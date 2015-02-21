<?php namespace Torann\MediaSort\ORM;

use Exception, Config, App;

trait EloquentTrait {

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
     * @param  string $name
     * @param  array  $options
     * @return void
     */
    public function hasMediaFile($name, array $options = [])
    {
        // Register the media with MediaSort and setup event listeners.
        $this->registerMedia($name, $options);
    }

    /**
     * Register eloquent event handlers.
     * We'll spin through each of the media file defined on this class
     * and register callbacks for the events we need to observe in order to
     * handle file uploads.
     *
     * @return void
     */
    public static function bootMediaSort()
    {
        static::saved(function($instance) {
            foreach($instance->mediaFiles as $mediaFile) {
                $mediaFile->afterSave($instance);
            }
        });

        static::deleting(function($instance) {
            foreach($instance->mediaFiles as $mediaFile) {
                $mediaFile->beforeDelete($instance);
            }
        });

        static::deleted(function($instance) {
            foreach($instance->mediaFiles as $mediaFile) {
                $mediaFile->afterDelete($instance);
            }
        });
    }

    /**
     * Handle the dynamic retrieval of media items.
     *
     * @param  string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->mediaFiles))
        {
            return $this->mediaFiles[$key];
        }

        return parent::getAttribute($key);
    }

    /**
     * Handle the dynamic setting of media items.
     *
     * @param  string $key
     * @param  mixed $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        if (array_key_exists($key, $this->mediaFiles))
        {
            if ($value)
            {
                $mediaFile = $this->mediaFiles[$key];
                $mediaFile->setUploadedFile($value, $key);
            }

            return;
        }

        parent::setAttribute($key, $value);
    }

    /**
     * Register an media type.
     * and add the media to the list of media to be processed during saving.
     *
     * @param  string $name
     * @param  array $options
     *
     * @return mixed
     * @throws Exception
     */
    protected function registerMedia($name, $options)
    {
        $options = $this->mergeOptions($options);

        if (preg_match("/:id\b/", $options['url']) !== 1)
        {
            throw new Exception('Invalid Url: an id interpolation is required.', 1);
        }

        $media = App::make('MediaSort', ['name' => $name, 'options' => $options]);
        $media->setInstance($this);
        $this->mediaFiles[$name] = $media;
    }

    /**
     * Merge configuration options.
     * Here we'll merge user defined options with the MediaSort defaults in a cascading manner.
     * We start with overall MediaSort options.  Next we merge in storage driver specific options.
     * Finally we'll merge in media specific options on top of that.
     *
     * @param  array $options
     * @return array
     */
    protected function mergeOptions($options)
    {
        $defaultOptions = Config::get('mediasort', array());
        $options = array_merge($defaultOptions, (array) $options);
        $options['styles'] = array_merge((array) $options['styles'], ['original' => '']);

        return $options;
    }

}
