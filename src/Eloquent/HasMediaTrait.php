<?php

namespace Torann\MediaSort\Eloquent;

use Exception;
use Generator;
use Torann\MediaSort\Manager;

trait HasMediaTrait
{
    /**
     * File media configurations.
     *
     * @var array[]
     */
    protected $media_config = [];

    /**
     * Accessor method for the media instances property.
     *
     * @return Generator
     */
    public function getMediaFiles(): Generator
    {
        foreach (array_keys($this->media_config) as $name) {
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
        $this->media_config[$name] = $options;
    }

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
        static::saved(function ($instance) {
            foreach ($instance->getMediaFiles() as $media_instance) {
                $media_instance->afterSave($instance);
            }
        });

        static::deleting(function ($instance) {
            if ($instance->canDeleteMedia()) {
                foreach ($instance->getMediaFiles() as $media_instance) {
                    $media_instance->beforeDelete($instance);
                }
            }
        });

        static::deleted(function ($instance) {
            if ($instance->canDeleteMedia()) {
                foreach ($instance->getMediaFiles() as $media_instance) {
                    $media_instance->afterDelete($instance);
                }
            }
        });
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
        if (array_key_exists($key, $this->media_config)) {
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
        if (array_key_exists($key, $this->media_config)) {
            if ($value) {
                $this->getMedia($key)->setUploadedFile($value, $key);
            }

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
        $instances = new Manager(
            $name, $this->mergeOptions($this->media_config[$name] ?? [])
        );

        $instances->setInstance($this);

        return $instances;
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
    protected function mergeOptions($options)
    {
        $options = array_merge(config('mediasort', []), (array) $options);
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
