<?php namespace Torann\MediaSort\Connections;

class Local extends AbstractConnection {

    /**
     * Connection type local.
     *
     * @var bool
     */
    protected $local = true;

	/**
	 * Return the url for a file upload.
	 *
	 * @param  string $styleName
	 * @return string
	 */
	public function url($styleName)
	{
		return asset($this->media->path($styleName));
	}

    /**
     * Return the path (on disk) of a file upload.
     *
     * @param  string $styleName
     * @return string
     */
    public function path($styleName)
    {
        return $this->config['path'].$this->media->path($styleName);
    }
}