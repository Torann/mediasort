<?php

namespace Torann\MediaSort\Concerns;

use Illuminate\Container\Container;
use Torann\MediaSort\Exceptions\InvalidClassException;

trait HasDisks
{
    /**
     * An instance of the disk.
     *
     * @var \Torann\MediaSort\Disks\AbstractDisk.
     */
    protected $disk_instance;

    /**
     * Set disk property.
     *
     * @param string $name
     *
     * @return self
     */
    public function setDisk($name)
    {
        $this->__set('disk', $name);

        return $this;
    }

    /**
     * Get disk instance.
     *
     * @return \Torann\MediaSort\Disks\AbstractDisk
     * @throws InvalidClassException
     */
    public function getDisk()
    {
        if ($this->disk_instance === null) {
            // Create disk class
            $class = "\\Torann\\MediaSort\\Disks\\" . ucfirst($this->config('disk'));

            // Verify disk
            if (class_exists($class) === false) {
                throw new InvalidClassException("Disk type \"{$class}\" not found.");
            }

            // Create disk instance
            $this->disk_instance = Container::getInstance()->makeWith($class, ['media' => $this]);
        }

        return $this->disk_instance;
    }

    /**
     * Remove an attached file.
     *
     * @param array $files
     *
     * @throws InvalidClassException
     */
    protected function remove($files)
    {
        $this->getDisk()->remove($files);
    }

    /**
     * Move an uploaded file to it's intended destination. The file can be an
     * actual uploaded file object or the path to a resized image file on disk.
     *
     * @param string $source
     * @param string $target
     *
     * @throws InvalidClassException
     */
    protected function move($source, $target)
    {
        $this->getDisk()->move($source, $target);
    }
}
