<?php

namespace Torann\MediaSort\Disks;

use Torann\MediaSort\Manager;
use Illuminate\Filesystem\FilesystemManager;

class Local extends AbstractDisk
{
    /**
     * Constructor method
     *
     * @param Manager           $media
     * @param FilesystemManager $filesystem
     */
    function __construct(Manager $media, FilesystemManager $filesystem)
    {
        parent::__construct($media, $filesystem);

        // Create a new instance of the local driver. Doing this will prevent
        // any setting changes made here from affecting the whole application.
        $this->filesystem = $filesystem->createLocalDriver($this->config);

        // Change the prefix of the local storage
        $this->setPathPrefix();
    }

    /**
     * Set local path prefix from settings.
     *
     * @return void
     */
    protected function setPathPrefix()
    {
        if ($this->media->local_root) {
            // Interpolate path
            $root = $this->media->getInterpolator()
                ->interpolate($this->media->local_root);

            // Set path
            $this->filesystem->getDriver()
                ->getAdapter()
                ->setPathPrefix($root);
        }
    }
}