<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: zed
 * Date: 17-10-26
 * Time: 上午11:22
 */

namespace Dezsidog;


use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Debug\Exception\ClassNotFoundException;

abstract class Repository implements RepositoryInterface
{
    protected $model;

    protected $expands = [];

    public function __construct()
    {
        if (!$this->model || !class_exists($this->model)) {
            throw new ClassNotFoundException("undefined class {$this->model}",new \ErrorException());
        }
    }

    protected function applySort(Builder $query): Builder
    {
        $sorts = \Request::get('sorts',[]);
        $model = $query->getModel();

        $current_table = $model->getTable();

        foreach ($sorts as $key => $value) {

            if (!$value || strpos($key, '.') !== false) {
                continue;
            }

            //先判断是否有自定义过滤方法
            $sort_method = 'sortBy'.ucfirst(camel_case($key));
            if (method_exists($this, $sort_method)) {
                $this->$sort_method($query,$value);
                continue;
            }

            //根据数据库字段名遍历构建排序语句
            $list = \Schema::getColumnListing($current_table);
            if (array_search($key,$list)) {
                $query->orderBy($key, $value);
            }
        }

        return $query;
    }

    protected function applyFilters(Builder $query): Builder
    {
        $filters = \Request::get('filters',[]);

        $model = $query->getModel();

        $current_table = $model->getTable();

        foreach ($filters as $key => $value) {

            if ($value === null) {
                continue;
            }

            //先判断是否有自定义过滤方法
            $filter_method = 'filterBy'.ucfirst(camel_case($key));
            if (strpos($key,'.') === false && method_exists($this, $filter_method)) {
                $this->$filter_method($query,$value);
                continue;
            }

            $keys = explode('.',$key);
            foreach ($keys as $item) {
                //根据数据库字段名遍历构建查询语句
                $list = \Schema::getColumnListing($current_table);
                if (array_search($item,$list)) {
                    switch (\Schema::getColumnType($current_table, $item)) {
                        case 'string':
                            $query->where($item,'like','%'.$value.'%');
                            break;
                        default:
                            $query->where($item,$value);
                    }
                }else{
                    if (method_exists($model, $item)) {
                        $relation = $model->$item();
                        list($current_table,$field) = explode('.',$relation->getQualifiedForeignKeyName());
                        if (!in_array($current_table,$query->joins)) {
                            $query->leftJoin($current_table,$field,'=',$relation->getQualifiedParentKeyName());
                        }
                    }
                }

            }
        }

        return $query;
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->getQuery()->get($columns);
    }

    public function create(array $data): Model
    {
        $fields = array_only($data,$this->getFillable());
        /**
         * @var Model $model
         */
        $model = call_user_func_array([$this->model,'create'],[$fields]);
        $model = $model->fresh($this->getExpands());
        $model->wasRecentlyCreated = true;
        return $model;
    }

    public function find($model, array $columns = ['*']): Model
    {
        if (!($model instanceof Model)) {
            return $this->getQuery()->find($model);
        }else{
            return $model->fresh($this->getExpands());
        }
    }

    public function update(array $data, $model): Model
    {
        if (!($model instanceof Model)) {
            $model = call_user_func_array([$this->model,'find'],[$model]);
        }
        $fields = array_only($data,$this->getFillable());
        /**
         * @var Model $model
         */
        $model->update($fields);
        return $model->fresh($this->getExpands());
    }

    /**
     * 根据简单条件查找数据
     * @param string $field
     * @param $value
     * @param array $columns
     * @return Collection
     *
     * @deprecated 1.1.0
     */
    public function findBy($field, $operator = null, $value = null, string $boolean = 'and', array $columns = ['*']): Collection
    {
        Log::warning('the findBy method of Repository is deprecated, by instead you can use getQuery method to build query string by yourself.');
        return $this->getQuery()->where($field, $operator, $value, $boolean)->get($columns);
    }

    public function delete($model): bool
    {
        if (!($model instanceof Model)) {
            $model = call_user_func_array([$this->model,'find'],[$model]);
        }
        return $model->delete();
    }

    /**
     * @param \Closure|null $callback
     * @param int $perPage
     * @param array $columns
     * @return LengthAwarePaginator
     *
     * @deprecated 1.1.2
     */
    public function paginate(?\Closure $callback = null, int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        $query = $this->getQuery();
        if ($callback) {
            $callback($query);
        }
        return $query->paginate($perPage,$columns);
    }

    protected function applyExpands(Builder $query): Builder
    {
        if (!empty($this->getExpands())) {
            return $query->with($this->getExpands());
        }

        return $query;
    }

    public function getExpands(): array
    {
        if (\Request::has('expands')) {
            $this->expands = array_filter(
                array_merge(
                    explode(',',\Request::get('expands','')),
                    $this->expands
                )
            );
        }
        return $this->expands;
    }

    public function getQuery(): Builder
    {
        return $this->applySort($this->applyFilters($this->applyExpands(call_user_func([$this->model,'query']))));
    }

    protected function getFillable(): array
    {
        return call_user_func([(new $this->model),'getFillable']);
    }
}
