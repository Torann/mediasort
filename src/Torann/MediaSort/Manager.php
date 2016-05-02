<?php

namespace Torann\MediaSort;

use Illuminate\Database\Eloquent\Model;
use Torann\MediaSort\File\Image\Resizer;
use Illuminate\Filesystem\FilesystemManager;
use Torann\MediaSort\Exceptions\InvalidClassException;

class Manager
{
    /**
     * The model the attachment belongs to.
     *
     * @var string
     */
    protected $instance;

    /**
     * An instance of the configuration class.
     *
     * @var \Torann\MediaSort\Config
     */
    protected $config;

    /**
     * An instance of the interpolator class for processing interpolations.
     *
     * @var \Torann\MediaSort\Interpolator
     */
    protected $interpolator;

    /**
     * The uploaded file object for the attachment.
     *
     * @var \Torann\MediaSort\File\UploadedFile
     */
    protected $uploadedFile;

    /**
     * An instance of the resizer library that's being used for image processing.
     *
     * @var \Torann\MediaSort\File\Image\Resizer
     */
    protected $resizer;

    /**
     * An FileManager instance for converting file input formats (Symfony uploaded file object
     * arrays, string, etc) into an instance of \Torann\MediaSort\UploadedFile.
     *
     * @var \Torann\MediaSort\FileManager
     */
    protected $fileManager;

    /**
     * An instance of the disk.
     *
     * @var \Torann\MediaSort\Disks\AbstractDisk.
     */
    protected $disk;

    /**
     * The uploaded/resized files that have been queued up for deletion.
     *
     * @var array
     */
    protected $queuedForDeletion = [];

    /**
     * The uploaded/re-sized files that have been queued up for deletion.
     *
     * @var array
     */
    protected $queuedForWrite = [];

    /**
     * Constructor method
     *
     * @param Config            $config
     * @param FilesystemManager $filesystem
     */
    function __construct(Config $config, FilesystemManager $filesystem)
    {
        $this->config = $config;
        $this->resizer = new Resizer($this->config->image_processor, $this->config->image_quality);
        $this->fileManager = new FileManager($this);
        $this->interpolator = new Interpolator($this);

        // Set disk
        $this->setDisk($this->config->disk, $filesystem);
    }

    /**
     * Handle the dynamic setting of attachment options.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value)
    {
        $this->config->$name = $value;
    }

    /**
     * Handle the dynamic retrieval of attachment options.
     * Style options will be converted into a php stcClass.
     *
     * @param  string $optionName
     *
     * @return mixed
     */
    public function __get($optionName)
    {
        return $this->config->$optionName;
    }

    /**
     * Mutator method for the uploadedFile property.
     *
     * @param  mixed  $uploadedFile
     * @param  string $styleName
     *
     * @return void
     */
    public function setUploadedFile($uploadedFile, $styleName)
    {
        $this->clear();

        if ($uploadedFile == MEDIASORT_NULL) {
            return;
        }

        $this->uploadedFile = $this->fileManager->make($uploadedFile);

        $this->instanceWrite('file_name', $this->uploadedFile->getClientOriginalName());
        $this->instanceWrite('file_size', $this->uploadedFile->getClientSize());
        $this->instanceWrite('content_type', $this->uploadedFile->getMimeType());
        $this->instanceWrite('updated_at', date('Y-m-d H:i:s'));

        $this->queueAllForWrite();
    }

    /**
     * Set disk property.
     *
     * @param  string            $diskName
     * @param  FilesystemManager $filesystem
     *
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     */
    public function setDisk($diskName, FilesystemManager $filesystem)
    {
        $diskName = ucfirst($diskName);
        $class = "\\Torann\\MediaSort\\Disks\\{$diskName}";

        if (!class_exists($class)) {
            throw new InvalidClassException("Disk type \"{$diskName}\" not found.");
        }

        $this->disk = new $class($this, $filesystem);
    }

    /**
     * Accessor method for the disk property.
     *
     * @return \Torann\MediaSort\Disks\AbstractDisk
     */
    public function getDisk()
    {
        return $this->disk;
    }

    /**
     * Accessor method for the interpolator property.
     *
     * @return \Torann\MediaSort\Interpolator
     */
    public function getInterpolator()
    {
        return $this->interpolator;
    }

    /**
     * Mutator method for the instance property.
     * This provides a mechanism for the attachment to access properties of the
     * corresponding model instance it's attached to.
     *
     * @param  Model $instance
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
     * @param  \Torann\MediaSort\Config $config
     *
     * @return void
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Accessor method for the QueuedForDeletion property.
     *
     * @return array
     */
    public function getQueuedForDeletion()
    {
        return $this->queuedForDeletion;
    }

    /**
     * Mutator method for the QueuedForDeletion property.
     *
     * @param array $array
     */
    public function setQueuedForDeletion($array)
    {
        $this->queuedForDeletion = $array;
    }

    /**
     * Remove an attached file.
     *
     * @param array $files
     */
    public function remove($files)
    {
        return $this->disk->remove($files);
    }

    /**
     * Move an uploaded file to it's intended destination.
     * The file can be an actual uploaded file object or the path to
     * a resized image file on disk.
     *
     * @param  string $source
     * @param  string $target
     */
    public function move($source, $target)
    {
        return $this->disk->move($source, $target);
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
        if ($this->originalFilename()) {
            if ($path = $this->path($styleName)) {
                return $this->prefix_url . $path;
            }
        }

        return $this->defaultUrl($styleName);
    }

    /**
     * Generates an array of all style urls.
     *
     * @param bool $skipEmpty
     *
     * @return array
     */
    public function toArray($skipEmpty = false)
    {
        // Skip when no media
        if ($skipEmpty === true && $this->hasMedia() === false) {
            return null;
        }

        $urls = [];

        foreach ($this->styles as $style) {
            $urls[$style->name] = $this->url($style->name);
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
            return $this->interpolator->interpolate($this->url, $styleName);
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
     * @param  Model $instance
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
     * @param  Model $instance
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
     * @param  Model $instance
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
     * @param  array $stylesToClear
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
     * @param  array $stylesToClear
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
        if (!$this->keep_old_files) {
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
        if (!$this->originalFilename()) {
            return;
        }

        foreach ($this->styles as $style) {
            if (!$file = $this->path($style->name)) {
                continue;
            }

            $file = $this->fileManager->make($file);

            if ($style->value && $file->isImage()) {
                $file = $this->resizer->resize($file, $style);
            }
            else {
                $file = $file->getRealPath();
            }

            $filePath = $this->path($style->name);
            $this->move($file, $filePath);
        }
    }

    /**
     * Used to manually trigger a processing. Helpful
     * for delayed upload of large files.
     *
     * @param  Model  $instance
     * @param  string $queue_path
     *
     * @return void
     */
    public function processQueue($instance, $queue_path)
    {
        $this->instance = $instance;

        // Get queue file
        $file = $this->interpolator->interpolate("{$queue_path}/:filename");

        $this->uploadedFile = $this->fileManager->make($file);

        $this->queueAllForWrite();

        $this->save();
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
     * Process the queuedForWrite que.
     *
     * @return void
     */
    protected function flushWrites()
    {
        foreach ($this->queuedForWrite as $style) {
            if ($style->value && $this->uploadedFile->isImage()) {
                $file = $this->resizer->resize($this->uploadedFile, $style);
            }
            else {
                $file = $this->uploadedFile->getRealPath();
            }

            // Only move it real
            if ($filePath = $this->path($style->name)) {
                $this->move($file, $filePath);
            }
        }

        $this->queuedForWrite = [];
    }

    /**
     * Process the queuedForDeletion que.
     *
     * @return void
     */
    protected function flushDeletes()
    {
        $this->remove($this->queuedForDeletion);
        $this->queuedForDeletion = [];
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
            $url = $this->interpolator->interpolate($this->default_url, $styleName);

            return parse_url($url, PHP_URL_HOST) ? $url : $this->prefix_url . $url;
        }

        return '';
    }

    /**
     * Fill the queuedForWrite que with all of this attachment's styles.
     *
     * @return void
     */
    protected function queueAllForWrite()
    {
        $this->queuedForWrite = $this->styles;
    }

    /**
     * Add a subset (filtered via style) of the uploaded files for this attachment
     * to the queuedForDeletion queue.
     *
     * @param  array $stylesToClear
     *
     * @return void
     */
    protected function queueSomeForDeletion($stylesToClear)
    {
        $filePaths = array_map(function ($styleToClear) {
            return $this->path($styleToClear);
        }, $stylesToClear);

        $this->queuedForDeletion = array_merge($this->queuedForDeletion, $filePaths);
    }

    /**
     * Add all uploaded files (across all image styles) to the queuedForDeletion queue.
     *
     * @return void
     */
    protected function queueAllForDeletion()
    {
        if (!$this->originalFilename()) {
            return;
        }

        if (!$this->preserve_files) {
            $filePaths = array_map(function ($style) {
                return $this->path($style->name);
            }, $this->styles);

            $this->queuedForDeletion = array_merge($this->queuedForDeletion, $filePaths);
        }

        $this->instanceWrite('file_name', null);
        $this->instanceWrite('file_size', null);
        $this->instanceWrite('content_type', null);
        $this->instanceWrite('updated_at', null);
    }

    /**
     * Set an attachment attribute on the underlying model instance.
     *
     * @param  string $property
     * @param  mixed  $value
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
            if (array_key_exists($fieldName, $this->instance['fillable'])) {
                $this->instance->setAttribute($fieldName, $value);
            }
        }
    }
}
