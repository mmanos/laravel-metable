<?php namespace Mmanos\Metable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MetableCommand extends Command
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'laravel-metable:metable';
	
	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a migration for a metable content table';
	
	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$full_migration_path = $this->createBaseMigration();
		file_put_contents($full_migration_path, $this->getMigrationStub());
		$this->info('Migration created successfully!');
		
		$this->call('optimize');
	}
	
	/**
	 * Create a base migration file.
	 *
	 * @return string
	 */
	protected function createBaseMigration()
	{
		$name = 'create_' . $this->argument('table') . '_table';

        $path = base_path('database/migrations');
		
		return $this->laravel['migration.creator']->create($name, $path);
	}
	
	/**
	 * Get the contents of the migration stub.
	 *
	 * @return string
	 */
	protected function getMigrationStub()
	{
		$stub = file_get_contents(__DIR__.'/../Stubs/MetableMigration.stub.php');
		
		$stub = str_replace('{{table}}', $this->argument('table'), $stub);
		$stub = str_replace(
			'{{class}}',
			'Create' . Str::studly($this->argument('table')) . 'Table',
			$stub
		);
		
		return $stub;
	}
	
	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('table', InputArgument::REQUIRED, 'The name for your metable content table.'),
		);
	}
}
