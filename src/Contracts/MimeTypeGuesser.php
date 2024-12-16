<?php

namespace Torann\MediaSort\Contracts;

use Torann\MediaSort\File\UploadedFile;

interface MimeTypeGuesser
{
    /**
     * @param UploadedFile $file
     *
     * @return string
     */
    public function guess(UploadedFile $file): string;
}
