<?php

namespace Torann\MediaSort\Services;

use Torann\MediaSort\Exceptions\InvalidClassException;

class ImageRefreshService
{
    /**
     * Attempt to refresh the defined attachments on a particular model.
     *
     * @param  string $class
     * @param  array  $media
     *
     * @throws \Torann\MediaSort\Exceptions\InvalidClassException
     */
    public function refresh($class, $media)
    {
        if (!method_exists($class, 'hasMediaFile')) {
            throw new InvalidClassException("Invalid class: the $class class is not currently using MediaSort.", 1);
        }

        // Get model
        $models = app($class)->all();

        if ($media)
        {
            $media = explode(',', str_replace(', ', ',', $media));
            $this->processSomeFiles($models, $media);

            return;
        }

        $this->processAllFiles($models);
    }

    /**
     * Process a only a specified subset of MediaSort files.
     *
     * @param  array $media
     * @return void
     */
    protected function processSomeFiles($models, $media)
    {
        foreach ($models as $model)
        {
            foreach ($model->getMediaFiles() as $file)
            {
                if (in_array($file->name, $media)) {
                    $file->reprocess();
                }
            }
        }
    }

    /**
     * Process all MediaSort attachments defined on a class.
     *
     * @return void
     */
    protected function processAllFiles($models)
    {
        foreach ($models as $model) {
            foreach ($model->getMediaFiles() as $file) {
                $file->reprocess();
            }
        }
    }
}