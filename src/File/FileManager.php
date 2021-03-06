<?php

namespace Torann\MediaSort\File;

use Illuminate\Support\Arr;
use Torann\MediaSort\Exceptions\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class FileManager
{
    /**
     * Build an UploadedFile object using various file input types.
     *
     * @param mixed $file
     *
     * @return UploadedFile
     * @throws FileException
     */
    public function make($file)
    {
        if ($file instanceof SymfonyUploadedFile) {
            return $this->createFromObject($file);
        }

        if (is_array($file)) {
            if (isset($file['base64']) && isset($file['name'])) {
                return $this->createFromBase64($file['name'], $file['base64']);
            } else {
                return $this->createFromArray($file);
            }
        }

        if (array_key_exists('scheme', parse_url($file))) {
            return $this->createFromUrl($file);
        }

        return $this->createFromString($file);
    }

    /**
     * Build a \Torann\MediaSort\File\UploadedFile object from
     * a Symfony\Component\HttpFoundation\File\UploadedFile object.
     *
     * @param SymfonyUploadedFile $file
     *
     * @return UploadedFile
     * @throws FileException
     */
    protected function createFromObject(SymfonyUploadedFile $file)
    {
        $upload_file = new UploadedFile(
            $file->getPathname(),
            $file->getClientOriginalName(),
            $file->getClientMimeType(),
            $file->getError()
        );

        // Throw error if the object is not valid
        if ($upload_file->isValid() === false) {
            throw new FileException(
                $upload_file->getErrorMessage(
                    $upload_file->getError()
                )
            );
        }

        return $upload_file;
    }

    /**
     * Build a Torann\MediaSort\File\UploadedFile object from a
     * base64 encoded image array. Usually from an API request.
     *
     * @param array  $filename
     * @param string $data
     *
     * @return UploadedFile
     */
    protected function createFromBase64($filename, $data)
    {
        // Get temporary destination
        $destination = sys_get_temp_dir() . '/' . str_random(4) . '-' . $filename;

        // Create destination if not already there
        if (is_dir(dirname($destination)) === false) {
            mkdir(dirname($destination), 0755, true);
        }

        // Create temporary file
        file_put_contents($destination, base64_decode($data), 0);

        // Get mime type
        $mime_type = mime_content_type($destination);

        return new UploadedFile($destination, $filename, $mime_type);
    }

    /**
     * Build a Torann\MediaSort\File\UploadedFile object from the
     * raw php $_FILES array date.
     *
     * @param array $file
     *
     * @return UploadedFile
     */
    protected function createFromArray($file)
    {
        return new UploadedFile(
            $file['tmp_name'],
            $file['name'],
            $file['type']
        );
    }

    /**
     * Fetch a remote file using a string URL and convert it into
     * an instance of Torann\MediaSort\File\UploadedFile.
     *
     * @param string $file
     *
     * @return UploadedFile
     */
    protected function createFromUrl($file)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $file,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.121 Safari/537.36',
            CURLOPT_FOLLOWLOCATION => 1,
        ]);

        $raw_file = curl_exec($ch);

        curl_close($ch);

        // Get the mime type of the file
        $size_info = getimagesizefromstring($raw_file);
        $mime = $size_info['mime'];

        // Create a file path for the file by storing it on disk.
        $file_path = @tempnam(sys_get_temp_dir(), 'STP');
        file_put_contents($file_path, $raw_file);

        // Get the original filename
        $name = strtok(pathinfo($file, PATHINFO_BASENAME), '?');

        // Append missing file extension
        if (empty(pathinfo($file, PATHINFO_EXTENSION))) {
            $name = $name . '.' . $this->getExtension($mime);
        }

        // Get the length of the file
        if (function_exists('mb_strlen')) {
            $size = mb_strlen($raw_file, '8bit');
        } else {
            $size = strlen($raw_file);
        }

        return new UploadedFile($file_path, $name, $mime);
    }

    /**
     * Fetch a local file using a string location and convert it into
     * an instance of Torann\MediaSort\File\UploadedFile.
     *
     * @param string $file
     *
     * @return UploadedFile
     */
    protected function createFromString($file)
    {
        return new UploadedFile($file, basename($file));
    }

    /**
     * Get the file extension based on the mime type.
     *
     * @param $mime_type
     *
     * @return string
     */
    protected function getExtension($mime_type)
    {
        return Arr::get([
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/vnd.wap.wbmp' => 'wbmp',
            'image/xbm' => 'xbm',
            'image/x-xbitmap' => 'xbm',
            'image/x-xbm' => 'xbm',
            'image/webp' => 'webp',
            'image/apng' => 'apng',
        ], $mime_type, 'png');
    }
}
