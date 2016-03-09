<?php

namespace Torann\MediaSort;

use Illuminate\Support\ServiceProvider;

class MediaSortServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Holds the hash value for the current MEDIASORT_NULL constant.
     *
     * @var string
     */
    protected $mediaSortNull;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->isLumen() === false) {
            $this->publishes([
                __DIR__ . '/../../config/mediasort.php' => config_path('mediasort.php'),
            ]);

            $this->mergeConfigFrom(
                __DIR__ . '/../../config/mediasort.php', 'mediasort'
            );
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mediaSortNull = sha1(time());

        if (!defined('MEDIASORT_NULL')) {
            define('MEDIASORT_NULL', $this->mediaSortNull);
        }

        // Package
        $this->registerMediaSort();

        // Commands
        $this->registerFastenCommand();
        $this->registerRefreshCommand();
    }

    /**
     * Register \Torann\MediaSort\MediaSort with the container.
     *
     * @return void
     */
    protected function registerMediaSort()
    {
        $this->app->bind('MediaSort', function ($app, $params) {
            $params['options']['disk'] = $app->config->get('filesystems.default', 'local');

            $config = new Config($params['name'], $params['options']);

            return new Manager($config, $app['filesystem']);
        });
    }

    /**
     * Register the MediaSort fasten command with the container.
     *
     * @return void
     */
    protected function registerFastenCommand()
    {
        $this->app->bind('media.fasten', function () {
            return new Commands\FastenCommand;
        });

        $this->commands('media.fasten');
    }

    /**
     * Register the MediaSort refresh command with the container.
     *
     * @return void
     */
    protected function registerRefreshCommand()
    {
        $this->app->bind('media.refresh', function () {
            $refreshService = new Services\ImageRefreshService();

            return new Commands\RefreshCommand($refreshService);
        });

        $this->commands('media.refresh');
    }

    /**
     * Check if package is running under Lumen app
     *
     * @return bool
     */
    protected function isLumen()
    {
        return str_contains($this->app->version(), 'Lumen') === true;
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}