<?php namespace Mmanos\Metable;

use Illuminate\Support\ServiceProvider;
use Mmanos\Metable\Commands\MetableCommand;
use Mmanos\Metable\Commands\MetasCommand;

class MetableServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;
	
	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{

	}
	
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind('command.laravel-metable.metas', function ($app) {
			return new MetasCommand();
		});
		$this->commands('command.laravel-metable.metas');
		
		$this->app->bind('command.laravel-metable.metable', function ($app) {
			return new MetableCommand();
		});
		$this->commands('command.laravel-metable.metable');
	}
	
	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}
}
