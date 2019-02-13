# laravel repository

[![License](http://www.wtfpl.net/wp-content/uploads/2012/12/wtfpl-badge-1.png)](LICENSE)

这个包抽象出数据层，让应用更好维护。

- [ ] 测试

## 特性

* 类似 `yii` 的 扩展字段：你可以在 url 后面添加这 `expands=xxx,xx` 样的 query string 来获取关联的模型数据。
* 过滤器：你可以在url后面添加 `filters[fieldName]=xxx&filters[fieldName2]=xx` 这样的 query string 来设置简单的数据库查询条件。
* 自定义过滤器：你可以自己创建或者覆盖已有的过滤器。
* 排序：你可以在url后面添加 `sorts[name]=asc` 这样的 query string 来设置简单的数据库排序条件。
* 自定义过排序：你可以自己创建或者覆盖已有的排序。

## 安装
```bash
composer require dezsidog/laravel-repository
```

## 用法

### 创建模型

```php
namespace App;

class Post extends Eloquent {

    protected $fillable = [
        'title',
        'author',
        ...
     ];

     ...
}
```

### 创建仓库
```php
namespace App\Repository;

use Dezsidog\Repository;
use App\Post;

class PostRepository extends Repository {
    protected $model = Post::class;
}
```

### 将仓库注入到控制器中

```php
namespace App\Http\Controllers;

use App\Repository\PostRepository;

class PostsController extends BaseController {

    /**
     * @var PostRepository
     */
    protected $posts;

    public function __construct(PostRepository $repository){
        $this->posts = $repository;
    }
}
```

### 接着你可以做以下操作

```php
public function someMethod(){
    $this->posts->find();
    $this->posts->findBy();
    $this->posts->all();
    $this->posts->paginate(); // 弃用，请直接使用getQuery()
    $this->posts->create();
    $this->posts->update();
    $this->posts->delete();
    $this->posts->getQuery();
}
```

## 扩展字段

如果模型有下面两个关联

```php
namespace App;

use App\User;
use App\Type;

class Post extends Eloquent {

    protected $fillable = [
        'title',
        'author',
        'user_id',
        'type_id',
        ...
     ];

    public function user(){
        return $this->belongTo(User::class);
    }
    
    public function type(){
        return $this->belongTo(Type::class);
    }
     ...
}
```

### 你可以

* 将扩展字段像下面这样添加到url里面

```
https://www.xxxx.com/path/to/post-list?expands=user,type
```

* 或者在仓库类中设置自动加载这些扩展字段

```php
namespace App\Repository;

use Dezsidog\Repository;
use App\Post;

class PostRepository extends Repository {
    protected $model = Post::class;
    protected $expands = [
        'user',
        'type',
    ];
}
```

## 过滤器

你可以像下面这样搜索
```
https://www.xxxx.com/path/to/post-list?filters[type_id]=3&filters[user_id]=1
```

你也可以通过关联到的模型字段来做搜索

```
https://www.xxxx.com/path/to/post-list?filters[type.id]=3&filters[user.id]=1
```

### 自定义过滤器

在仓库类中添加方法来设置自定义过滤器，方法名为：`filterBy + 键名` （注意命名格式不能变）。

```php
namespace App\Repository;

use Dezsidog\Repository;
use App\Post;
use Illuminate\Database\Eloquent\Builder;

class PostRepository extends Repository {
    protected $model = Post::class;
    
    public function filterByNameOrTypeName(Builder $query, $value){
        $query->leftJoin('users', 'users.id', '=', 'posts.user_id')
            ->leftJoin('types', 'type.id', '=', 'posts.type_id')
            ->where(function(Builder $query) use ($value) {
                $query->where('users.username', 'like', '%'.$value.'%')
                    ->orWhere('types.name', 'like', '%'.$value.'%')
            });
    }
}
```

接着在url中这样使用你的过滤器
```
https://www.xxxx.com/path/to/post-list?filters[name-or-type-name]=x
```

### 你也可以覆盖默认的过滤器
```php
namespace App\Repository;

use Dezsidog\Repository;
use App\Post;
use Illuminate\Database\Eloquent\Builder;

class PostRepository extends Repository {
    protected $model = Post::class;
    
    public function filterByTitle(Builder $query, $value){
        $query->where('posts.title', '=', $value);
    }
}
```
## 排序

你可以感觉当前模型的字段设置排序
```
https://www.xxxx.com/path/to/post-list?sorts[type_id]=asc&sorts[user_id]=desc
```

排序不能使用关联的字段。

### 自定义排序

在仓库类中添加方法来设置自定义排序，方法名为：`sortBy + 字段名` （注意命名格式不能变）。

```php
namespace App\Repository;

use Dezsidog\Repository;
use App\Post;
use Illuminate\Database\Eloquent\Builder;

class PostRepository extends Repository {
    protected $model = Post::class;
    
    public function sortByName(Builder $query, $value){
        $query->orderby('name',$value);
    }
}
```

在url中使用
```
https://www.xxxx.com/path/to/post-list?sorts[name]=asc|desc
```

你同样可以覆盖默认的排序。
