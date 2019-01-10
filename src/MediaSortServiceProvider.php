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

        $this->mergeConfigFrom(
            __DIR__ . '/../config/mediasort.php', 'mediasort'
        );
    }

    /**
     * Register \Torann\MediaSort\MediaSort with the container.
     *
     * @return void
     */
    protected function registerMediaSort()
    {
        $this->mediaSortNull = sha1(time());

        if (defined('MEDIASORT_NULL') === false) {
            define('MEDIASORT_NULL', $this->mediaSortNull);
        }
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
                $app['view'], $app['files']
            );
        });

        $this->app->bind('mediasort.refresh', function ($app) {
            return new Commands\RefreshCommand(
                new Services\ImageRefreshService()
            );
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
