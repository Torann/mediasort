<?php

namespace Torann\MediaSort\File\Image;

use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ManipulatorInterface;
use Torann\MediaSort\File\UploadedFile;
use Torann\MediaSort\Exceptions\InvalidClassException;

class Resizer
{
    /**
     * Image processor class.
     *
     * @var string
     */
    protected $image_processor;

    /**
     * Instance of imagine Interface.
     *
     * @var ImageInterface
     */
    protected $imagine;

    /**
     * Quality of the saved image.
     *
     * @var int
     */
    protected $quality;

    /**
     * Auto-orient images.
     *
     * @var bool
     */
    protected $auto_orient = false;

    /**
     * Constructor method
     *
     * @param string $image_processor
     * @param int    $quality
     * @param bool   $auto_orient
     *
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     */
    function __construct($image_processor, $quality = 90, $auto_orient = false)
    {
        if (!class_exists($image_processor)) {
            throw new InvalidClassException('Image processor not found.');
        }

        $this->image_processor = $image_processor;
        $this->quality = $quality;
        $this->auto_orient = $auto_orient;
    }

    /**
     * Resize an image using the computed settings.
     *
     * @param \Torann\MediaSort\File\UploadedFile $file
     * @param \stdClass                           $style
     *
     * @return string
     */
    public function resize(UploadedFile $file, $style)
    {
        $this->imagine = new $this->image_processor;

        $filePath = tempnam(sys_get_temp_dir(), 'STP') . '.' . $file->getClientOriginalName();
        list($width, $height, $option, $enlarge) = $this->parseStyleDimensions($style);
        $method = "resize" . ucfirst($option);

        if ($method == 'resizeCustom') {
            $this->resizeCustom($file, $style->value, $enlarge)
                ->save($filePath, ['quality' => $this->quality]);

            return $filePath;
        }

        $image = $this->imagine->open($file->getRealPath());

        if ($this->auto_orient) {
            $image = $this->autoOrient($file->getRealPath(), $image);
        }

        $this->$method($image, $width, $height, $enlarge)
            ->save($filePath, [
                'quality' => $this->quality,
                'flatten' => false,
            ]);

        return $filePath;
    }

    /**
     * parseStyleDimensions method
     *
     * Parse the given style dimensions to extract out the file processing options,
     * perform any necessary image resizing for a given style.
     *
     * @param \stdClass $style
     *
     * @return array
     */
    protected function parseStyleDimensions($style)
    {
        if (is_callable($style->value)) {
            return [null, null, 'custom', false];
        }

        $enlarge = true;

        // Don't allow the package to enlarge an image
        if (strpos($style->value, '?') !== false) {
            $style->value = str_replace('?', '', $style->value);
            $enlarge = false;
        }

        if (strpos($style->value, 'x') === false) {
            // Width given, height automatically selected to preserve aspect ratio (landscape).
            $width = $style->value;

            return [$width, null, 'landscape', $enlarge];
        }

        $dimensions = explode('x', $style->value);
        $width = $dimensions[0];
        $height = $dimensions[1];

        if (empty($width)) {
            // Height given, width automagically selected to preserve aspect ratio (portrait).
            return [null, $height, 'portrait', $enlarge];
        }

        $resizingOption = substr($height, -1, 1);

        if ($resizingOption == '#') {
            // Resize, then crop.
            $height = rtrim($height, '#');

            return [$width, $height, 'crop', $enlarge];
        }

        if ($resizingOption == '!') {
            // Resize by exact width/height (does not preserve aspect ratio).
            $height = rtrim($height, '!');

            return [$width, $height, 'exact', $enlarge];
        }

        // Let the script decide the best way to resize.
        return [$width, $height, 'auto', $enlarge];
    }

    /**
     * Resize an image as a landscape (width only)
     *
     * @param ImageInterface $image
     * @param string         $width
     * @param string         $height
     * @param bool           $enlarge
     *
     * @return ManipulatorInterface
     */
    protected function resizeLandscape(ImageInterface $image, $width, $height, $enlarge = true)
    {
        // Don't enlarge a small image
        $box = $image->getSize();
        $ratio = $box->getHeight() / $box->getWidth();

        if ($enlarge === false && $box->getWidth() < $width) {
            $width = $box->getWidth();
        }

        $dimensions = $image->getSize()
            ->widen($width)
            ->heighten($width * $ratio);

        return $image->resize($dimensions);
    }

    /**
     * Resize an image as a portrait (height only)
     *
     * @param ImageInterface $image
     * @param string         $width
     * @param string         $height
     * @param bool           $enlarge
     *
     * @return ManipulatorInterface
     */
    protected function resizePortrait(ImageInterface $image, $width, $height, $enlarge = true)
    {
        // Don't enlarge a small image
        $box = $image->getSize();
        $ratio = $box->getWidth() / $box->getHeight();

        if ($enlarge === false && $box->getHeight() < $height) {
            $height = $box->getHeight();
        }

        $dimensions = $image->getSize()
            ->heighten($height)
            ->widen($height * $ratio);

        return $image->resize($dimensions);
    }

    /**
     * Resize an image and then center crop it.
     *
     * @param ImageInterface $image
     * @param string         $width
     * @param string         $height
     * @param bool           $enlarge
     *
     * @return ManipulatorInterface
     */
    protected function resizeCrop(ImageInterface $image, $width, $height, $enlarge = true)
    {
        $size = $image->getSize();

        if ($enlarge === false && $size->getWidth() < $width) {
            $width = $size->getWidth();
        }

        if ($enlarge === false && $size->getHeight() < $height) {
            $height = $size->getHeight();
        }

        list($optimalWidth, $optimalHeight) = $this->getOptimalCrop($size, $width, $height, $enlarge);

        // Find center - this will be used for the crop
        $centerX = ($optimalWidth / 2) - ($width / 2);
        $centerY = ($optimalHeight / 2) - ($height / 2);

        return $image->resize(new Box($optimalWidth, $optimalHeight))
            ->crop(new Point($centerX, $centerY), new Box($width, $height));
    }

    /**
     * Resize an image to an exact width and height.
     *
     * @param ImageInterface $image
     * @param string         $width
     * @param string         $height
     * @param bool           $enlarge
     *
     * @return ImageInterface
     */
    protected function resizeExact(ImageInterface $image, $width, $height, $enlarge = true)
    {
        return $image->resize(new Box($width, $height));
    }

    /**
     * Resize an image as closely as possible to a given width and
     * height while still maintaining aspect ratio.
     *
     * This method is really just a proxy to other resize methods:
     *
     * If the current image is wider than it is tall, we'll resize landscape.
     * If the current image is taller than it is wide, we'll resize portrait.
     * If the image is as tall as it is wide (it's a squarey) then we'll
     * apply the same process using the new dimensions (we'll resize exact if
     * the new dimensions are both equal since at this point we'll have a square
     * image being resized to a square).
     *
     * @param ImageInterface $image
     * @param string         $width
     * @param string         $height
     * @param bool           $enlarge
     *
     * @return ManipulatorInterface
     */
    protected function resizeAuto(ImageInterface $image, $width, $height, $enlarge = true)
    {
        $size = $image->getSize();
        $originalWidth = $size->getWidth();
        $originalHeight = $size->getHeight();

        if ($originalHeight < $originalWidth) {
            return $this->resizeLandscape($image, $width, $height, $enlarge);
        }

        if ($originalHeight > $originalWidth) {
            return $this->resizePortrait($image, $width, $height, $enlarge);
        }

        if ($height < $width) {
            return $this->resizeLandscape($image, $width, $height, $enlarge);
        }

        if ($height > $width) {
            return $this->resizePortrait($image, $width, $height, $enlarge);
        }

        return $this->resizeExact($image, $width, $height, $enlarge);
    }

    /**
     * Resize an image using a user defined callback.
     *
     * @param UploadedFile $file
     * @param              $callable
     * @param bool         $enlarge
     *
     * @return \stdClass
     */
    protected function resizeCustom(UploadedFile $file, $callable, $enlarge = true)
    {
        return call_user_func_array($callable, [$file, $this->imagine, $enlarge]);
    }

    /**
     * Attempts to find the best way to crop.
     * Takes into account the image being a portrait or landscape.
     *
     * @param BoxInterface $size
     * @param string       $width
     * @param string       $height
     * @param bool         $enlarge
     *
     * @return array
     */
    protected function getOptimalCrop(BoxInterface $size, $width, $height, $enlarge = true)
    {
        $heightRatio = $size->getHeight() / $height;
        $widthRatio = $size->getWidth() / $width;

        if ($heightRatio < $widthRatio) {
            $optimalRatio = $heightRatio;
        }
        else {
            $optimalRatio = $widthRatio;
        }

        $optimalHeight = round($size->getHeight() / $optimalRatio, 2);
        $optimalWidth = round($size->getWidth() / $optimalRatio, 2);

        return [$optimalWidth, $optimalHeight];
    }

    /**
     * Re-orient an image using its embedded Exif profile orientation:
     *
     * 1. Attempt to read the embedded exif data inside the image to determine it's orientation.
     *    if there is no exif data (i.e an exeption is thrown when trying to read it) then we'll
     *    just return the image as is.
     * 2. If there is exif data, we'll rotate and flip the image accordingly to re-orient it.
     * 3. Finally, we'll strip the exif data from the image so that there can be no attempt to 'correct' it again.
     *
     * @param string         $path
     * @param ImageInterface $image
     *
     * @return ImageInterface $image
     */
    protected function autoOrient($path, ImageInterface $image)
    {
        if (function_exists('exif_read_data')) {
            try {
                $exif = exif_read_data($path);
            }
            catch (\ErrorException $e) {
                return $image;
            }

            if (isset($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 2:
                        $image->flipHorizontally();
                        break;
                    case 3:
                        $image->rotate(180);
                        break;
                    case 4:
                        $image->flipVertically();
                        break;
                    case 5:
                        $image->flipVertically()
                            ->rotate(90);
                        break;
                    case 6:
                        $image->rotate(90);
                        break;
                    case 7:
                        $image->flipHorizontally()
                            ->rotate(90);
                        break;
                    case 8:
                        $image->rotate(-90);
                        break;
                }
            }

            return $image->strip();
        }
        else {
            return $image;
        }
    }
}
