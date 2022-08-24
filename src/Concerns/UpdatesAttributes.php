<?php

namespace Torann\MediaSort\Concerns;

use Illuminate\Support\Arr;
use Torann\MediaSort\File\FileManager;
use Torann\MediaSort\File\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Torann\MediaSort\File\Image\Resizer;

trait UpdatesAttributes
{
    protected UploadedFile $uploaded_file;
    protected array $tasks = [];

    /**
     * Setup up the tasks for saving.
     *
     * @param mixed $file
     *
     * @return void
     * @throws \Torann\MediaSort\Exceptions\FileException
     */
    public function beforeSave(mixed $file)
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
        } else {
            $this->addUploadedFile($file);
        }
    }

    /**
     * Process the saving tasks.
     *
     * @return void
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     */
    public function afterSave()
    {
        $this->save();
    }

    /**
     * Setup up the tasks for deletion.
     *
     * @return void
     */
    public function beforeDelete()
    {
        $this->clear();
    }

    /**
     * Process the "delete" tasks.
     *
     * @return void
     */
    public function afterDelete()
    {
        $this->flushDeletes();
    }

    /**
     * Destroys the attachment.  Has the same effect as previously assigning
     * MEDIASORT_NULL to the attachment and then saving.
     *
     * @param array $styles
     *
     * @return void
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     */
    public function destroy(array $styles = [])
    {
        $this->clear($styles);

        $this->save();
    }

    /**
     * Mutator method for the uploaded file property.
     *
     * @param mixed $file
     *
     * @return void
     * @throws \Torann\MediaSort\Exceptions\FileException
     */
    public function setUploadedFile(mixed $file)
    {
        $this->uploaded_file = $this->getUploadedFile($file);
    }

    /**
     * Get the requested task.
     *
     * @param string $task
     *
     * @return array
     */
    protected function getTask(string $task): array
    {
        return (array) Arr::get($this->tasks, $task, []);
    }

    /**
     * Set an item in the tasks list.
     *
     * @param string       $task
     * @param string|array $value
     *
     * @return void
     */
    protected function setTask(string $task, string|array $value)
    {
        // Ensure the value is an array
        if (is_array($value) === false) {
            $value = [$value];
        }

        $this->tasks[$task] = array_merge(
            $this->getTask($task), $value
        );
    }

    /**
     * Reset the provided task.
     *
     * @param string $task
     *
     * @return void
     */
    protected function resetTask(string $task)
    {
        $this->tasks[$task] = [];
    }

    /**
     * Mutator method for the uploadedFile property.
     *
     * @param mixed $file
     *
     * @return void
     * @throws \Torann\MediaSort\Exceptions\FileException
     */
    protected function queueUploadedFile(mixed $file)
    {
        $this->setUploadedFile($file);

        // Create the unique directory name and file to save into
        $file_target = $this->joinPaths(
            str_replace('.', '-', uniqid(rand(), true)),
            $this->uploaded_file->getClientOriginalName()
        );

        // Parse the queue path
        $queue_path = realpath($this->getInterpolator()->interpolate(
            $this->config('queue_path')
        ));

        // Determine if the path is locale and just simple move it,
        // otherwise use the disk driver to move the attachment.
        if ($local_path = realpath($queue_path)) {
            $target = $this->joinPaths($local_path, $file_target);

            // Ensure the target directory exists
            if (is_dir(dirname($target)) === false) {
                @mkdir(dirname($target), 0777, true);
            }

            // Move the file
            @rename($this->uploaded_file->getRealPath(), $target);
        }

        // Save the information for later
        $this->modelWrite('queue_state', self::QUEUE_WAITING);
        $this->modelWrite('queued_at', date('Y-m-d H:i:s'));
        $this->modelWrite('queued_file', $file_target);
    }

    /**
     * Mutator method for the uploadedFile property.
     *
     * @param mixed $file
     *
     * @return void
     * @throws \Torann\MediaSort\Exceptions\FileException
     */
    protected function addUploadedFile(mixed $file)
    {
        $this->clear();

        $this->setUploadedFile($file);

        // Get the original values
        $filename = $this->uploaded_file->getClientOriginalName();
        $content_type = $this->uploaded_file->getMimeType();

        // Set model attributes
        $this->modelWrite('file_name', $filename);
        $this->modelWrite('file_size', $this->uploaded_file->getSize());
        $this->modelWrite('content_type', $content_type);
        $this->modelWrite('updated_at', date('Y-m-d H:i:s'));

        // Set model queue attributes
        $this->modelWrite('queued_at', null);
        $this->modelWrite('queued_file', null);

        // Queue all styles for writing
        $this->setTask('write', $this->styles);
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
        if (empty($styles) === false) {
            $this->taskSomeForDeletion($styles);
        } else {
            $this->taskAllForDeletion();
        }
    }

    /**
     * Removes the old file upload (if necessary).
     * Saves the new file upload.
     *
     * @return void
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
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
     * @throws \Torann\MediaSort\Exceptions\FileException
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
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

            $file = $this->getUploadedFile($file);

            if ($style && $file->isImage()) {
                $file = $this->getResizer()->resize($file, $style);
            } else {
                $file = $file->getRealPath();
            }

            $this->move($file, $this->path($name));
        }
    }

    /**
     * Trigger queued files for processing.
     *
     * @param Model       $model
     * @param string|null $path
     * @param bool        $cleanup
     *
     * @return void
     * @throws \Torann\MediaSort\Exceptions\FileException
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function processQueue(Model $model, string $path = null, bool $cleanup = true)
    {
        $this->setModel($model);

        // Determine the path to use
        $path = $path ?: $this->getQueuedFilePath();

        // Set the file for processing
        $this->addUploadedFile($path);

        // Start processing the file
        $this->save();

        // Save all updated model attributes
        $this->getModel()->save();

        // Remove queued file locally
        if ($cleanup === true && realpath($path) !== false) {
            @unlink($path);
            @rmdir(dirname($path));
        }
    }

    /**
     * Get the resizer instance.
     *
     * @return Resizer
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     */
    public function getResizer(): Resizer
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
     * Process the tasks for writes.
     *
     * @return void
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function flushWrites()
    {
        // Skip this if there aren't any write tasks
        if (count($this->getTask('write')) === 0) {
            return;
        }

        // Update the state of the queued attachment
        $this->updateQueueState(self::QUEUE_WORKING);

        foreach ($this->getTask('write') as $name => $style) {
            if ($style && $this->uploaded_file->isImage()) {
                $file = $this->getResizer()->resize($this->uploaded_file, $style);
            } else {
                $file = $this->uploaded_file->getRealPath();
            }

            // Only move it real
            if ($file_path = $this->path($name)) {
                $this->move($file, $file_path);
            }
        }

        $this->resetTask('write');

        // Update the state of the queued attachment
        $this->updateQueueState(self::QUEUE_DONE);
    }

    /**
     * Process the deletion tasks.
     *
     * @return void
     */
    protected function flushDeletes()
    {
        try {
            $this->remove($this->getTask('deletion'));
        } catch (\Exception $e) {
            //
        }

        $this->resetTask('deletion');
    }

    /**
     * Add a subset (filtered via style) of the uploaded files for this attachment
     * to the deletion task.
     *
     * @param array $styles
     *
     * @return void
     */
    protected function taskSomeForDeletion(array $styles)
    {
        $file_paths = array_map(function ($style) {
            return $this->path($style);
        }, $styles);

        $this->setTask('deletion', $file_paths);
    }

    /**
     * Add all uploaded files (across all image styles) to the deletion task.
     *
     * @return void
     */
    protected function taskAllForDeletion()
    {
        if (empty($this->getAttribute('filename'))) {
            return;
        }

        // Remove old files
        if ($this->config('preserve_files', false) === false) {
            foreach ($this->styles as $name => $style) {
                $this->setTask('deletion', $this->path($name));
            }
        }

        // Set model attributes
        $this->modelWrite('file_name', null);
        $this->modelWrite('file_size', null);
        $this->modelWrite('content_type', null);
        $this->modelWrite('updated_at', null);

        // Set model queue attributes
        $this->modelWrite('queue_state', self::QUEUE_DONE);
        $this->modelWrite('queued_at', null);
        $this->modelWrite('queued_file', null);
    }

    /**
     * Set an attachment attribute on the underlying model instance.
     *
     * @param string $property
     * @param mixed  $value
     *
     * @return void
     */
    protected function modelWrite(string $property, mixed $value)
    {
        $field = "{$this->name}_{$property}";

        // This is not fillable as it is the one required attribute
        if ($property === 'file_name') {
            $this->getModel()->setAttribute($field, $value);
        }

        // Queue attributes are optional and outside the fillable,
        // because of this they are always set.
        elseif (preg_match('/^queue(d?)_/', $property)) {
            if ($this->isQueueable()) {
                $this->getModel()->setAttribute($field, $value);
            }
        }

        // All other attributes must be in the model's fillable array
        // to have their values set.
        else {
            if (in_array($field, $this->getModel()->getFillable())) {
                $this->getModel()->setAttribute($field, $value);
            }
        }
    }

    /**
     * Get the file manager instance.
     *
     * @param mixed $file
     *
     * @return UploadedFile
     * @throws \Torann\MediaSort\Exceptions\FileException
     */
    protected function getUploadedFile(mixed $file): UploadedFile
    {
        return (new FileManager())->make($file);
    }
}
