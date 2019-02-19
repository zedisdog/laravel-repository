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

interface RepositoryInterface
{
    /**
     * 返回所有数据
     * @param array $columns
     * @return mixed
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * 返回分页数据
     * @param int $perPage
     * @param array $columns
     * @return LengthAwarePaginator
     *
     * @deprecated 1.1.2
     */
    public function paginate(?\Closure $callback = null, int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * 创建记录
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model;

    /**
     * 更新记录
     * @param array $data
     * @param integer|Model $model
     * @return Model
     */
    public function update(array $data, $model): Model;

    /**
     * 删除记录
     * @param integer|Model $model
     * @return bool|null
     */
    public function delete($model): ?bool;

    /**
     * 查找单条记录
     * @param integer|Model $model
     * @param array $columns
     * @return Model
     */
    public function find($model, array $columns = ['*']): ?Model;

    /**
     * 根据简单条件查找数据
     * @param  string|array|\Closure  $column
     * @param  mixed   $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return Model|null
     *
     */
    public function findBy($column, $operator = null, $value = null, $boolean = 'and'): ?Model;

    /**
     * get query object
     * @return Builder
     */
    public function getQuery(): Builder;

    public function exists(...$args): bool;
}
