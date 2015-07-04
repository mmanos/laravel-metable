<?php namespace Mmanos\Metable;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

class QueryBuilder extends Builder
{
	protected $model;
	protected $meta_context;
	protected $filters = array();
	protected $relations = array();
	protected $meta_filters_applied;
	
	/**
	 * Set the model for this instance.
	 *
	 * @param \Illuminate\Database\Eloquent\Model $model
	 * 
	 * @return QueryBuilder
	 */
	public function setModel($model)
	{
		$this->model = $model;
		
		$this->from($this->model->metableTable() . ' AS m');
		
		if ($this->model->metableTableSoftDeletes()) {
			$this->whereNull('m.' . $this->model->getDeletedAtColumn());
		}
		
		return $this;
	}
	
	/**
	 * Set the meta query context to be used by this instance.
	 *
	 * @param mixed $meta_context
	 * 
	 * @return QueryBuilder
	 */
	public function withMetaContext($meta_context)
	{
		$this->meta_context = $meta_context;
		
		return $this;
	}
	
	/**
	 * Filter the query on the given meta name(s) or meta model(s).
	 *
	 * @param Eloquent|string|Collection|array $meta
	 * 
	 * @return QueryBuilder
	 */
	public function withMeta($meta)
	{
		foreach (func_get_args() as $arg) {
			$metas = ($arg instanceof Collection) ? $arg->all() : is_array($arg) ? $arg : array($arg);
			
			foreach ($metas as $m) {
				if (is_object($m)) {
					$this->filters[]['metas'][] = array('id' => $m->id);
				}
				else {
					$this->filters[]['metas'][] = array('name' => $m);
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Filter the query on the given meta id(s).
	 *
	 * @param int|array $id
	 * 
	 * @return QueryBuilder
	 */
	public function withMetaId($id)
	{
		foreach (func_get_args() as $arg) {
			$meta_ids = (array) $arg;
			
			foreach ($meta_ids as $meta_id) {
				$this->filters[]['metas'][] = array('id' => $meta_id);
			}
		}
		
		return $this;
	}
	
	/**
	 * Filter the query on any of the given meta name(s) or meta model(s).
	 *
	 * @param Eloquent|string|Collection|array $meta
	 * 
	 * @return QueryBuilder
	 */
	public function withAnyMeta($meta)
	{
		$filters = array();
		
		foreach (func_get_args() as $arg) {
			$metas = ($arg instanceof Collection) ? $arg->all() : is_array($arg) ? $arg : array($arg);
			
			foreach ($metas as $m) {
				if (is_object($m)) {
					$filters[] = array('id' => $m->id);
				}
				else {
					$filters[] = array('name' => $m);
				}
			}
		}
		
		$this->filters[]['metas'] = $filters;
		
		return $this;
	}
	
	/**
	 * Filter the query on any of the given meta id(s).
	 *
	 * @param int|array $id
	 * 
	 * @return QueryBuilder
	 */
	public function withAnyMetaId($id)
	{
		$filters = array();
		
		foreach (func_get_args() as $arg) {
			$arg = (array) $arg;
			
			foreach ($arg as $meta_id) {
				$filters[] = array('id' => $m->id);
			}
		}
		
		$this->filters[]['metas'] = $filters;
		
		return $this;
	}
	
	/**
	 * Filter the query on the given meta name(s) (or meta model(s)) and meta value(s).
	 *
	 * @param Eloquent|string|array $meta
	 * @param string                $operator
	 * @param mixed                 $value
	 * 
	 * @return QueryBuilder
	 */
	public function whereMeta($meta, $operator = null, $value = null)
	{
		if (null === $value) {
			$value = $operator;
			$operator = '=';
		}
		
		$metas = is_array($meta) ? $meta : array($meta => array($value, $operator));
		
		foreach ($metas as $m => $d) {
			$v = is_array($d) ? Arr::get($d, 0) : $d;
			$o = is_array($d) ? Arr::get($d, 1, '=') : '=';
			
			if (is_object($m)) {
				$this->filters[]['metas'][] = array('id' => $m->id, 'value' => $v, 'operator' => $o);
			}
			else {
				$this->filters[]['metas'][] = array('name' => $m, 'value' => $v, 'operator' => $o);
			}
		}
		
		return $this;
	}
	
	/**
	 * Filter the query on the given meta id(s).
	 *
	 * @param int|array $id
	 * @param string    $operator
	 * @param mixed     $value
	 * 
	 * @return QueryBuilder
	 */
	public function whereMetaId($id, $operator = null, $value = null)
	{
		if (null === $value) {
			$value = $operator;
			$operator = '=';
		}
		
		$meta_ids = is_array($id) ? $id : array($id => array($value, $operator));
		
		foreach ($meta_ids as $m => $d) {
			$v = is_array($d) ? Arr::get($d, 0) : $d;
			$o = is_array($d) ? Arr::get($d, 1, '=') : '=';
			
			$this->filters[]['metas'][] = array('id' => $m, 'value' => $v, 'operator' => $o);
		}
		
		return $this;
	}
	
	/**
	 * Filter the query on any of the given meta names (or meta models) and meta values.
	 *
	 * @param array $metas
	 * 
	 * @return QueryBuilder
	 */
	public function whereAnyMeta(array $metas)
	{
		$filters = array();
		
		foreach ($metas as $meta => $data) {
			$value = is_array($data) ? Arr::get($data, 0) : $data;
			$operator = is_array($data) ? Arr::get($data, 1, '=') : '=';
			
			if (is_object($meta)) {
				$filters[] = array('id' => $meta->id, 'value' => $value, 'operator' => $operator);
			}
			else {
				$filters[] = array('name' => $meta, 'value' => $value, 'operator' => $operator);
			}
		}
		
		$this->filters[]['metas'] = $filters;
		
		return $this;
	}
	
	/**
	 * Filter the query on any of the given meta ids and meta values.
	 *
	 * @param array $metas
	 * 
	 * @return QueryBuilder
	 */
	public function whereAnyMetaId(array $metas)
	{
		$filters = array();
		
		foreach ($metas as $meta => $data) {
			$value = is_array($data) ? Arr::get($data, 0) : $data;
			$operator = is_array($data) ? Arr::get($data, 1, '=') : '=';
			
			$filters[] = array('id' => $meta, 'value' => $value, 'operator' => $operator);
		}
		
		$this->filters[]['metas'] = $filters;
		
		return $this;
	}
	
	/**
	 * Set the relationships that should be eager loaded.
	 *
	 * @param  mixed  $relations
	 * @return QueryBuilder
	 */
	public function with($relations)
	{
		if (is_string($relations)) $relations = func_get_args();
		
		$this->relations = array_merge($this->relations, $relations);
		
		return $this;
	}
	
	/**
	 * Apply any meta filters added to this query.
	 * Will return false if any requested meta does not exist.
	 *
	 * @return bool
	 */
	protected function applyMetaFilters()
	{
		if (isset($this->meta_filters_applied)) {
			return $this->meta_filters_applied;
		}
		
		if (empty($this->filters)) {
			return $this->meta_filters_applied = false;
		}
		
		$meta_model = $this->model->metaModel();
		$meta_instance = new $meta_model;
		$meta_query = $meta_instance->newQuery();
		
		if (isset($this->meta_context)) {
			if (method_exists($meta_model, 'applyQueryContext')) {
				call_user_func_array(array($meta_model, 'applyQueryContext'), array($meta_query, $this->meta_context));
			}
		}
		
		$filters = $this->filters;
		$meta_query->where(function ($query) use ($filters) {
			foreach ($filters as $filter) {
				foreach ($filter['metas'] as $sub_filter) {
					if (isset($sub_filter['id'])) {
						$query->orWhere('id', $sub_filter['id']);
					}
					else if (isset($sub_filter['name'])) {
						$query->orWhere('name', $sub_filter['name']);
					}
				}
			}
		});
		
		$metas = $meta_query->get();
		$meta_items = $metas->lists('num_items', 'id');
		
		$found = array(
			'id'   => $metas->lists('id', 'id'),
			'name' => $metas->lists('id', 'name'),
		);
		
		foreach ($filters as &$filter) {
			$found_one = false;
			
			foreach ($filter['metas'] as $idx => &$sub_filter) {
				$key = isset($sub_filter['id']) ? 'id' : 'name';
				$key_value = $sub_filter[$key];
				
				if (!array_key_exists($key_value, $found[$key])) {
					unset($filter['metas'][$idx]);
					continue;
				}
				
				$found_one = true;
				$sub_filter['id'] = $found[$key][$key_value];
				
				$num_items = $meta_items[$found[$key][$key_value]];
				if (!isset($filter['num_items']) || $num_items > $filter['num_items']) {
					$filter['num_items'] = $num_items;
				}
			}
			
			if (!$found_one) {
				return $this->meta_filters_applied = false;
			}
			
			if (count($filter['metas']) > 1) {
				$filter['num_items'] += 100000000;
			}
		}
		
		usort($filters, function ($a, $b) {
			return ($a['num_items'] < $b['num_items']) ? -1 : 1;
		});
		
		$set_distinct = false;
		
		$first_filter = current(array_splice($filters, 0, 1));
		if (count($first_filter['metas']) > 1) {
			$this->where(function ($query) use ($first_filter) {
				foreach ($first_filter['metas'] as $f) {
					if (isset($f['value'])) {
						$query->orWhere(function ($query) use ($f) {
							$query->where('m.meta_id', $f['id']);
							$query->where('m.value', $f['operator'], $f['value']);
						});
					}
					else {
						$query->orWhere('m.meta_id', $f['id']);
					}
				}
			});
			
			if (!$set_distinct) {
				$this->distinct();
				$set_distinct = true;
			}
		}
		else {
			foreach ($first_filter['metas'] as $f) {
				$this->where('m.meta_id', $f['id']);
				if (isset($f['value'])) {
					$this->where('m.value', $f['operator'], $f['value']);
				}
			}
		}
		
		foreach ($filters as $i => $cur_filter) {
			$this->join("{$this->model->metableTable()} AS m{$i}", function ($join) use ($i, $cur_filter) {
				$join_query = $join->on('m.xref_id', '=', "m{$i}.xref_id");
				
				if (count($cur_filter['metas']) <= 1) {
					foreach ($cur_filter['metas'] as $f) {
						if (isset($f['value'])) {
							$join_query->where("m{$i}.meta_id", '=', $f['id']);
							$join_query->where("m{$i}.value", $f['operator'], $f['value']);
						}
						else {
							$join_query->where("m{$i}.meta_id", '=', $f['id']);
						}
					}
				}
			});
			
			if (count($cur_filter['metas']) > 1) {
				$this->where(function ($query) use ($cur_filter, $i) {
					foreach ($cur_filter['metas'] as $f) {
						if (isset($f['value'])) {
							$query->orWhere(function ($query2) use ($f, $i) {
								$query2->where("m{$i}.meta_id", $f['id']);
								$query2->where("m{$i}.value", $f['operator'], $f['value']);
							});
						}
						else {
							$query->orWhere("m{$i}.meta_id", $f['id']);
						}
					}
				});
				
				if (!$set_distinct) {
					$this->distinct();
					$set_distinct = true;
				}
			}
		}
		
		return $this->meta_filters_applied = true;
	}
	
	/**
	 * Execute the query as a "select" statement.
	 *
	 * @param  array  $columns
	 * @return \Illuminate\Database\Eloquent\Collection|static[]
	 */
	public function get($columns = array('*'))
	{
		if (!$this->applyMetaFilters()) {
			if (!empty($this->aggregate)) {
				return 0;
			}
			return $this->model->newCollection();
		}
		
		if (!empty($this->aggregate)) {
			return parent::get($columns);
		}
		else {
			$results = parent::get(array('m.xref_id'));
		}
		
		if (empty($results)) {
			return $this->model->newCollection();
		}
		
		$xref_ids = array();
		foreach ($results as $result) {
			if (!isset($result->xref_id)) continue;
			$xref_ids[] = $result->xref_id;
		}
		
		if (empty($xref_ids)) {
			return $this->model->newCollection();
		}
		
		$key = $this->model->getKeyName();
		
		$models = $this->model->newQuery()->whereIn($key, $xref_ids)->get();
		
		$models->sortBy(function ($model) use ($xref_ids, $key) {
			foreach ($xref_ids as $idx => $i) {
				if ($model->{$key} == $i) {
					return $idx;
				}
			}
			return 0;
		});
		
		if (!empty($this->relations)) {
			$models->load($this->relations);
		}
		
		return $models;
	}
	
	/**
	 * Execute the query and get the first result.
	 *
	 * @param  array   $columns
	 * @return mixed|\Illuminate\Database\Eloquent\Collection|static
	 */
	public function first($columns = array('*'))
	{
		$results = $this->take(1)->get($columns);

		return count($results) > 0 ? $results->first() : null;
	}
	
	/**
	 * Get a paginator for the "select" statement.
	 *
	 * @param  int    $perPage
	 * @param  array  $columns
	 * @return \Illuminate\Pagination\Paginator
	 */
	public function paginate($perPage = null, $columns = array('*'))
	{
		$perPage = $perPage ?: $this->model->getPerPage();
		
		$paginator = $this->connection->getPaginator();
		
		$total = $this->getPaginationCount();
		
		return $paginator->make($this->get($columns)->all(), $total, $perPage);
	}
	
	/**
	 * Update a record in the database.
	 *
	 * @param  array  $values
	 * @return bool
	 */
	public function update(array $values)
	{
		return false;
	}
	
	/**
	 * Delete a record from the database.
	 *
	 * @param  mixed  $id
	 * @return bool
	 */
	public function delete($id = null)
	{
		return false;
	}
}
