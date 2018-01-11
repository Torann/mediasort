<?php

namespace Torann\MediaSort;

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
    function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Interpolate a string.
     *
     * @param string $string
     * @param string $styleName
     *
     * @return string
     */
    public function interpolate($string, $styleName = '')
    {
        return preg_replace_callback("/{(([[:alnum:]]|_|\.|-)+)?}/", function ($match) use ($styleName) {
            //// Get a value from relationship
            //if (strpos($match[1], '.')) {
            //    list($key, $value) = explode('.', $match[1]);
            //    return $this->getAttribute($key)->getAttribute($value);
            //}

            // Create local method call.
            $method = 'get' . studly_case($match[1]);

            // Is interpolator value?
            if (method_exists($this, $method)) {
                return $this->$method($styleName);
            }

            return $this->getAttribute($match[1]);
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
        return str_plural($this->manager->name);
    }

    /**
     * Returns the style, or the default style if an empty style is supplied.
     *
     * @param string $styleName
     *
     * @return string
     */
    protected function getStyle($styleName = '')
    {
        return $styleName ?: $this->manager->default_style;
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