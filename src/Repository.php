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
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Debug\Exception\ClassNotFoundException;

abstract class Repository implements RepositoryInterface
{
    protected $model;

    protected $expands = [];

    protected $noFilters = false;

    /**
     * Repository constructor.
     * @throws ClassNotFoundException
     */
    public function __construct()
    {
        if (!$this->model || !class_exists($this->model)) {
            throw new ClassNotFoundException("undefined class {$this->model}",new \ErrorException());
        }
    }

    /**
     * @param Builder $query
     * @return Builder
     */
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
            $sort_method = 'sortBy'.ucfirst(Str::camel($key));
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

    /**
     * @param Builder $query
     * @return Builder
     * @throws \ReflectionException
     */
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
            $filter_method = 'filterBy'.ucfirst(Str::camel($key));
            if (strpos($key,'.') === false && method_exists($this, $filter_method)) {
                $reflect = new \ReflectionMethod(static::class, $filter_method);
                if ($reflect->getNumberOfParameters() == 3) {
                    $this->$filter_method($query, $value, $filters);
                } else {
                    $this->$filter_method($query,$value);
                }
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
                        if (method_exists($relation, 'getQualifiedForeignKeyName')) {
                            list($current_table,$field) = explode('.',$relation->getQualifiedForeignKeyName());
                        } else {
                            list($current_table,$field) = explode('.',$relation->getQualifiedForeignKey());
                        }

                        if (!empty($query->getQuery()->joins) && !in_array($current_table,$query->getQuery()->joins)) {
                            $query->leftJoin($current_table,$field,'=',$relation->getQualifiedParentKeyName());
                        }
                    }
                }

            }
        }

        return $query;
    }

    /**
     * @param array $columns
     * @return Collection
     * @throws \ReflectionException
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->getQuery()->get($columns);
    }

    public function create(array $data): Model
    {
        $fields = Arr::only($data,$this->getFillable());
        /**
         * @var Model $model
         */
        $model = call_user_func_array([$this->model,'create'],[$fields]);
        $model = $model->fresh($this->getExpands());
        $model->wasRecentlyCreated = true;
        return $model;
    }

    /**
     * @param Model|int $model
     * @param array $columns
     * @return Model|null
     * @throws \ReflectionException
     */
    public function find($model, array $columns = ['*']): ?Model
    {
        if (!($model instanceof Model)) {
            return $this->getQuery()->find($model, $columns);
        }else{
            return $model->fresh($this->getExpands());
        }
    }

    /**
     * 判断给定条件的记录是否存在
     * @param mixed ...$args
     * @return bool
     * @throws \ReflectionException
     */
    public function exists(...$args): bool
    {
        return $this->getQuery()->where(...$args)->exists();
    }

    public function update(array $data, $model): Model
    {
        if (!($model instanceof Model)) {
            $model = call_user_func_array([$this->model,'find'],[$model]);
        }
        $fields = Arr::only($data,$this->getFillable());
        /**
         * @var Model $model
         */
        $model->update($fields);
        return $model->fresh($this->getExpands());
    }

    /**
     * 根据简单条件查找数据
     * @param string|array|\Closure $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return Model|null
     * @throws \ReflectionException
     */
    public function findBy($column, $operator = null, $value = null, $boolean = 'and'): ?Model
    {
        return $this->getQuery()->where(...func_get_args())->first();
    }

    /**
     * @param $model
     * @return bool|null
     * @throws \Exception
     */
    public function delete($model): ?bool
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
     * @throws \ReflectionException
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

    /**
     * @return Builder
     * @throws \ReflectionException
     */
    public function getQuery(): Builder
    {
        if ($this->noFilters) {
            return $this->applySort($this->applyExpands(call_user_func([$this->model,'query'])));
        } else {
            return $this->applySort($this->applyFilters($this->applyExpands(call_user_func([$this->model,'query']))));
        }
    }

    protected function getFillable(): array
    {
        return call_user_func([(new $this->model),'getFillable']);
    }

    /**
     * 保存给定的模型
     * @param Model $model
     * @return bool
     */
    public function save(Model $model): bool
    {
        return $model->save();
    }

    /**
     * @param array $data
     * @return Model
     */
    public function freshModel(array $data = []): Model
    {
        /** @var Model $model */
        $model = new $this->model;
        $model->fill(Arr::only($data, $model->getFillable()));
        return $model;
    }

    /**
     * @param bool $bool
     * @return static
     */
    public function noFilter(bool $bool = true)
    {
        $this->noFilters = $bool;
        return $this;
    }

    /**
     * @param array $attr
     * @param array $values
     * @return Model
     */
    public function firstOrCreate(array $attr, array $values = []): Model
    {
        return $this->model::firstOrCreate($attr, $values);
    }

    public function updateOrCreate($attr, $values = []): Model
    {
        return $this->model::updateOrCreate($attr, $values);
    }

    public function firstOrNew(array $attr, array $values = []): Model
    {
        return $this->model::firstOrNew($attr, $values);
    }
}
