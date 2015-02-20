<?php namespace Torann\MediaSort\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use View, File, Str;

class FastenCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'media:fasten';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generate a migration for adding MediaSort file fields to a database table';

	/**
	 * Create a new command instance.
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->createMigration();
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('table', InputArgument::REQUIRED, 'The name of the database table the file fields will be added to.'),
			array('attachment', InputArgument::REQUIRED, 'The name of the corresponding MediaSort attachment.'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array();
	}

	/**
	 * Create a new migration.
	 *
	 * @return void
	 */
	protected function createMigration()
	{
		$data = ['table' => $this->argument('table'), 'attachment' => $this->argument('attachment')];
		$prefix = date('Y_m_d_His');
		$path = app_path() . '/database/migrations';

		if (!is_dir($path)) mkdir($path);

		$fileName  = $path . '/' . $prefix . '_add_' . $data['attachment'] . '_fields_to_' . $data['table'] . '_table.php';
		$data['className'] = 'Add' . ucfirst($data['attachment']) . 'FieldsTo' . ucfirst(Str::camel($data['table'])) . 'Table';

		// Save the new migration to disk using the MediaSort migration view.
		$migration = View::make('mediasort::migration', $data)->render();
		File::put($fileName, $migration);

		// Print a created migration message to the console.
		$this->info("Created migration: $fileName");
	}

}
