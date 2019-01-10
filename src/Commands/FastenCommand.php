<?php

namespace Torann\MediaSort\Commands;

use Illuminate\View\Factory;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputArgument;

class FastenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:fasten
                                {table : The name of the database table the file fields will be added to.}
                                {attachment : The name of the corresponding MediaSort attachment.}
                                {after? : Name of a database field after which the file fields will get added.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a migration for adding MediaSort file fields to a database table.';

    /**
     * An instance of Laravel's view factory.
     *
     * @var \Illuminate\View\Factory
     */
    protected $view;

    /**
     * An instance of Laravel's filesystem.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $file;

    /**
     * Create a new command instance.
     *
     * @param Factory    $view
     * @param Filesystem $file
     */
    public function __construct(Factory $view, Filesystem $file)
    {
        parent::__construct();

        $this->view = $view;
        $this->file = $file;
    }

    /**
     * Execute the console command for Laravel 5.4 and below
     *
     * @return void
     */
    public function fire()
    {
        $this->handle();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $data = [
            'table' => $this->argument('table'),
            'attachment' => $this->argument('attachment'),
            'after' => $this->argument('after'),
        ];

        // Create filename
        $file = base_path("database/migrations/" . date('Y_m_d_His')
            . "_add_{$data['attachment']}_fields_to_{$data['table']}_table.php");

        // Save the new migration to disk using the MediaSort migration view.
        $migration = $this->view->file(realpath(__DIR__ . '/../../resources/views/migration.blade.php'), $data)->render();
        $this->file->put($file, $migration);

        // Print a created migration message to the console.
        $this->info("Created migration: $file");
    }
}
