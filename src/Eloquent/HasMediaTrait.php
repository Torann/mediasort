<?php

namespace Torann\MediaSort\Eloquent;

use Exception;
use Generator;
use Illuminate\Support\Arr;
use Torann\MediaSort\Manager;

trait HasMediaTrait
{
    /**
     * File media configurations.
     *
     * @var array[]
     */
    protected static $media = [];

    /**
     * @var array
     */
    protected static $media_instances = [];

    /**
     * Register eloquent event handlers.
     *
     * We'll spin through each of the media file defined on this class
     * and register callbacks for the events we need to observe in order to
     * handle file uploads.
     *
     * @return void
     */
    public static function bootHasMediaTrait()
    {
        static::saving(function ($instance) {
            /** @var self $instance */
            foreach ($instance->configMediaFiles() as $name => $params) {
                if (is_null($params['file'] ?? null) === false) {
                    $instance->getMediaInstance($name)->beforeSave(
                        $params['file']
                    );
                }
            }
        });

        static::saved(function ($instance) {
            /** @var self $instance */
            foreach ($instance->configMediaFiles() as $name => $params) {
                if ($instance->hasMediaInstance($name)) {
                    $instance->getMediaInstance($name)->afterSave();
                }
            }
        });

        static::deleting(function ($instance) {
            /** @var self $instance */
            if ($instance->canDeleteMedia()) {
                foreach ($instance->configMediaFiles() as $name => $params) {
                    $instance->getMediaInstance($name)->beforeDelete();
                }
            }
        });

        static::deleted(function ($instance) {
            /** @var self $instance */
            if ($instance->canDeleteMedia()) {
                foreach ($instance->configMediaFiles() as $name => $params) {
                    $instance->getMediaInstance($name)->afterDelete();
                }
            }
        });
    }

    /**
     * @param mixed $key
     * @param mixed $value
     *
     * @return array|mixed|null
     */
    public function configMediaFiles($key = null, $value = null)
    {
        if (isset(self::$media[self::class]) === false) {
            self::$media[self::class] = [];
        }

        if ($value !== null) {
            return Arr::set(self::$media[self::class], $key, $value);
        }

        if ($key !== null) {
            return Arr::get(self::$media[self::class], $key);
        }

        return self::$media[self::class];
    }

    /**
     * Accessor method for the media instances property.
     *
     * @return Generator
     */
    public function getMediaFiles(): Generator
    {
        foreach (array_keys($this->configMediaFiles()) as $name) {
            yield $this->getMedia($name);
        }
    }

    /**
     * Add a new file media type to the list of available media.
     * This function acts as a quasi constructor for this trait.
     *
     * @param string $name
     * @param array  $options
     *
     * @return void
     * @throws Exception
     */
    public function hasMediaFile($name, array $options = [])
    {
        $this->configMediaFiles("{$name}.config", $options);
    }

    /**
     * Determine if there are any attachments queued for processing.
     *
     * @return bool
     */
    public function hasQueuedAttachments()
    {
        return count($this->getQueuedAttachments()) > 0;
    }

    /**
     * Return all queued attachments that need processing.
     *
     * @return array
     */
    public function getQueuedAttachments()
    {
        $queued = [];

        foreach ($this->getMediaFiles() as $name => $attachment) {
            if ($attachment->isQueued()) {
                $queued[$name] = $attachment;
            }
        }

        return $queued;
    }

    /**
     * Handle the dynamic retrieval of media items.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->configMediaFiles())) {
            return $this->getMedia($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Handle the dynamic setting of media items.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if (array_key_exists($key, $this->configMediaFiles())) {
            $this->configMediaFiles("{$key}.file", $value);

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Get the media manager instance for the provided media config.
     *
     * @param string $name
     *
     * @return Manager
     */
    protected function getMedia($name): Manager
    {
        $media = $this->configMediaFiles($name);

        $instances = new Manager(
            $name, $this->mergeOptions(Arr::get($media, 'config'))
        );

        $instances->setModel($this);

        return $instances;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    protected function hasMediaInstance($name): bool
    {
        return isset(self::$media_instances[self::class])
            && array_key_exists($name, self::$media_instances[self::class]);
    }

    /**
     * Get the media manager instance for the provided media config.
     *
     * @param string $name
     *
     * @return Manager
     */
    protected function getMediaInstance($name): Manager
    {
        if (isset(self::$media_instances[self::class]) === false) {
            self::$media_instances[self::class] = [];
        }

        if (array_key_exists($name, self::$media_instances[self::class]) === false) {
            self::$media_instances[self::class][$name] = $this->getMedia($name);
        }

        return self::$media_instances[self::class][$name];
    }

    /**
     * Merge configuration options.
     *
     * Here we'll merge user defined options with the MediaSort defaults in a cascading manner.
     * We start with overall MediaSort options.  Next we merge in storage driver specific options.
     * Finally we'll merge in media specific options on top of that.
     *
     * @param array $options
     *
     * @return array
     */
    protected function mergeOptions(array $overrides = null)
    {
        $options = config('mediasort', []);

        // Apply any overrides
        if (is_array($overrides)) {
            $options = array_merge($options, $overrides);
        }

        $options['styles'] = array_merge((array) $options['styles'], ['original' => '']);

        return $options;
    }

    /**
     * Determine if the media is going to be deleted.
     *
     * This fixes the bug with soft deleting a resource also
     * deletes the attached media objects.
     *
     * @return bool
     */
    public function canDeleteMedia()
    {
        return (isset($this->forceDeleting))
            ? $this->forceDeleting
            : true;
    }
}
