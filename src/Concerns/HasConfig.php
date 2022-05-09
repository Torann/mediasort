<?php

namespace Torann\MediaSort\Concerns;

use Exception;
use Illuminate\Support\Arr;

trait HasConfig
{
    protected array $config = [];

    /**
     * @param mixed $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function config(mixed $key, mixed $default = null): mixed
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                Arr::set($this->config, $k, $v);

                $this->getModel()->updateMediaFile($this->name, $k, $v);
            }

            return null;
        }

        return Arr::get($this->config, $key, $default);
    }

    /**
     * Merge configuration options.
     *
     * Here we'll merge user defined options with the MediaSort defaults in a cascading manner.
     * We start with overall MediaSort options. Next we merge in storage driver specific options.
     *
     * Finally, we'll merge in media specific options on top of that.
     *
     * @param array|null $config
     *
     * @return void
     * @throws Exception
     */
    public function setConfig(array $config = null)
    {
        // Get the system set configuration
        $this->config = config('mediasort', []);

        // Apply any overrides
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }

        $this->config['styles'] = array_merge(
            (array) $this->config['styles'], ['original' => '']
        );

        // Sanity check
        if (str_contains($this->config['url'], '{id}') === false) {
            throw new Exception('Invalid Url: an id interpolation is required.', 1);
        }

        // Set media disk
        $this->config['disk'] = Arr::get(
            $this->config, 'disk', config('filesystems.default', 'local')
        );
    }
}
