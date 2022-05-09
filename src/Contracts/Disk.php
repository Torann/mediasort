<?php

namespace Torann\MediaSort\Contracts;

interface Disk
{
    /**
     * Remove an attached file.
     *
     * @param array $files
     *
     * @return void
     */
    public function remove(array $files);

    /**
     * Move an uploaded file to it's intended target.
     *
     * @param string $source
     * @param string $target
     *
     * @return void
     */
    public function move(string $source, string $target);
}
