<?php{{namespace}}

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\SoftDeletingTrait;

class {{class}} extends Eloquent
{
	use SoftDeletingTrait;
	
	/**
	 * The table name for t his model.
	 *
	 * @var string
	 */
	protected $table = '{{table}}';
	
	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = array('deleted_at');
	
	/**
	 * The attributes that aren't mass assignable.
	 *
	 * @var array
	 */
	protected $guarded = array('id');
	
	/**
	 * The dates array.
	 *
	 * @var array
	 */
	protected $dates = array('deleted_at');
}
