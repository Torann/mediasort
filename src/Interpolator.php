<?php

namespace Torann\MediaSort;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Interpolator
{
    /**
     * Manager instance.
     *
     * @var \Torann\MediaSort\Manager
     */
    protected $manager;

    /**
     * Constructor method
     *
     * @param \Torann\MediaSort\Manager $manager
     */
    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Interpolate a string.
     *
     * @param string $string
     * @param string $style
     *
     * @return string
     */
    public function interpolate($string, $style = '')
    {
        return preg_replace_callback("/{(([[:alnum:]]|_|\.|-)+)?}/", function ($match) use ($style) {
            $key = $match[1];

            // Create local method call.
            $method = 'get' . studly_case($key);

            // Check for a custom interpolator value.
            if (method_exists($this, $method)) {
                return $this->$method($style);
            }

            // Check for an interpolator override
            if ($override = $this->manager->config("interpolate.{$key}")) {
                return $override;
            }

            return $this->getAttribute($key);
        }, $string);
    }

    /**
     * Returns the file name.
     *
     * @return string
     */
    protected function getFilename()
    {
        return $this->manager->originalFilename();
    }

    /**
     * Returns the current class name, taking into account namespaces.
     *
     * @return string
     */
    protected function getClass()
    {
        return strtolower($this->handleBackslashes($this->manager->getInstanceClass()));
    }

    /**
     * Returns the basename portion of the media file, e.g 'file' for file.jpg.
     *
     * @return string
     */
    protected function getBasename()
    {
        return pathinfo($this->manager->originalFilename(), PATHINFO_FILENAME);
    }

    /**
     * Returns the extension of the media file, e.g 'jpg' for file.jpg.
     *
     * @return string
     */
    protected function getExtension()
    {
        return pathinfo($this->manager->originalFilename(), PATHINFO_EXTENSION);
    }

    /**
     * Returns the id of the current object instance.
     *
     * @return string
     */
    protected function getId()
    {
        if ($key = $this->manager->model_primary_key) {
            return $this->manager->getInstance()->{$key};
        }

        return $this->manager->getInstance()->getKey();
    }

    /**
     * Returns the pluralized form of the media name. e.g.
     * "avatars" for an media of :avatar.
     *
     * @return string
     */
    protected function getMedia()
    {
        return Str::plural($this->manager->name);
    }

    /**
     * Returns the style, or the default style if an empty style is supplied.
     *
     * @param string $style
     *
     * @return string
     */
    protected function getStyle($style = '')
    {
        return $style ?: $this->manager->default_style;
    }

    /**
     * Returns the the applications base URL.
     *
     * @return string
     */
    protected function getAppUrl()
    {
        return url('/');
    }

    /**
     * Returns the root of the Laravel project.
     *
     * @return string
     */
    protected function getLaravelRoot()
    {
        return realpath(base_path());
    }

    /**
     * Return attribute from model.
     *
     * @param string $string
     *
     * @return string
     */
    public function getAttribute($string)
    {
        return $this->manager->getInstance()->getAttribute($string);
    }

    /**
     * Utility function to turn a back-slashed string into a string
     * suitable for use in a file path, e.g '\foo\bar' becomes 'foo/bar'.
     *
     * @param string $string
     *
     * @return string
     */
    protected function handleBackslashes($string)
    {
        return str_replace('\\', '/', ltrim($string, '\\'));
    }
}