<?php

namespace Torann\MediaSort\Disks;

use Exception;
use Torann\MediaSort\Manager;
use Illuminate\Filesystem\FilesystemManager;

abstract class AbstractDisk
{
    /**
     * The current media object being processed.
     *
     * @var Manager
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
     * @param Manager           $media
     * @param FilesystemManager $filesystem
     */
    function __construct(Manager $media, FilesystemManager $filesystem)
    {
        $this->media = $media;
        $this->filesystem = $filesystem;
        $this->config = config("filesystems.disks.{$this->media->disk}");
    }

    /**
     * Remove an attached file.
     *
     * @param array $files
     */
    public function remove($files)
    {
        foreach ($files as $file) {
            try {
                $this->filesystem->delete($file);
            } // Ignore not found exceptions
            catch (Exception $e) {
            }
        }
    }

    /**
     * Move an uploaded file to it's intended target.
     *
     * @param  string $source
     * @param  string $target
     *
     * @return void
     */
    public function move($source, $target)
    {
        // Save file
        $this->filesystem->put(
            $target,
            file_get_contents($source),
            $this->media->visibility
        );
    }
}