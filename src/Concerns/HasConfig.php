<?php

namespace Torann\MediaSort\Concerns;

use Exception;
use Illuminate\Support\Arr;

trait HasConfig
{
    /**
     * Configuration values.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Get configuration value.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function config($key, $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * Mutator method for the config property.
     *
     * @param array $config
     *
     * @return void
     * @throws Exception
     */
    public function setConfig($config)
    {
        $this->config = $config;

        // Sanity check
        if (strpos($this->config['url'], '{id}') === false) {
            throw new Exception('Invalid Url: an id interpolation is required.', 1);
        }

        // Set media disk
        $this->config['disk'] = Arr::get(
            $this->config, 'disk', config('filesystems.default', 'local')
        );
    }
}
