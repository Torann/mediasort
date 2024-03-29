<?php

namespace Torann\MediaSort;

use Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $disk
 * @property string $local_root
 * @property string $url
 * @property string $prefix_url
 * @property string $default_url
 * @property string $waiting_url
 * @property string $loading_url
 * @property string $failed_url
 * @property bool   $queueable
 * @property string $queue_path
 * @property string $visibility
 * @property string $image_processor
 * @property int    $image_quality
 * @property bool   $auto_orient
 * @property string $color_palette
 * @property string $default_style
 * @property array  $styles
 * @property bool   $keep_old_files
 * @property bool   $preserve_files
 * @property string $model_primary_key
 * @property array  $interpolate
 */
class Manager
{
    use Concerns\UpdatesAttributes,
        Concerns\HasQueuing,
        Concerns\HasConfig,
        Concerns\HasDisks;

    // Queued states
    const QUEUE_NA = 0;
    const QUEUE_DONE = 1;
    const QUEUE_WAITING = 2;
    const QUEUE_WORKING = 3;
    const QUEUE_FAILED = 4;

    public string $name;
    protected Model|null $model = null;

    /**
     * @param string     $name
     * @param array|null $config
     *
     * @throws Exception
     */
    public function __construct(string $name, array $config = null)
    {
        $this->name = $name;

        $this->setConfig($config);
    }

    /**
     * This provides a mechanism for the attachment to access properties of the
     * corresponding model instance it's attached to.
     *
     * @param Model $model
     *
     * @return self
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Return the underlying model object for this attachment.
     *
     * @return Model|null
     */
    public function getModel(): Model|null
    {
        return $this->model;
    }

    /**
     * Generates the url to a file upload.
     *
     * @param string $style
     *
     * @return string
     */
    public function url(string $style = ''): string
    {
        if ($this->isQueued()) {
            return $this->queueUrl($style);
        }

        if ($this->getAttribute('filename')) {
            if ($path = $this->path($style)) {
                return $this->config('prefix_url') . $path;
            }
        }

        return $this->defaultUrl($style);
    }

    /**
     * Generates the filesystem path to an uploaded file.
     *
     * @param string $style
     *
     * @return string
     */
    public function path(string $style = ''): string
    {
        if ($this->getAttribute('filename')) {
            return $this->getInterpolator()->interpolate($this->url, $style);
        }

        return '';
    }

    /**
     * Generates an array of all style urls.
     *
     * @param bool $skip_empty
     * @param bool $include_original
     *
     * @return array|null
     */
    public function toArray(bool $skip_empty = false, bool $include_original = true): array|null
    {
        // Skip when no media
        if ($skip_empty === true && $this->hasMedia() === false) {
            return null;
        }

        $returns = [];

        foreach ($this->styles as $name => $style) {
            if ($include_original === false
                && $this->config('default_style') === $name
            ) {
                continue;
            }

            $returns[$name] = $this->url($name);
        }

        return $returns;
    }

    /**
     * Determine if object has media.
     *
     * @return bool
     */
    public function hasMedia(): bool
    {
        if ($this->getAttribute('filename') && $this->path()) {
            return $this->isQueued() === false;
        }

        return false;
    }

    /**
     * Return the attachment attribute value.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute(string $key): mixed
    {
        // Sanitize the key
        $key = preg_replace('/^_/', '', $key);

        // Decoder ring for legacy keys
        switch ($key) {
            case 'size':
                $key = 'file_size';
                break;
            case 'filename':
            case 'original_filename':
                $key = 'file_name';
                break;
        }

        return $this->getModel()->getAttribute("{$this->name}_{$key}");
    }

    /**
     * Get the interpolator instance.
     *
     * @return Interpolator
     */
    public function getInterpolator(): Interpolator
    {
        return new Interpolator($this);
    }

    /**
     * Handle the dynamic setting of attachment options.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function __set(string $key, mixed $value)
    {
        $this->config([$key => $value]);
    }

    /**
     * Handle the dynamic retrieval of attachment options.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->config($key);
    }

    /**
     * Generates the default url if no file attachment is present.
     *
     * @param string $style
     *
     * @return string
     */
    protected function defaultUrl(string $style = ''): string
    {
        if ($this->config('default_url')) {
            $url = $this->getInterpolator()
                ->interpolate($this->config('default_url'), $style);

            return parse_url($url, PHP_URL_HOST) ? $url : $this->config('prefix_url') . $url;
        }

        return '';
    }

    /**
     * Transform an array into path.
     *
     * @param array $args
     *
     * @return string
     */
    protected function joinPaths(...$args): string
    {
        return rtrim(preg_replace('/\/{2,}/', '/', join('/', $args)), '/');
    }
}
