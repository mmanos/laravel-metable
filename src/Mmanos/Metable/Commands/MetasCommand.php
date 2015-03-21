<?php namespace Mmanos\Metable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MetasCommand extends Command
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'laravel-metable:metas';
	
	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a migration and model for a metas summary table';
	
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
		
		$full_model_path = $this->createBaseModel();
		file_put_contents($full_model_path, $this->getModelStub());
		$this->info('Model created successfully!');
		
		$this->call('optimize');
	}
	
	/**
	 * Return the name of the table to create.
	 *
	 * @return string
	 */
	protected function tableName()
	{
		return $this->argument('table') ?: 'metas';
	}
	
	/**
	 * Create a base migration file.
	 *
	 * @return string
	 */
	protected function createBaseMigration()
	{
		$name = 'create_' . $this->tableName() . '_table';

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
		$stub = file_get_contents(__DIR__.'/../Stubs/MetasMigration.stub.php');
		
		$stub = str_replace('{{table}}', $this->tableName(), $stub);
		$stub = str_replace(
			'{{class}}',
			'Create' . Str::studly($this->tableName()) . 'Table',
			$stub
		);
		
		return $stub;
	}
	
	/**
	 * Create a base model file.
	 *
	 * @return string
	 */
	protected function createBaseModel()
	{
		$name_parts = explode('_', $this->tableName());

        $path = app_path();
		
		for ($i = 0; $i < (count($name_parts) - 1); $i++) {
			$path .= '/' . Str::studly(Str::singular($name_parts[$i]));
		}
		
		if (count($name_parts) > 1 && !File::exists($path)) {
			File::makeDirectory($path, 0755, true);
		}
		
		$path .= '/' . Str::studly(Str::singular(end($name_parts))) . '.php';
		
		return $path;
	}
	
	/**
	 * Get the contents of the model stub.
	 *
	 * @return string
	 */
	protected function getModelStub()
	{
		$stub = file_get_contents(__DIR__.'/../Stubs/MetaModel.stub.php');
		
		$name_parts = explode('_', $this->tableName());
		$namespace = '';
		for ($i = 0; $i < (count($name_parts) - 1); $i++) {
			$namespace .= '\\' . Str::studly(Str::singular($name_parts[$i]));
		}
		$namespace = trim($namespace, '\\');
		$class = Str::studly(Str::singular(end($name_parts)));
		
		$stub = str_replace('{{namespace}}', empty($namespace) ? '' : " namespace {$namespace};", $stub);
		$stub = str_replace('{{class}}', $class, $stub);
		$stub = str_replace('{{table}}', $this->tableName(), $stub);
		
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
			array('table', InputArgument::OPTIONAL, 'The name of your metas table (default is metas).'),
		);
	}
}
