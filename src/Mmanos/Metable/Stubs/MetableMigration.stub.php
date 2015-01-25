<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class {{class}} extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('{{table}}', function (Blueprint $table)
		{
			$table->increments('id');
			$table->integer('xref_id')->unsigned();
			$table->integer('meta_id')->unsigned();
			$table->text('value');
			$table->timestamp('meta_created_at');
			$table->timestamp('meta_updated_at');
			
			$table->unique(array('xref_id', 'meta_id'), 'xref_meta');
			
			if ('mysql' != DB::connection()->getDriverName()) {
				$table->index(array('meta_id', 'value', 'xref_id'), 'meta_value');
			}
		});
		
		if ('mysql' == DB::connection()->getDriverName()) {
			DB::statement('CREATE INDEX meta_value ON user_metas (meta_id, value(255), xref_id);');
		}
	}
	
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('{{table}}');
	}
}
