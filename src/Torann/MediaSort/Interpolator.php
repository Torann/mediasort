<?php namespace Torann\MediaSort;

class Interpolator {

    /**
	 * Interpolate a string.
	 *
	 * @param  string  $string
	 * @param  Manager $manager
	 * @param  string  $styleName
	 * @return string
	*/
	public function interpolate($string, $manager, $styleName = '')
	{
		foreach ($this->interpolations() as $key => $value)
		{
			if (strpos($string, $key) !== false) {
				$string = preg_replace("/$key\b/", $this->$value($manager, $styleName), $string);
			}
		}

		return $string;
	}

	/**
	 * Returns a sorted list of all interpolations.  This list is currently hard coded
	 * (unlike its paperclip counterpart) but can be changed in the future so that
	 * all interpolation methods are broken off into their own class and returned automatically
	 *
	 * @return array
	*/
	protected function interpolations()
	{
		return [
			':filename' => 'filename',
			':url' => 'url',
			':class' => 'getClass',
			':basename' => 'basename',
			':extension' => 'extension',
			':id' => 'id',
			':media' => 'media',
			':style' => 'style'
		];
	}

	/**
	 * Returns the file name.
	 *
	 * @param Manager $manager
	 * @param string  $styleName
	 * @return string
	*/
	protected function filename($manager, $styleName = '')
	{
		return $manager->originalFilename();
	}

	/**
	 * Generates the url to a file upload.
	 *
	 * @param Manager $manager
	 * @param string  $styleName
	 * @return string
	*/
	protected function url($manager, $styleName = '')
	{
		return $this->interpolate($manager->url, $manager, $styleName);
	}

	/**
	 * Returns the current class name, taking into account namespaces, e.g
	 * '\\Swingline\\MediaSort' will become swingline/mediasort.
	 *
	 * @param Manager $manager
	 * @param string  $styleName
	 * @return string
	*/
    protected function getClass($manager, $styleName = '')
    {
    	return strtolower($this->handleBackslashes($manager->getInstanceClass()));
    }

    /**
	 * Returns the basename portion of the media file, e.g 'file' for file.jpg.
	 *
	 * @param Manager $manager
	 * @param string  $styleName
	 * @return string
	*/
	protected function basename($manager, $styleName = '')
	{
		return pathinfo($manager->originalFilename(), PATHINFO_FILENAME);
	}

    /**
	 * Returns the extension of the media file, e.g 'jpg' for file.jpg.
	 *
	 * @param Manager $manager
	 * @param string  $styleName
	 * @return string
	*/
	protected function extension($manager, $styleName = '')
	{
		return pathinfo($manager->originalFilename(), PATHINFO_EXTENSION);
	}

	/**
	 * Returns the id of the current object instance.
	 *
	 * @param Manager $manager
	 * @param string  $styleName
	 * @return string
	*/
    protected function id($manager, $styleName = '')
    {
     	return $manager->getInstance()->getKey();
    }

	/**
	 * Returns the pluralized form of the media name. e.g.
     * "avatars" for an media of :avatar.
	 *
	 * @param Manager $manager
	 * @param string  $styleName
	 * @return string
	*/
	protected function media($manager, $styleName = '')
	{
		return str_plural($manager->name);
	}

	/**
	 * Returns the style, or the default style if an empty style is supplied.
	 *
	 * @param Manager $manager
	 * @param string  $styleName
	 * @return string
	*/
	protected function style($manager, $styleName = '')
	{
		return $styleName ?: $manager->default_style;
	}

	/**
	 * Utitlity function to turn a backslashed string into a string
	 * suitable for use in a file path, e.g '\foo\bar' becomes 'foo/bar'.
	 *
	 * @param string $string
	 * @return string
	 */
	protected function handleBackslashes($string)
	{
		return str_replace('\\', '/', ltrim($string, '\\'));
	}
}