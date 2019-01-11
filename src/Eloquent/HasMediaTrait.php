<?php

namespace Torann\MediaSort\Eloquent;

use Exception;
use Torann\MediaSort\Manager;

trait HasMediaTrait
{
    /**
     * All of the model's current file media.
     *
     * @var array
     */
    protected $mediaFiles = [];

    /**
     * Accessor method for the $mediaFiles property.
     *
     * @return array
     */
    public function getMediaFiles()
    {
        return $this->mediaFiles;
    }

    /**
     * Add a new file media type to the list of available media.
     * This function acts as a quasi constructor for this trait.
     *
     * @param string $name
     * @param array  $options
     *
     * @return void
     */
    public function hasMediaFile($name, array $options = [])
    {
        // Register the media with MediaSort and setup event listeners.
        $this->registerMedia($name, $options);
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
            foreach ($instance->mediaFiles as $mediaFile) {
                $mediaFile->afterSave($instance);
            }
        });

        static::deleting(function ($instance) {
            if ($instance->canDeleteMedia()) {
                foreach ($instance->mediaFiles as $mediaFile) {
                    $mediaFile->beforeDelete($instance);
                }
            }
        });

        static::deleted(function ($instance) {
            if ($instance->canDeleteMedia()) {
                foreach ($instance->mediaFiles as $mediaFile) {
                    $mediaFile->afterDelete($instance);
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

        foreach ($this->mediaFiles as $name => $attachment) {
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
        if (array_key_exists($key, $this->mediaFiles)) {
            return $this->mediaFiles[$key];
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
        if (array_key_exists($key, $this->mediaFiles)) {
            if ($value) {
                $this->mediaFiles[$key]
                    ->setUploadedFile($value, $key);
            }

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Register an media type and add the media to the
     * list of media to be processed during saving.
     *
     * @param string $name
     * @param array  $options
     *
     * @return void
     * @throws Exception
     */
    protected function registerMedia($name, $options)
    {
        $this->mediaFiles[$name] = new Manager($name, $this->mergeOptions($options));
        $this->mediaFiles[$name]->setInstance($this);
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
        $options = array_merge(config('mediasort', []), (array)$options);
        $options['styles'] = array_merge((array)$options['styles'], ['original' => '']);

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
