<?php

namespace Torann\MediaSort\Disks;

use Torann\MediaSort\Manager;
use Torann\MediaSort\Contracts\Disk;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Contracts\Filesystem\Filesystem;

class Local implements Disk
{
    public Manager $media;
    public array $config = [];
    public Filesystem $filesystem;

    /**
     * @param Manager           $media
     * @param FilesystemManager $filesystem
     */
    public function __construct(Manager $media, FilesystemManager $filesystem)
    {
        $this->media = $media;
        $this->config = config("filesystems.disks.{$this->media->disk}");

        // Set filesystem root
        $this->config['root'] = $this->getLocalRoot($this->config['root'] ?? '');

        // Create a new instance of the local driver. Doing this will prevent
        // any setting changes made here from affecting the whole application.
        $this->filesystem = $filesystem->createLocalDriver($this->config);
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

    /**
     * @param string $default
     *
     * @return string
     */
    private function getLocalRoot(string $default): string
    {
        if ($this->media->local_root) {
            return $this->media
                ->getInterpolator()
                ->interpolate($this->media->local_root);
        }

        return $default;
    }
}
