<?php

namespace Torann\MediaSort;

use Illuminate\Support\Str;

class Interpolator
{
    protected Manager $manager;

    /**
     * @param Manager $manager
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
    public function interpolate(string $string, string $style = ''): string
    {
        return preg_replace_callback("/{(([[:alnum:]]|_|\.|-)+)?}/", function ($match) use ($style) {
            $key = $match[1];

            // Create local method call.
            $method = 'get' . Str::studly($key);

            // Check for a custom interpolator value.
            if (method_exists($this, $method)) {
                return $this->{$method}($style);
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
    protected function getFilename(): string
    {
        return $this->manager->getAttribute('filename');
    }

    /**
     * Returns the queue state test.
     *
     * @return string
     */
    protected function getQueueState(): string
    {
        return $this->manager->getQueuedStateText();
    }

    /**
     * Returns the current class name, taking into account namespaces.
     *
     * @return string
     */
    protected function getClass(): string
    {
        return strtolower($this->handleBackslashes(
            get_class($this->manager->getModel())
        ));
    }

    /**
     * Returns the basename portion of the media file, e.g 'file' for file.jpg.
     *
     * @return string
     */
    protected function getBasename(): string
    {
        return pathinfo($this->getFilename(), PATHINFO_FILENAME);
    }

    /**
     * Returns the extension of the media file, e.g 'jpg' for file.jpg.
     *
     * @return string
     */
    protected function getExtension(): string
    {
        return pathinfo($this->getFilename(), PATHINFO_EXTENSION);
    }

    /**
     * Returns the id of the current model.
     *
     * @return mixed
     */
    protected function getId(): mixed
    {
        if ($key = $this->manager->config('model_primary_key')) {
            return $this->manager->getModel()->{$key};
        }

        return $this->manager->getModel()->getKey();
    }

    /**
     * Returns the pluralized form of the media name. e.g.
     * "avatars" for an media of :avatar.
     *
     * @return string
     */
    protected function getMedia(): string
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
    protected function getStyle(string $style = ''): string
    {
        return $style ?: $this->manager->config('default_style');
    }

    /**
     * Return the applications base URL.
     *
     * @return string
     */
    protected function getAppUrl(): string
    {
        return url('/');
    }

    /**
     * Returns the root of the Laravel project.
     *
     * @return string
     */
    protected function getLaravelRoot(): string
    {
        return realpath(base_path());
    }

    /**
     * Return attribute from model.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute(string $key): mixed
    {
        return $this->manager->getModel()->getAttribute($key);
    }

    /**
     * Utility function to turn a back-slashed string into a string
     * suitable for use in a file path, e.g '\foo\bar' becomes 'foo/bar'.
     *
     * @param string $string
     *
     * @return string
     */
    protected function handleBackslashes(string $string): string
    {
        return str_replace('\\', '/', ltrim($string, '\\'));
    }
}
