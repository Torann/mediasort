<?php

namespace Torann\MediaSort;

class Validator
{
    /**
     * Validate the attachment options for an attachment type.
     * A url is required to have either an {id} or an {id_partition} interpolation.
     *
     * @param  array $options
     * @return void
     */
    public function validateOptions($options)
    {
        $options['storage'] == 'filesystem' ? $this->validateFilesystemOptions($options) : $this->validateS3Options($options);
    }

    /**
     * Validate the attachment options for an attachment type when the storage
     * driver is set to 'filesystem'.
     *
     * @param  array $options
     * @return void
     *
     * @throws Exceptions\InvalidUrlOptionException
     */
    protected function validateFilesystemOptions($options)
    {
        if (strpos($options['url'], '{id}') === false && strpos($options['url'], '{id_partition}') === false
            && strpos($options['url'], '{hash}') === false) {
            throw new Exceptions\InvalidUrlOptionException('Invalid Url: an id, id_partition, or hash interpolation is required.',
                1);
        }
    }

    /**
     * Validate the attachment optioins for an attachment type when the storage
     * driver is set to 's3'.
     *
     * @param  array $options
     * @return void
     *
     * @throws Exceptions\InvalidUrlOptionException
     */
    protected function validateS3Options($options)
    {
        if (!$options['bucket']) {
            throw new Exceptions\InvalidUrlOptionException('Invalid Path: a bucket interpolation is required for s3 storage.',
                1);
        }
    }
}