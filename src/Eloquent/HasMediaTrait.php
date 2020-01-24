<?php

namespace Torann\MediaSort\Eloquent;

use Exception;
use Generator;
use Illuminate\Support\Arr;
use Torann\MediaSort\Manager;

trait HasMediaTrait
{
    /**
     * Available attachments with optional configurations.
     *
     * @var array[]
     */
    protected $media = [];

    /**
     * @var array
     */
    protected $media_instances = [];

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
            foreach ($instance->media as $name => $params) {
                if (empty($params['file'] ?? null) === false) {
                    $instance->getMediaInstance($name)->beforeSave(
                        $params['file']
                    );

                    // Remove the file from the reference since it is now part of the shared
                    // manager instance and will be processed in the saved event
                    unset($instance->media[$name]['file']);
                }
            }
        });

        static::saved(function ($instance) {
            /** @var self $instance */
            foreach ($instance->media as $name => $params) {
                if ($instance->hasMediaInstance($name)) {
                    $instance->getMediaInstance($name)->afterSave();

                    // Flush the media instance
                    unset($instance->media_instances[$name]);
                }
            }
        });

        static::deleting(function ($instance) {
            /** @var self $instance */
            if ($instance->canDeleteMedia()) {
                foreach ($instance->media as $name => $params) {
                    $instance->getMediaInstance($name)->beforeDelete();
                }
            }
        });

        static::deleted(function ($instance) {
            /** @var self $instance */
            if ($instance->canDeleteMedia()) {
                foreach ($instance->media as $name => $params) {
                    $instance->getMediaInstance($name)->afterDelete();

                    // Flush the media instance
                    unset($instance->media_instances[$name]);
                }
            }
        });
    }

    /**
     * Accessor method for the media instances property.
     *
     * @return Generator
     * @throws Exception
     */
    public function getMediaFiles(): Generator
    {
        foreach (array_keys($this->media) as $name) {
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
        Arr::set($this->media, "{$name}.config", $options);
    }

    /**
     * Update a media type config in the list of available media.
     *
     * @param string $name
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     * @throws Exception
     */
    public function updateMediaFile($name, $key, $value)
    {
        Arr::set($this->media, "{$name}.config.{$key}", $value);
    }

    /**
     * Determine if there are any attachments queued for processing.
     *
     * @return bool
     */
    public function hasQueuedAttachments()
    {
        try {
            return count($this->getQueuedAttachments()) > 0;
        }
        catch (Exception $e) {
            return false;
        }
    }

    /**
     * Return all queued attachments that need processing.
     *
     * @return array
     * @throws Exception
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
     * @throws Exception
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->media)) {
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
        if (array_key_exists($key, $this->media)) {
            Arr::set($this->media, "{$key}.file", $value);

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
     * @throws Exception
     */
    protected function getMedia($name): Manager
    {
        $instances = new Manager(
            $name, Arr::get($this->media, "{$name}.config")
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
        return array_key_exists($name, $this->media_instances);
    }

    /**
     * Get the media manager instance for the provided media config.
     *
     * @param string $name
     *
     * @return Manager
     * @throws Exception
     */
    protected function getMediaInstance($name): Manager
    {
        if (array_key_exists($name, $this->media_instances) === false) {
            $this->media_instances[$name] = $this->getMedia($name);
        }

        return $this->media_instances[$name];
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
