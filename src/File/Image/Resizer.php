<?php

namespace Torann\MediaSort\File\Image;

use Imagine\Image\Box;
use Imagine\Image\Point;
use Illuminate\Support\Arr;
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
     * Resizer options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Constructor method
     *
     * @param string $image_processor
     * @param array  $options
     *
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     */
    public function __construct($image_processor, array $options = [])
    {
        if (class_exists($image_processor) === false) {
            throw new InvalidClassException('Image processor not found.');
        }

        $this->image_processor = $image_processor;
        $this->options = $options;
    }

    /**
     * Resize an image using the computed settings.
     *
     * @param UploadedFile $file
     * @param string       $style
     *
     * @return string
     */
    public function resize(UploadedFile $file, $style)
    {
        $quality = $this->option('quality', 90);

        $this->imagine = new $this->image_processor;

        $file_path = @tempnam(sys_get_temp_dir(), 'STP') . '.' . $file->getClientOriginalName();
        list($width, $height, $option, $enlarge) = $this->parseStyleDimensions($style);
        $method = 'resize' . ucfirst($option);

        if ($method == 'resizeCustom') {
            $this->resizeCustom($file, $style, $enlarge)
                ->save($file_path, [
                    'quality' => $quality,
                ]);

            return $file_path;
        }

        $image = $this->imagine->open($file->getRealPath());

        // Orient the file programmatically
        if ($this->option('auto_orient', false)) {
            $image = $this->autoOrient($file->getRealPath(), $image);
        }

        // Force a color palette
        if ($palette = $this->option('color_palette')) {
            $image->usePalette(new $palette);
        }

        $this->$method($image, $width, $height, $enlarge)
            ->save($file_path, [
                'quality' => $quality,
                'flatten' => false,
            ]);

        return $file_path;
    }

    /**
     * parseStyleDimensions method
     *
     * Parse the given style dimensions to extract out the file processing options,
     * perform any necessary image resizing for a given style.
     *
     * @param string $style
     *
     * @return array
     */
    protected function parseStyleDimensions($style)
    {
        if (is_callable($style) === true) {
            return [null, null, 'custom', false];
        }

        $enlarge = true;

        // Don't allow the package to enlarge an image
        if (strpos($style, '?') !== false) {
            $style = str_replace('?', '', $style);
            $enlarge = false;
        }

        if (strpos($style, 'x') === false) {
            // Width given, height automatically selected to preserve aspect ratio (landscape).
            $width = $style;

            return [$width, null, 'landscape', $enlarge];
        }

        $dimensions = explode('x', $style);
        $width = $dimensions[0];
        $height = $dimensions[1];

        if (empty($width)) {
            // Height given, width automatically selected to preserve aspect ratio (portrait).
            return [null, $height, 'portrait', $enlarge];
        }

        $resizing_option = substr($height, -1, 1);

        if ($resizing_option == '#') {
            // Resize, then crop.
            $height = rtrim($height, '#');

            return [$width, $height, 'crop', $enlarge];
        }

        if ($resizing_option == '!') {
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

        list($optimal_width, $optimal_height) = $this->getOptimalCrop($size, $width, $height, $enlarge);

        // Find center - this will be used for the crop
        $center_x = ($optimal_width / 2) - ($width / 2);
        $center_y = ($optimal_height / 2) - ($height / 2);

        return $image->resize(new Box($optimal_width, $optimal_height))
            ->crop(new Point($center_x, $center_y), new Box($width, $height));
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
        $original_width = $size->getWidth();
        $original_height = $size->getHeight();

        if ($original_height < $original_width) {
            return $this->resizeLandscape($image, $width, $height, $enlarge);
        }

        if ($original_height > $original_width) {
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
        $height_ratio = $size->getHeight() / $height;
        $width_ratio = $size->getWidth() / $width;

        if ($height_ratio < $width_ratio) {
            $optimal_ratio = $height_ratio;
        }
        else {
            $optimal_ratio = $width_ratio;
        }

        $optimal_height = round($size->getHeight() / $optimal_ratio, 2);
        $optimal_width = round($size->getWidth() / $optimal_ratio, 2);

        return [$optimal_width, $optimal_height];
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
            $exif = exif_read_data($path);

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

    /**
     * Get option value.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function option($key, $default = null)
    {
        return Arr::get($this->options, $key, $default);
    }
}
