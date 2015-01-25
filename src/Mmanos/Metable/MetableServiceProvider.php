<?php namespace Mmanos\Metable;

use Illuminate\Support\ServiceProvider;

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
		$this->package('mmanos/laravel-metable');
	}
	
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bindShared('command.laravel-metable.metas', function ($app) {
			return new MetasCommand;
		});
		$this->commands('command.laravel-metable.metas');
		
		$this->app->bindShared('command.laravel-metable.metable', function ($app) {
			return new MetableCommand;
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
