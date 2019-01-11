<?php

namespace Torann\MediaSort;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Torann\MediaSort\File\Image\Resizer;
use Torann\MediaSort\Exceptions\InvalidClassException;

class Manager
{
    // Queued states
    const QUEUE_DONE = 1;
    const QUEUE_WAITING = 2;
    const QUEUE_WORKING = 3;

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
    protected $uploaded_file;

    /**
     * An FileManager instance for converting file input formats (Symfony uploaded file object
     * arrays, string, etc) into an instance of \Torann\MediaSort\UploadedFile.
     *
     * @var \Torann\MediaSort\FileManager
     */
    protected $file_manager_instance;

    /**
     * An instance of the disk.
     *
     * @var \Torann\MediaSort\Disks\AbstractDisk.
     */
    protected $disk_instance;

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
     * @param mixed $file
     *
     * @return void
     */
    public function setUploadedFile($file)
    {
        // If set, this just clears the image.
        if ($file == MEDIASORT_NULL) {
            $this->clear();

            return;
        }

        // Determine if this attachment should be processed
        // now or later using queued job.
        if ($this->isQueueable()) {
            $this->queueUploadedFile($file);
        }

        // Standard upload, nothing fancy here.
        else {
            $this->addUploadedFile($file);
        }
    }

    /**
     * Mutator method for the uploadedFile property.
     *
     * @param mixed $file
     *
     * @return void
     */
    protected function queueUploadedFile($file)
    {
        // Get the real path of the file
        $file = $this->getFileManager()
            ->make($file)
            ->getRealPath();

        // Create the unique directory name and file to save into
        $file_target = $this->joinPaths(
            str_replace('.', '-', uniqid(rand(), true)),
            basename($file)
        );

        // Parse the queue path
        $queue_path = $this->getInterpolator()->interpolate(
            $this->config('queue_path')
        );

        // Determine if the path is locale and just simple move it,
        // otherwise use the disk driver to move the attachment.
        if ($local_path = realpath($queue_path)) {
            $target = $this->joinPaths($local_path, $file_target);

            // Ensure the target directory exists
            if (is_dir(dirname($target)) === false) {
                mkdir(dirname($target), 0777, true);
            }

            // Move the file
            rename($file, $target);
        }
        else {
            $this->move(
                $file, $this->joinPaths($queue_path, $file_target)
            );
        }

        // Save the information for later
        $this->instanceWrite('queue_state', self::QUEUE_WAITING);
        $this->instanceWrite('queued_file', $file_target);
    }

    /**
     * Mutator method for the uploadedFile property.
     *
     * @param mixed $file
     *
     * @return void
     */
    protected function addUploadedFile($file)
    {
        $this->clear();

        $this->uploaded_file = $this->getFileManager()->make($file);

        // Get the original values
        $filename = $this->uploaded_file->getClientOriginalName();
        $content_type = $this->uploaded_file->getMimeType();

        // Set model values
        $this->instanceWrite('file_name', $filename);
        $this->instanceWrite('file_size', $this->uploaded_file->getSize());
        $this->instanceWrite('content_type', $content_type);
        $this->instanceWrite('updated_at', date('Y-m-d H:i:s'));
        $this->instanceWrite('queued_file', null);

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
        if ($this->disk_instance === null) {
            // Create disk class
            $class = "\\Torann\\MediaSort\\Disks\\" . ucfirst($this->config('disk'));

            // Verify disk
            if (class_exists($class) === false) {
                throw new InvalidClassException("Disk type \"{$class}\" not found.");
            }

            // Create disk instance
            $this->disk_instance = Container::getInstance()->makeWith($class, ['media' => $this]);
        }

        return $this->disk_instance;
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
        $this->config['disk'] = Arr::get(
            $this->config, 'disk', config('filesystems.default', 'local')
        );
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
     *
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
     * @param string $style
     *
     * @return string
     */
    public function url($style = '')
    {
        if ($this->isQueued()) {
            return $this->loadingUrl($style);
        }

        if ($this->getAttribute('filename')) {
            if ($path = $this->path($style)) {
                return $this->config('prefix_url') . $path;
            }
        }

        return $this->defaultUrl($style);
    }

    /**
     * Determine if the attachement is queueable.
     *
     * @return bool
     */
    public function isQueueable()
    {
        return $this->config('queueable', false) == true;
    }

    /**
     * Determine if the attachment is beng processed.
     *
     * @return bool
     */
    public function isQueued()
    {
        if ($this->isQueueable()) {
            return $this->getAttribute('queue_state') != self::QUEUE_DONE;
        }

        return false;
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
        return ($this->getAttribute('filename') && $this->path());
    }

    /**
     * Generates the filesystem path to an uploaded file.
     *
     * @param string $style
     *
     * @return string
     */
    public function path($style = '')
    {
        if ($this->getAttribute('filename')) {
            return $this->getInterpolator()->interpolate($this->url, $style);
        }

        return '';
    }

    /**
     * Return the attachment attribute value.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute($key)
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

        return $this->getInstance()
            ->getAttribute("{$this->name}_{$key}");
    }

    /**
     * Returns the content type of the file as originally assigned to this attachment's model.
     * Lives in the <attachment>_content_type attribute of the model.
     *
     * @return string
     *
     * @deprecated Use getAttribute() instead
     */
    public function contentType()
    {
        return $this->getAttribute('content_type');
    }

    /**
     * Returns the size of the file as originally assigned to this attachment's model.
     * Lives in the <attachment>_file_size attribute of the model.
     *
     * @return integer
     *
     * @deprecated Use getAttribute() instead
     */
    public function size()
    {
        return $this->getAttribute('file_size');
    }

    /**
     * Returns the size of the file as originally assigned to this attachment's model.
     * Lives in the <attachment>_file_size attribute of the model.
     *
     * @return integer
     *
     * @deprecated Use getAttribute() instead
     */
    public function queueState()
    {
        return $this->getAttribute('queue_state');
    }

    /**
     * Returns the name of the file as originally assigned to this attachment's model.
     * Lives in the <attachment>_file_name attribute of the model.
     *
     * @return string
     *
     * @deprecated Use getAttribute() instead
     */
    public function originalFilename()
    {
        return $this->getAttribute('file_name');
    }

    /**
     * Get the queued file path.
     *
     * @return string
     */
    public function getQueuedFilePath()
    {
        return $this->getInterpolator()->interpolate(
            $this->joinPaths(
                $this->config('queue_path'),
                $this->getAttribute('queued_file')
            )
        );
    }

    /**
     * Process the write queue.
     *
     * @param Model $instance
     *
     * @return void
     */
    public function beforeSave($instance)
    {
        $this->setInstance($instance);

        $this->save();
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
        $this->setInstance($instance);

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
        $this->setInstance($instance);

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
        $this->setInstance($instance);

        $this->flushDeletes();
    }

    /**
     * Destroys the attachment.  Has the same effect as previously assigning
     * MEDIASORT_NULL to the attachment and then saving.
     *
     * @param array $styles
     *
     * @return void
     */
    public function destroy($styles = [])
    {
        $this->clear($styles);

        $this->save();
    }

    /**
     * Clears out the attachment.  Has the same effect as previously assigning
     * MEDIASORT_NULL to the attachment.  Does not save the associated model.
     *
     * @param array $styles
     *
     * @return void
     */
    public function clear(array $styles = [])
    {
        if ($styles) {
            $this->queueSomeForDeletion($styles);
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
        if (empty($this->getAttribute('filename'))) {
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
     * Trigger queued files for processing.
     *
     * @param Model  $instance
     * @param string $path
     * @param bool   $cleanup
     *
     * @return void
     */
    public function processQueue(Model $instance, $path = null, bool $cleanup = true)
    {
        $this->setInstance($instance);

        // Determine the path to use
        $path = $path ?: $this->getQueuedFilePath();

        // Set the file for processing
        $this->addUploadedFile($path);

        // Start processing the file
        $this->save();

        // Save all updated model attributes
        $this->getInstance()->save();

        // TODO: also remove non-local files
        // Remove queued file locally
        if ($cleanup === true && realpath($path) !== false) {
            @unlink($path);
            @rmdir(dirname($path));
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
        return get_class($this->getInstance());
    }

    /**
     * Process the queued for writes.
     *
     * @return void
     */
    protected function flushWrites()
    {
        // Skip this if there is no queued write items
        if (count($this->getQueue('write')) === 0) {
            return;
        }

        // Update the state of the queued attachment
        $this->updateQueueState(self::QUEUE_WORKING);

        foreach ($this->getQueue('write') as $name => $style) {
            if ($style && $this->uploaded_file->isImage()) {
                $file = $this->getResizer()
                    ->resize($this->uploaded_file, $style);
            }
            else {
                $file = $this->uploaded_file->getRealPath();
            }

            // Only move it real
            if ($filePath = $this->path($name)) {
                $this->move($file, $filePath);
            }
        }

        $this->resetQueue('write');

        // Update the state of the queued attachment
        $this->updateQueueState(self::QUEUE_DONE);
    }

    /**
     * Get queue state text.
     *
     * @return string
     */
    public function getQueuedStateText()
    {
        switch ((int)$this->getAttribute('queue_state')) {
            case self::QUEUE_DONE:
                return 'done';
            case self::QUEUE_WAITING:
                return 'waiting';
            case self::QUEUE_WORKING:
                return 'working';
            default:
                return 'unknown';
        }
    }

    /**
     * Use the model's connecting and table to quickly update the queue state and
     * bypass the save event in the model to prevent an event loop.
     *
     * @param int $state
     *
     * @return void
     */
    public function updateQueueState(int $state)
    {
        if ($this->isQueueable()) {
            $this->getInstance()
                ->getConnection()
                ->table($this->getInstance()->getTable())
                ->update([
                    "{$this->name}_queue_state" => $state,
                ]);
        }
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
     * @param string $style
     *
     * @return string
     */
    protected function defaultUrl($style = '')
    {
        if ($this->config('default_url')) {
            $url = $this->getInterpolator()->interpolate($this->config('default_url'), $style);

            return parse_url($url, PHP_URL_HOST) ? $url : $this->config('prefix_url') . $url;
        }

        return '';
    }

    /**
     * Generates the loading url if no file attachment is present.
     *
     * @param string $style
     *
     * @return string
     */
    protected function loadingUrl($style = '')
    {
        if ($this->config('loading_url')) {
            $url = $this->getInterpolator()
                ->interpolate($this->config('loading_url'), $style);

            return parse_url($url, PHP_URL_HOST) ? $url : $this->config('prefix_url') . $url;
        }

        return '';
    }

    /**
     * Add a subset (filtered via style) of the uploaded files for this attachment
     * to the queuedForDeletion queue.
     *
     * @param array $styles
     *
     * @return void
     */
    protected function queueSomeForDeletion($styles)
    {
        $filePaths = array_map(function ($style) {
            return $this->path($style);
        }, $styles);

        $this->setQueue('deletion', $filePaths);
    }

    /**
     * Add all uploaded files (across all image styles) to the queuedForDeletion queue.
     *
     * @return void
     */
    protected function queueAllForDeletion()
    {
        if (empty($this->getAttribute('filename'))) {
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
        $this->instanceWrite('queue_state', self::QUEUE_DONE);
        $this->instanceWrite('queued_file', null);
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
        $field = "{$this->name}_{$property}";

        // This is not fillable as it is the one required attribute
        if ($property === 'file_name') {
            $this->getInstance()->setAttribute($field, $value);
        }

        // Queue state is optional and outside of the fillable
        else if (preg_match('/^queue(d?)_/', $property)) {
            if ($this->isQueueable()) {
                $this->getInstance()->setAttribute($field, $value);
            }
        }

        // All other attributes must be fillable to have their values set
        else {
            if (in_array($field, $this->getInstance()->getFillable())) {
                $this->getInstance()->setAttribute($field, $value);
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
        if ($this->file_manager_instance === null) {
            $this->file_manager_instance = new FileManager($this);
        }

        return $this->file_manager_instance;
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
     * Transform an array into path.
     *
     * @param mixed $args
     *
     * @return string
     */
    protected function joinPaths(...$args)
    {
        return rtrim(preg_replace('/\/{2,}/', '/', join('/', $args)), '/');
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
