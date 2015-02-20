<?php namespace Torann\MediaSort\Connections;

use GrahamCampbell\Flysystem\Facades\Flysystem;

class Awss3 extends AbstractConnection {

    /**
     * Connection type local.
     *
     * @var bool
     */
    protected $local = false;

	/**
	 * Return the url for a file upload.
	 *
	 * @param  string $styleName
	 * @return string
	 */
	public function url($styleName)
	{
        return Flysystem::getAdapter()->getClient()->getObjectUrl(
            $this->config['bucket'],
            $this->media->path($styleName)
        );
	}

    /**
     * Return the url as the path of a file upload.
     *
     * @param  string $styleName
     * @return string
     */
    public function path($styleName)
    {
        return $this->url($styleName);
    }
}
