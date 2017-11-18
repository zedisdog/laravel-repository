# laravel repository

[![License](http://www.wtfpl.net/wp-content/uploads/2012/12/wtfpl-badge-1.png)](LICENSE)

laravel repositories is used to abstract the data layer, making our application more flexible to maintain.

> bad english

> will be tested soon.

## feature

* yii expands.you can append `expands=xxx,xx` to url for extra fields by call the relation methods
* filters.you can append `filters[fieldName]=xxx&filters[fieldName2]=xx` to url for search records by given condition.
* custom filters.you can add or cover the filter methods.

## install
```bash
composer require dezsidog/laravel-repository
```

## usage

### create model

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

### create repository
```php
namespace App\Repository;

use Dezsidog\Repository;
use App\Post;

class PostRepository extends Repository {
    protected $model = Post::class;
}
```

### add repository to controller

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

### then you can

```php
public function someMethod(){
    $this->posts->find(...);
    $this->posts->findBy(...);
    $this->posts->all();
    $this->posts->paginate(...);
    $this->posts->create(...);
    $this->posts->update(...);
    $this->posts->delete(...);
}
```

## expands

if model has relations.

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

### you can

* just append expands field to url:

```
https://www.xxxx.com/path/to/post-list?expands=user,Type
```

* or make expands auto

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

## filters

to search something by
```
https://www.xxxx.com/path/to/post-list?filters[type_id]=3&filters[user_id]=1
```

you can use relations

```
https://www.xxxx.com/path/to/post-list?filters[type.id]=3&filters[user.id]=1
```

### custom filters

add method named `filterBy + key name`, to custom filters.

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

then make url:
```
https://www.xxxx.com/path/to/post-list?filters[name-or-type-name]=x
```

### you can just cover the exists field
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
