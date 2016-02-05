<?php

namespace Torann\MediaSort\Disks;

use File;
use Config;
use Storage;
use Exception;

abstract class AbstractDisk
{
    /**
     * The current media object being processed.
     *
     * @var \Torann\MediaSort\Manager
     */
    public $media;

    /**
     * Storage configurations.
     *
     * @var array
     */
    public $config;

    /**
     * Constructor method
     *
     * @param \Torann\MediaSort\Manager $media
     */
    function __construct($media)
    {
        $this->media = $media;
        $this->config = Config::get("filesystems.disks.{$this->media->disk}");
    }

    /**
     * Remove an attached file.
     *
     * @param array $files
     */
    public function remove($files)
    {
        foreach ($files as $file)
        {
            try {
                Storage::delete($file);
            } // Ignore not found exceptions
            catch (Exception $e) {}
        }
    }

    /**
     * Move an uploaded file to it's intended target.
     *
     * @param  string $source
     * @param  string $target
     * @return void
     */
    public function move($source, $target)
    {
        // Save file
        Storage::put(
            $target,
            File::get($source),
            $this->media->visibility
        );
    }
}