<?php namespace Torann\MediaSort\Connections;

use Config;
use GrahamCampbell\Flysystem\Facades\Flysystem;

abstract class AbstractConnection {

    /**
     * The current media object being processed.
     *
     * @var \Torann\MediaSort\Manager
     */
    public $media;

    /**
     * Flysystem configurations.
     *
     * @var array
     */
    public $config;

    /**
     * Connection type local.
     *
     * @var bool
     */
    protected $local = false;

    /**
     * Constructor method
     *
     * @param \Torann\MediaSort\Manager $media
     */
    function __construct($media)
    {
        $this->media  = $media;
        $this->config = Config::get('graham-campbell/flysystem::connections.' . $this->media->connection);
    }

    /**
	 * Return the url for a file upload.
	 *
	 * @param  string $styleName
	 * @return string
	 */
    abstract public function url($styleName);

    /**
     * Is connection type local.
     *
     * @return bool
     */
    public function isLocal()
    {
        return $this->local;
    }

    /**
     * Remove an attached file.
     *
     * @param array $filePaths
     */
    public function remove($filePaths)
    {
        foreach ($filePaths as $filePath)
        {
            try {
                Flysystem::delete($filePath);
            }
            catch (\League\Flysystem\FileNotFoundException $e) {
                // Ignore
            }
        }
    }

    /**
     * Move an uploaded file to it's intended destination.
     *
     * @param  string $file
     * @param  string $filePath
     * @return void
     */
    public function move($file, $filePath)
    {
        // Remove
        $this->remove([$filePath]);

        // Open stream
        $stream = fopen($file, 'r+');

        // Write to file
        Flysystem::writeStream($filePath, $stream);

        // Close it!
        if(is_resource($stream)) {
            fclose($stream);
        }
    }
}