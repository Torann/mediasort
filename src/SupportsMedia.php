<?php

namespace Torann\MediaSort;

use Generator;

interface SupportsMedia
{
    /**
     * Accessor method for the media instance property.
     *
     * @return Generator
     * @throws \Exception
     */
    public function getMediaFiles(): Generator;

    /**
     * Add a new file media type to the list of available media.
     * This function acts as a quasi constructor for this trait.
     *
     * @param string $name
     * @param array  $options
     *
     * @return void
     */
    public function hasMediaFile(string $name, array $options = []);

    /**
     * Handle the dynamic retrieval of media items.
     *
     * @param string $key
     *
     * @return mixed
     * @throws \Exception
     */
    public function getAttribute($key);

    /**
     * Handle the dynamic setting of media items.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function setAttribute($key, $value);
}
