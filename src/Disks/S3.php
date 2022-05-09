<?php

namespace Torann\MediaSort\Disks;

use Torann\MediaSort\Manager;
use Torann\MediaSort\Contracts\Disk;
use Illuminate\Filesystem\FilesystemManager;

/**
 * @deprecated Removed in next major release
 */
class S3 implements Disk
{
    public Manager $media;
    public FilesystemManager $filesystem;

    /**
     * Constructor method
     *
     * @param Manager           $media
     * @param FilesystemManager $filesystem
     */
    public function __construct(Manager $media, FilesystemManager $filesystem)
    {
        $this->media = $media;
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritDoc}
     */
    public function remove(array $files)
    {
        foreach ($files as $file) {
            try {
                $this->filesystem->delete($file);
            } catch (\Exception $e) {
                // Ignore not found exceptions
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function move(string $source, string $target)
    {
        $this->filesystem->put(
            $target, file_get_contents($source), $this->media->visibility
        );
    }
}
