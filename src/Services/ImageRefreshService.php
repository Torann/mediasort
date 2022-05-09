<?php

namespace Torann\MediaSort\Services;

use Torann\MediaSort\Exceptions\InvalidClassException;

class ImageRefreshService
{
    /**
     * Attempt to refresh the defined attachments on a particular model.
     *
     * @param string $class
     * @param string $media
     *
     * @throws InvalidClassException
     */
    public function refresh(string $class, string $media = '')
    {
        if (method_exists($class, 'hasMediaFile') === false) {
            throw new InvalidClassException("Invalid class: the {$class} class is not currently using MediaSort.", 1);
        }

        // Get model
        $models = app($class)->all();

        if ($media) {
            $this->processSomeFiles(
                $models,
                explode(',', str_replace(', ', ',', $media))
            );
        } else {
            $this->processAllFiles($models);
        }
    }

    /**
     * Process a only a specified subset of MediaSort files.
     *
     * @param array $models
     * @param array $media
     *
     * @return void
     */
    protected function processSomeFiles(array $models, array $media)
    {
        foreach ($models as $model) {
            foreach ($model->getMediaFiles() as $file) {
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
