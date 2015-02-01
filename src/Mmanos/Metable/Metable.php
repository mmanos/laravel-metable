<?php namespace Mmanos\Metable;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model as Eloquent;

trait Metable
{
	/**
	 * Trait boot method called by parent model class.
	 *
	 * @return void
	 */
	public static function bootMetable()
	{
		static::saved(function ($model) {
			$model->syncMetableTableAttributes();
		});
		
		static::deleted(function ($model) {
			$model->handleDeletedModelMetas();
		});
		
		static::registerModelEvent('restored', function ($model) {
			$model->handleRestoredModelMetas();
		});
	}
	
	/**
	 * Return the meta class for this model.
	 *
	 * @return string
	 */
	public function metaModel()
	{
		return $this->meta_model;
	}
	
	/**
	 * Return the metable table name for this model.
	 *
	 * @return string
	 */
	public function metableTable()
	{
		return $this->metable_table;
	}
	
	/**
	 * Define the metas relationship for this model.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function metas()
	{
		return $this->belongsToMany($this->metaModel(), $this->metableTable(), 'xref_id')->withPivot('value', 'meta_created_at', 'meta_updated_at');
	}
	
	/**
	 * Return an array of all metas associated with this model.
	 *
	 * @return array
	 */
	public function metasArray()
	{
		$metas = array();
		
		foreach ($this->metas as $meta) {
			if (!isset($meta->pivot)) {
				continue;
			}
			
			$metas[$meta->name] = $meta->pivot->value;
		}
		
		return $metas;
	}
	
	/**
	 * Retrieve one or more values from the current model.
	 * Can be either a meta name, a meta model, a collection of models, or an array of models.
	 *
	 * @param Eloquent|string|Collection|array $name
	 * @param mixed                            $default
	 * 
	 * @return mixed|array
	 */
	public function meta($meta, $default = null)
	{
		$single = ($meta instanceof Collection) ? false : is_array($meta) ? false : true;
		$metas = ($meta instanceof Collection) ? $meta->all() : is_array($meta) ? $meta : array($meta);
		
		$values = array();
		
		foreach ($metas as $m) {
			if (is_object($m)) {
				$values[$m->name] = $this->metas->find($m->id, $default);
				continue;
			}
			
			$found = $this->metas->filter(function ($m2) use ($m) {
				return $m2->name == $m;
			});
			
			if (!$found->isEmpty()) {
				if (isset($found->first()->pivot)) {
					$values[$m] = $found->first()->pivot->value;
					continue;
				}
			}
			
			$values[$m] = $default;
		}
		
		return $single ? current($values) : $values;
	}
	
	/**
	 * Returns true if the current model has the given meta name (or meta model).
	 *
	 * @param Eloquent|string $meta
	 * 
	 * @return bool
	 */
	public function hasMeta($meta)
	{
		$found = $this->metas->filter(function ($t) use ($meta) {
			if (is_object($meta)) {
				return $t->id == $meta->id;
			}
			else {
				return $t->name == $meta;
			}
		});
		
		return !$found->isEmpty();
	}
	
	/**
	 * Add one or more metas to the current model.
	 * Can be either a meta name, a meta model, or an array of models.
	 *
	 * @param Eloquent|string|array $meta
	 * @param mixed                 $value
	 * 
	 * @return Eloquent
	 */
	public function setMeta($meta, $value = null)
	{
		$metas = is_array($meta) ? $meta : array($meta => $value);
		
		foreach ($metas as $m => $v) {
			if (!is_object($m)) {
				$m = $this->findMetaByNameOrCreate($m);
			}
			
			if (!$m || !$m instanceof Eloquent) {
				continue;
			}
			
			if ($this->hasMeta($m)) {
				if (is_null($v)) {
					$this->unsetMeta($m);
					continue;
				}
				
				$attributes = array(
					'value'           => $v,
					'meta_updated_at' => date('Y-m-d H:i:s'),
				);
				$this->metas()->updateExistingPivot($m->id, array_merge($this->metableTableSyncAttributes(), $attributes));
				$this->metas->find($m->id)->pivot->value = $v;
				$this->metas->find($m->id)->pivot->meta_updated_at = $attributes['meta_updated_at'];
			}
			else {
				if (is_null($v)) {
					continue;
				}
				
				$this->metas()->attach($m, array_merge($this->metableTableSyncAttributes(), array(
					'value'           => $v,
					'meta_created_at' => date('Y-m-d H:i:s'),
					'meta_updated_at' => date('Y-m-d H:i:s'),
				)));
				
				$m->increment('num_items');
				
				$this->metas->add($m);
			}
		}
		
		return $this;
	}
	
	/**
	 * Remove one or more metas from the current model.
	 * Can be either a meta name, a meta model, a collection of models, or an array of models.
	 * Will remove all metas if no paramter is passed.
	 *
	 * @param Eloquent|string|Collection|array $meta
	 * 
	 * @return Eloquent
	 */
	public function unsetMeta($meta = null)
	{
		$args = func_get_args();
		
		if (0 == count($args)) {
			$args[] = $this->metas;
		}
		
		foreach ($args as $arg) {
			$metas = ($arg instanceof Collection) ? $arg->all() : is_array($arg) ? $arg : array($arg);
			
			foreach ($metas as $m) {
				if (!is_object($m)) {
					$m = $this->findMetaByName($m);
				}
				
				if (!$m || !$m instanceof Eloquent) {
					continue;
				}
				
				if (!$this->hasMeta($m)) {
					return;
				}
				
				$this->metas()->detach($m);
				
				$m->decrement('num_items');
				
				foreach ($this->metas as $idx => $cur_meta) {
					if ($cur_meta->getKey() == $m->getKey()) {
						$this->metas->pull($idx);
						break;
					}
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Return a new meta table query.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	private function newMetaQuery()
	{
		$meta_model = $this->metaModel();
		$meta_instance = new $meta_model;
		
		$query = $meta_instance->newQuery();
		
		if (method_exists($this, 'metaContext')) {
			if (method_exists($meta_model, 'applyQueryContext')) {
				call_user_func_array(
					array($meta_model, 'applyQueryContext'),
					array($query, $this->metaContext())
				);
			}
		}
		
		return $query;
	}
	
	/**
	 * Find a meta from the given name.
	 *
	 * @param string $name
	 * 
	 * @return Eloquent|null
	 */
	private function findMetaByName($name)
	{
		return $this->newMetaQuery()->where('name', $name)->first();
	}
	
	/**
	 * Find a meta from the given name or create it if not found.
	 *
	 * @param string $name
	 * 
	 * @return Eloquent
	 */
	private function findMetaByNameOrCreate($name)
	{
		if ($meta = $this->findMetaByName($name)) {
			return $meta;
		}
		
		$meta_model = $this->metaModel();
		
		$meta = new $meta_model;
		$meta->name = $name;
		$meta->num_items = 0;
		
		if (method_exists($this, 'metaContext')) {
			if (method_exists($meta_model, 'applyModelContext')) {
				call_user_func_array(
					array($meta_model, 'applyModelContext'),
					array($meta, $this->metaContext())
				);
			}
		}
		
		$meta->save();
		
		return $meta;
	}
	
	/**
	 * Return an array of model attributes to sync on the metable_table records.
	 *
	 * @return array
	 */
	private function metableTableSyncAttributes()
	{
		if (!isset($this->metable_table_sync)) {
			return array();
		}
		
		$attributes = array();
		foreach ($this->metable_table_sync as $attr) {
			$attributes[$attr] = $this->getAttribute($attr);
		}
		
		return $attributes;
	}
	
	/**
	 * Returns whether or not we are soft-deleting metable table records.
	 *
	 * @return bool
	 */
	public function metableTableSoftDeletes()
	{
		if (method_exists($this, 'getDeletedAtColumn')) {
			if (array_key_exists($this->getDeletedAtColumn(), $this->metableTableSyncAttributes())) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Sync metable table attributes to all metas associated with this model.
	 *
	 * @return void
	 */
	public function syncMetableTableAttributes()
	{
		if (empty($this->metableTableSyncAttributes())) {
			return;
		}
		
		DB::table($this->metableTable())
			->where('xref_id', $this->getKey())
			->update($this->metableTableSyncAttributes());
	}
	
	/**
	 * Delete metable table records for this current model since it was just deleted.
	 *
	 * @return void
	 */
	private function handleDeletedModelMetas()
	{
		if ($this->metableTableSoftDeletes()) {
			foreach ($this->metas as $meta) {
				$this->syncMetableTableAttributes();
				$meta->decrement('num_items');
			}
			
			return;
		}
		
		$this->unsetMeta();
	}
	
	/**
	 * Restore metable table records for this current model since it was just restorede.
	 *
	 * @return void
	 */
	private function handleRestoredModelMetas()
	{
		if (!$this->metableTableSoftDeletes()) {
			return;
		}
		
		foreach ($this->metas as $meta) {
			$this->syncMetableTableAttributes();
			$meta->increment('num_items');
		}
	}
	
	/**
	 * Begin querying the model's metable table and filter on the given meta name(s) or meta model(s).
	 *
	 * @param Eloquent|string|Collection|array $meta
	 * 
	 * @return QueryBuilder
	 */
	public static function withMeta($meta)
	{
		return call_user_func_array(array(static::queryMetas(), 'withMeta'), func_get_args());
	}
	
	/**
	 * Begin querying the model's metable table and filter on the given meta id(s).
	 *
	 * @param int|array $id
	 * 
	 * @return QueryBuilder
	 */
	public static function withMetaId($id)
	{
		return call_user_func_array(array(static::queryMetas(), 'withMetaId'), func_get_args());
	}
	
	/**
	 * Begin querying the model's metable table and filter on any of the given meta name(s) or meta model(s).
	 *
	 * @param Eloquent|string|Collection|array $meta
	 * 
	 * @return QueryBuilder
	 */
	public static function withAnyMeta($meta)
	{
		return call_user_func_array(array(static::queryMetas(), 'withAnyMeta'), func_get_args());
	}
	
	/**
	 * Begin querying the model's metable table and filter on any of the given meta id(s).
	 *
	 * @param int|array $id
	 * 
	 * @return QueryBuilder
	 */
	public static function withAnyMetaId($id)
	{
		return call_user_func_array(array(static::queryMetas(), 'withAnyMetaId'), func_get_args());
	}
	
	/**
	 * Begin querying the model's metable table and filter on the given meta name(s) (or meta model(s)) and meta value(s).
	 *
	 * @param Eloquent|string|array $meta
	 * @param string                $operator
	 * @param mixed                 $value
	 * 
	 * @return QueryBuilder
	 */
	public static function whereMeta($meta, $operator = null, $value = null)
	{
		return call_user_func_array(array(static::queryMetas(), 'whereMeta'), func_get_args());
	}
	
	/**
	 * Begin querying the model's metable table and filter on the given meta id(s) and meta value(s).
	 *
	 * @param int|array $id
	 * @param string    $operator
	 * @param mixed     $value
	 * 
	 * @return QueryBuilder
	 */
	public static function whereMetaId($id, $operator = null, $value = null)
	{
		return call_user_func_array(array(static::queryMetas(), 'whereMetaId'), func_get_args());
	}
	
	/**
	 * Begin querying the model's metable table and filter on any of the given meta names (or meta models) and meta values.
	 *
	 * @param array $metas
	 * 
	 * @return QueryBuilder
	 */
	public static function whereAnyMeta(array $metas)
	{
		return call_user_func_array(array(static::queryMetas(), 'whereAnyMeta'), func_get_args());
	}
	
	/**
	 * Begin querying the model's metable table and filter on any of the given meta ids and meta values.
	 *
	 * @param array $metas
	 * 
	 * @return QueryBuilder
	 */
	public static function whereAnyMetaId(array $metas)
	{
		return call_user_func_array(array(static::queryMetas(), 'whereAnyMetaId'), func_get_args());
	}
	
	/**
	 * Begin querying the model's metable table.
	 *
	 * @return QueryBuilder
	 */
	public static function queryMetas()
	{
		$model = new static;
		
		$conn = $model->getConnection();
		$query = new QueryBuilder(
			$conn,
			$conn->getQueryGrammar(),
			$conn->getPostProcessor()
		);
		
		$query->setModel($model);
		
		return $query;
	}
}
