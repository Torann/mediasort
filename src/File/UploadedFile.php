<?php

namespace Torann\MediaSort\File;

class UploadedFile extends \Symfony\Component\HttpFoundation\File\UploadedFile
{
    /**
     * An array of key value pairs for valid image extensions and their
     * associated MIME types.
     */
    protected array $image_mimes = [
        'bmp' => 'image/bmp',
        'gif' => 'image/gif',
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpe' => ['image/jpeg', 'image/pjpeg'],
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /**
     * Utility method for detecing whether a given file upload is an image.
     *
     * @return bool
     */
    public function isImage(): bool
    {
        // The $image_mimes property contains an array of file extensions and
        // their associated MIME types. We will loop through them and look for
        // the MIME type of the current UploadedFile.
        if ($mime = $this->getMimeType()) {
            foreach ($this->image_mimes as $image_mime) {
                if (in_array($mime, (array) $image_mime)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns locale independent base name of the given path.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getName(string $name): string
    {
        $name = parent::getName($name);

        // This fixes any URL encoded filename and sanitize it
        $name = strtolower(urldecode($name));

        // Replace spaces with a dash
        $name = preg_replace('!\s+!', '-', $name);

        // Remove odd characters
        return preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
    }
}
