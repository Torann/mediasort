<?php

namespace Torann\MediaSort;

use Exception;
use Generator;
use Illuminate\Support\Arr;

trait HasMedia
{
    protected array $media_instances = [];

    /**
     * Available attachments with optional configurations.
     */
    protected array $media = [];

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
     * {@inheritDoc}
     */
    public function getMediaFiles(): Generator
    {
        foreach (array_keys($this->media) as $name) {
            yield $name => $this->getMedia($name);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hasMediaFile(string $name, array $options = [])
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
    public function updateMediaFile(string $name, string $key, mixed $value)
    {
        Arr::set($this->media, "{$name}.config.{$key}", $value);
    }

    /**
     * Determine if there are any attachments queued for processing.
     *
     * @return bool
     */
    public function hasQueuedAttachments(): bool
    {
        try {
            return count($this->getQueuedAttachments()) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Return all queued attachments that need processing.
     *
     * @return array
     * @throws Exception
     */
    public function getQueuedAttachments(): array
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
     * {@inheritDoc}
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->media)) {
            return $this->getMedia($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * {@inheritDoc}
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
    protected function getMedia(string $name): Manager
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
    protected function hasMediaInstance(string $name): bool
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
    protected function getMediaInstance(string $name): Manager
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
    public function canDeleteMedia(): bool
    {
        return (isset($this->forceDeleting))
            ? $this->forceDeleting
            : true;
    }
}
