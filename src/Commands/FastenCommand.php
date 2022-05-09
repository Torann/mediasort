<?php

namespace Torann\MediaSort\Commands;

use Illuminate\Support\Str;
use Illuminate\View\Factory;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class FastenCommand extends Command
{
    protected Factory $view;
    protected Filesystem $file;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:fasten
                                {table : The name of the database table the file fields will be added to.}
                                {attachment : The name of the corresponding MediaSort attachment.}
                                {--queueable : Attachment will support queueing.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a migration for adding MediaSort file fields to a database table.';

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
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $data = [
            'table' => $this->argument('table'),
            'attachment' => $this->argument('attachment'),
            'queueable' => $this->option('queueable'),
        ];

        // Convert name to class
        $data['class_name'] = Str::studly($data['table']);

        // Create filename
        $file = base_path('database/migrations/'
            . date('Y_m_d_His')
            . "_add_{$data['attachment']}_fields_to_{$data['table']}_table.php");

        // Save the new migration to disk using the MediaSort migration view.
        $migration = $this->view
            ->file(realpath(__DIR__ . '/../../resources/views/migration.blade.php'), $data)
            ->render();

        $this->file->put($file, $migration);

        // Print a created migration message to the console.
        $this->info("Created migration: $file");

        return 0;
    }
}
