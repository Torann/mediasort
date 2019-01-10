<?php

namespace Torann\MediaSort;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Torann\MediaSort\File\UploadedFile;
use Torann\MediaSort\File\Image\Resizer;
use Torann\MediaSort\Exceptions\InvalidClassException;

class Manager
{
    /**
     * Media identifier.
     *
     * @var string
     */
    public $name;

    /**
     * The model the attachment belongs to.
     *
     * @var Model
     */
    protected $instance;

    /**
     * Configuration values.
     *
     * @var array
     */
    protected $config = [];

    /**
     * The uploaded file object for the attachment.
     *
     * @var \Torann\MediaSort\File\UploadedFile
     */
    protected $uploadedFile;

    /**
     * An FileManager instance for converting file input formats (Symfony uploaded file object
     * arrays, string, etc) into an instance of \Torann\MediaSort\UploadedFile.
     *
     * @var \Torann\MediaSort\FileManager
     */
    protected $fileManagerInstance;

    /**
     * An instance of the disk.
     *
     * @var \Torann\MediaSort\Disks\AbstractDisk.
     */
    protected $diskInstance;

    /**
     * Queue used for various file processing.
     *
     * @var array
     */
    protected $queues = [];

    /**
     * Constructor method
     *
     * @param string $name
     * @param array  $config
     */
    public function __construct($name, array $config)
    {
        $this->name = $name;
        $this->setConfig($config);
    }

    /**
     * Mutator method for the uploadedFile property.
     *
     * @param mixed  $uploadedFile
     * @param string $styleName
     *
     * @return void
     */
    public function setUploadedFile($uploadedFile, $styleName)
    {
        $this->clear();

        if ($uploadedFile == MEDIASORT_NULL) {
            return;
        }

        $this->uploadedFile = $this->getFileManager()->make($uploadedFile);

        // Get the original values
        $filename = $this->uploadedFile->getClientOriginalName();
        $content_type = $this->uploadedFile->getMimeType();

        // Set model values
        $this->instanceWrite('file_name', $filename);
        $this->instanceWrite('file_size', $this->uploadedFile->getSize());
        $this->instanceWrite('content_type', $content_type);
        $this->instanceWrite('updated_at', date('Y-m-d H:i:s'));

        // Queue all styles for writing
        $this->setQueue('write', $this->styles);
    }

    /**
     * Set disk property.
     *
     * @param string $name
     *
     * @return self
     */
    public function setDisk($name)
    {
        $this->__set('disk', $name);

        return $this;
    }

    /**
     * Get disk instance.
     *
     * @return \Torann\MediaSort\Disks\AbstractDisk
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     */
    public function getDisk()
    {
        if ($this->diskInstance === null) {
            // Create disk class
            $class = "\\Torann\\MediaSort\\Disks\\" . ucfirst($this->config('disk'));

            // Verify disk
            if (class_exists($class) === false) {
                throw new InvalidClassException("Disk type \"{$class}\" not found.");
            }

            // Create disk instance
            $this->diskInstance = Container::getInstance()->makeWith($class, ['media' => $this]);
        }

        return $this->diskInstance;
    }

    /**
     * Mutator method for the instance property.
     *
     * This provides a mechanism for the attachment to access properties of the
     * corresponding model instance it's attached to.
     *
     * @param Model $instance
     *
     * @return void
     */
    public function setInstance($instance)
    {
        $this->instance = $instance;
    }

    /**
     * Return the underlying instance object for this attachment.
     *
     * @return Model
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * Mutator method for the config property.
     *
     * @param array $config
     *
     * @return void
     * @throws Exception
     */
    public function setConfig($config)
    {
        $this->config = $config;

        // Sanity check
        if (strpos($this->config['url'], '{id}') === false) {
            throw new Exception('Invalid Url: an id interpolation is required.', 1);
        }

        // Set media disk
        $this->config['disk'] = Arr::get($this->config, 'disk', config('filesystems.default', 'local'));
    }

    /**
     * Accessor method for the QueuedForDeletion property.
     *
     * @param string $queue
     *
     * @return array
     */
    public function getQueue($queue)
    {
        return (array)Arr::get($this->queues, $queue, []);
    }

    /**
     * Set an item to be queued.
     *
     * @param string       $queue
     * @param string|array $value
     */
    public function setQueue($queue, $value)
    {
        // Ensure the value is an array
        if (is_array($value) === false) {
            $value = [$value];
        }

        $this->queues[$queue] = array_merge(
            $this->getQueue($queue), $value
        );
    }

    /**
     * Reset a queue.
     *
     * @param string $queue
     */
    public function resetQueue($queue)
    {
        $this->queues[$queue] = [];
    }

    /**
     * Remove an attached file.
     *
     * @param array $files
     */
    public function remove($files)
    {
        $this->getDisk()->remove($files);
    }

    /**
     * Move an uploaded file to it's intended destination.
     * The file can be an actual uploaded file object or the path to
     * a resized image file on disk.
     *
     * @param string $source
     * @param string $target
     */
    public function move($source, $target)
    {
        $this->getDisk()->move($source, $target);
    }

    /**
     * Generates the url to a file upload.
     *
     * @param string $styleName
     *
     * @return string
     */
    public function url($styleName = '')
    {
        if ($this->isProcessing()) {
            return $this->loadingUrl($styleName);
        }

        if ($this->originalFilename()) {
            if ($path = $this->path($styleName)) {
                return $this->prefix_url . $path;
            }
        }

        return $this->defaultUrl($styleName);
    }

    /**
     * Based on the processing attribute, determine if the
     * attachement is being processed.
     *
     * @return bool
     */
    public function isProcessing()
    {
        // When empty this feature is turned off
        if (empty($this->config('processing_key'))) {
            return false;
        }

        return $this->instance
                ->getAttribute($this->config('processing_key')) == true;
    }

    /**
     * Generates an array of all style urls.
     *
     * @param bool $skip_empty
     * @param bool $include_original
     *
     * @return array|null
     */
    public function toArray($skip_empty = false, $include_original = true)
    {
        // Skip when no media
        if ($skip_empty === true && $this->hasMedia() === false) {
            return null;
        }

        $urls = [];

        foreach ($this->styles as $name => $style) {
            // Skip the original file
            if ($include_original === false
                && $this->config('default_style') === $name
            ) {
                continue;
            }

            $urls[$name] = $this->url($name);
        }

        return $urls;
    }

    /**
     * Determine if object has media.
     *
     * @return bool
     */
    public function hasMedia()
    {
        return ($this->originalFilename() && $this->path());
    }

    /**
     * Generates the filesystem path to an uploaded file.
     *
     * @param string $styleName
     *
     * @return string
     */
    public function path($styleName = '')
    {
        if ($this->originalFilename()) {
            return $this->getInterpolator()->interpolate($this->url, $styleName);
        }

        return '';
    }

    /**
     * Returns the content type of the file as originally assigned to this attachment's model.
     * Lives in the <attachment>_content_type attribute of the model.
     *
     * @return string
     */
    public function contentType()
    {
        return $this->instance->getAttribute("{$this->name}_content_type");
    }

    /**
     * Returns the size of the file as originally assigned to this attachment's model.
     * Lives in the <attachment>_file_size attribute of the model.
     *
     * @return integer
     */
    public function size()
    {
        return $this->instance->getAttribute("{$this->name}_file_size");
    }

    /**
     * Returns the name of the file as originally assigned to this attachment's model.
     * Lives in the <attachment>_file_name attribute of the model.
     *
     * @return string
     */
    public function originalFilename()
    {
        return $this->instance->getAttribute("{$this->name}_file_name");
    }

    /**
     * Process the write queue.
     *
     * @param Model $instance
     *
     * @return void
     */
    public function afterSave($instance)
    {
        $this->instance = $instance;
        $this->save();
    }

    /**
     * Queue up this attachments files for deletion.
     *
     * @param Model $instance
     *
     * @return void
     */
    public function beforeDelete($instance)
    {
        $this->instance = $instance;

        $this->clear();
    }

    /**
     * Process the delete queue.
     *
     * @param Model $instance
     *
     * @return void
     */
    public function afterDelete($instance)
    {
        $this->instance = $instance;

        $this->flushDeletes();
    }

    /**
     * Destroys the attachment.  Has the same effect as previously assigning
     * MEDIASORT_NULL to the attachment and then saving.
     *
     * @param array $stylesToClear
     *
     * @return void
     */
    public function destroy($stylesToClear = [])
    {
        $this->clear($stylesToClear);

        $this->save();
    }

    /**
     * Clears out the attachment.  Has the same effect as previously assigning
     * MEDIASORT_NULL to the attachment.  Does not save the associated model.
     *
     * @param array $stylesToClear
     *
     * @return void
     */
    public function clear($stylesToClear = [])
    {
        if ($stylesToClear) {
            $this->queueSomeForDeletion($stylesToClear);
        }
        else {
            $this->queueAllForDeletion();
        }
    }

    /**
     * Removes the old file upload (if necessary).
     * Saves the new file upload.
     *
     * @return void
     */
    public function save()
    {
        if ($this->config('keep_old_files', false) === false) {
            $this->flushDeletes();
        }

        $this->flushWrites();
    }

    /**
     * Rebuild the images for this attachment.
     *
     * @return void
     */
    public function reprocess()
    {
        if (empty($this->originalFilename())) {
            return;
        }

        foreach ($this->styles as $name => $style) {
            if (empty($file = $this->path($name))) {
                continue;
            }

            $file = $this->getFileManager()->make($file);

            if ($style && $file->isImage()) {
                $file = $this->getResizer()
                    ->resize($file, $style);
            }
            else {
                $file = $file->getRealPath();
            }

            $filePath = $this->path($name);
            $this->move($file, $filePath);
        }
    }

    /**
     * Return the class type of the attachment's underlying
     * model instance.
     *
     * @return string
     */
    public function getInstanceClass()
    {
        return get_class($this->instance);
    }

    /**
     * Process the queued for writes.
     *
     * @return void
     */
    protected function flushWrites()
    {
        foreach ($this->getQueue('write') as $name => $style) {
            if ($style && $this->uploadedFile->isImage()) {
                $file = $this->getResizer()
                    ->resize($this->uploadedFile, $style);
            }
            else {
                $file = $this->uploadedFile->getRealPath();
            }

            // Only move it real
            if ($filePath = $this->path($name)) {
                $this->move($file, $filePath);
            }
        }

        $this->resetQueue('write');
    }

    /**
     * Process the queuedForDeletion que.
     *
     * @return void
     */
    protected function flushDeletes()
    {
        $this->remove($this->getQueue('deletion'));

        $this->resetQueue('deletion');
    }

    /**
     * Generates the default url if no file attachment is present.
     *
     * @param string $styleName
     *
     * @return string
     */
    protected function defaultUrl($styleName = '')
    {
        if ($this->default_url) {
            $url = $this->getInterpolator()->interpolate($this->default_url, $styleName);

            return parse_url($url, PHP_URL_HOST) ? $url : $this->prefix_url . $url;
        }

        return '';
    }

    /**
     * Generates the loading url if no file attachment is present.
     *
     * @param string $styleName
     *
     * @return string
     */
    protected function loadingUrl($styleName = '')
    {
        if ($this->loading_url) {
            $url = $this->getInterpolator()->interpolate($this->loading_url, $styleName);

            return parse_url($url, PHP_URL_HOST) ? $url : $this->prefix_url . $url;
        }

        return '';
    }

    /**
     * Add a subset (filtered via style) of the uploaded files for this attachment
     * to the queuedForDeletion queue.
     *
     * @param array $stylesToClear
     *
     * @return void
     */
    protected function queueSomeForDeletion($stylesToClear)
    {
        $filePaths = array_map(function ($styleToClear) {
            return $this->path($styleToClear);
        }, $stylesToClear);

        $this->setQueue('deletion', $filePaths);
    }

    /**
     * Add all uploaded files (across all image styles) to the queuedForDeletion queue.
     *
     * @return void
     */
    protected function queueAllForDeletion()
    {
        if (empty($this->originalFilename())) {
            return;
        }

        // Remove old files
        if ($this->config('preserve_files', false) === false) {
            foreach ($this->styles as $name => $style) {
                $this->setQueue('deletion', $this->path($name));
            }
        }

        // Set model attributes
        $this->instanceWrite('file_name', null);
        $this->instanceWrite('file_size', null);
        $this->instanceWrite('content_type', null);
        $this->instanceWrite('updated_at', null);
    }

    /**
     * Set an attachment attribute on the underlying model instance.
     *
     * @param string $property
     * @param mixed  $value
     *
     * @return void
     */
    protected function instanceWrite($property, $value)
    {
        $fieldName = "{$this->name}_{$property}";

        if ($property === 'file_name') {
            $this->instance->setAttribute($fieldName, $value);
        }
        else {
            if (in_array($fieldName, $this->instance->getFillable())) {
                $this->instance->setAttribute($fieldName, $value);
            }
        }
    }

    /**
     * Get configuration value.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function config($key, $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * Get the file manager instance.
     *
     * @return FileManager
     */
    public function getFileManager()
    {
        if ($this->fileManagerInstance === null) {
            $this->fileManagerInstance = new FileManager($this);
        }

        return $this->fileManagerInstance;
    }

    /**
     * Get the interpolator instance.
     *
     * @return Interpolator
     */
    public function getInterpolator()
    {
        return new Interpolator($this);
    }

    /**
     * Get the resizer instance.
     *
     * @return Resizer
     */
    public function getResizer()
    {
        $options = [
            'image_quality' => $this->config('image_quality'),
            'auto_orient' => $this->config('auto_orient'),
            'color_palette' => $this->config('color_palette'),
        ];

        return new Resizer(
            $this->config('image_processor'), $options
        );
    }

    /**
     * Handle the dynamic setting of attachment options.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function __set($key, $value)
    {
        Arr::set($this->config, $key, $value);
    }

    /**
     * Handle the dynamic retrieval of attachment options.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->config($key);
    }
}
