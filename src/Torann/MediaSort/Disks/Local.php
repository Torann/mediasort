<?php

namespace Torann\MediaSort\Disks;

use Storage;

class Local extends AbstractDisk
{
    /*
     * Used to determine if the path
     * has already been updated.
     *
     * @var string
     */
    private $root;

    /**
     * Remove an attached file.
     *
     * @param array $files
     */
    public function remove($files)
    {
        $this->setPathPrefix();

        parent::remove($files);
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
        $this->setPathPrefix();

        parent::move($source, $target);
    }

    /**
     * Set local path prefix from settings.
     *
     * @return void
     */
    protected function setPathPrefix()
    {
        if ($this->media->local_root && !$this->root) {
            // Interpolate path
            $this->root = $this->media->getInterpolator()
                ->interpolate($this->media->local_root);

            // Set path
            Storage::getDriver()
                ->getAdapter()
                ->setPathPrefix($this->root);
        }
    }
}