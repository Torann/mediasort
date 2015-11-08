<?php

namespace Torann\MediaSort\ORM;

interface MediaSortInterface
{
    /**
     * Accessor method for the $media property.
     *
     * @return array
     */
    public function getMediaFiles();

    /**
     * Add a new file attachment type to the list of available attachments.
     * This function acts as a quasi constructor for this trait.
     *
     * @param string $name
     * @param array  $options
     */
    public function hasMediaFile($name, array $options = []);

    /**
     * Handle the dynamic retrieval of attachment objects.
     *
     * @param  string $key
     * @return mixed
     */
    public function getAttribute($key);

    /**
     * Handle the dynamic setting of attachment objects.
     *
     * @param  string $key
     * @param  mixed  $value
     */
    public function setAttribute($key, $value);
}
