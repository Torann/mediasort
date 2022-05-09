<?php

namespace Torann\MediaSort\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Torann\MediaSort\Services\ImageRefreshService;

class RefreshCommand extends Command
{
    protected ImageRefreshService $image_refresh_service;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'media:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate images for a given model (and optional attachment and styles)';

    /**
     * Create a new command instance.
     *
     * @param ImageRefreshService $image_refresh_service
     */
    public function __construct(ImageRefreshService $image_refresh_service)
    {
        parent::__construct();

        $this->image_refresh_service = $image_refresh_service;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Refreshing uploaded images...');

        $this->image_refresh_service->refresh(
            $this->argument('class'), $this->option('attachments')
        );

        $this->info('Done!');

        return 0;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['class', InputArgument::OPTIONAL, 'The name of a class (model) to refresh images on'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['attachments', null, InputOption::VALUE_OPTIONAL, 'A list of specific attachments to refresh images on.'],
        ];
    }
}
