<?php

namespace Torann\MediaSort;

use Illuminate\Support\ServiceProvider;

class MediaSortServiceProvider extends ServiceProvider
{
    /**
     * Holds the hash value for the current MEDIASORT_NULL constant.
     *
     * @var string
     */
    protected $mediaSortNull;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerMediaSort();

        if ($this->app->runningInConsole()) {
            $this->registerResources();
            $this->registerCommands();
        }
    }

    /**
     * Register \Torann\MediaSort\MediaSort with the container.
     *
     * @return void
     */
    protected function registerMediaSort()
    {
        $this->mediaSortNull = sha1(time());

        if (!defined('MEDIASORT_NULL')) {
            define('MEDIASORT_NULL', $this->mediaSortNull);
        }

        $this->app->bind('mediasort', function ($app, $params) {
            $params['options']['disk'] = $app->config->get('filesystems.default', 'local');

            $config = new Config($params['name'], $params['options']);

            return new Manager($config, $app['filesystem']);
        });
    }

    /**
     * Register resources.
     *
     * @return void
     */
    public function registerResources()
    {
        if ($this->isLumen() === false) {
            $this->publishes([
                __DIR__ . '/../config/mediasort.php' => config_path('mediasort.php'),
            ], 'config');

            $this->mergeConfigFrom(
                __DIR__ . '/../config/mediasort.php', 'mediasort'
            );
        }
    }

    /**
     * Register commands.
     *
     * @return void
     */
    public function registerCommands()
    {
        $this->app->bind('mediasort.fasten', function ($app) {
            return new Commands\FastenCommand(
                $app['view'],
                $app['files'],
                base_path('database/migrations')
            );
        });

        $this->app->bind('mediasort.refresh', function ($app) {
            $refreshService = new Services\ImageRefreshService();
            return new Commands\RefreshCommand($refreshService);
        });

        $this->commands([
            'mediasort.fasten',
            'mediasort.refresh',
        ]);
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
}