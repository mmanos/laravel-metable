# Meta Package for Laravel 5

This package adds meta support to your Laravel application. You can configure it to attach meta to any of your existing Eloquent models.

## Installation

#### Composer

Add this to your composer.json file, in the require object:

```javascript
"mmanos/laravel-metable": "dev-laravel-5"
```

After that, run composer install to install the package.

#### Service Provider

Register the `Mmanos\Metable\MetableServiceProvider` in your `config/app.php` configuration file.

## Configuration

#### Metas Migration and Model

First you'll need to publish a `metas` table and a `Meta` model. This table will hold a summary of all meta created by your metable models.

```console
$ php artisan laravel-metable:metas metas
```

> **Note:** Modify the last parameter of this call to change the table/model name.

> **Note:** You may publish as many meta tables as you need, if you want to keep the meta separate for different types of content, for example.

#### Metable Migration

Next, publish a migration for each type of content you want to attach meta to. You may attach meta to as many types of content as you wish. For example, if you want to be able to add meta to both a `users` table and a `blog_posts` table, run this migration once for each table.

```console
$ php artisan laravel-metable:metable user_metas
```

#### Run Migrations

Once the migration has been created, simply run the `migrate` command.

#### Model Setup

Next, add the `Metable` trait to each metable model definition:

```php
use Mmanos\Metable\Metable;

class User extends Eloquent
{
	use Metable;
}
```

Then you need to specify the meta model as well as the metable table to use with your model:

```php
class User extends Eloquent
{
	protected $meta_model = 'Meta';
	protected $metable_table = 'user_metas';
}
```

#### Syncing Custom Attributes

Sometimes you will want to have some of the same fields in your content table synced to the metable table records. This will allow you to filter and sort by these attributes when querying the metable table. Luckily this system will automatically sync any fields you define to the metable table records any time there are changes.

To get started, **modify the metable migration file** to include your additional fields.

Then, tell your model which fields it needs to sync:

```php
class User extends Eloquent
{
	protected $metable_table_sync = ['company_id', 'created_at', 'updated_at', 'deleted_at'];
}
```

Now every time you create or update a model, these fields will by synced to all metable table records for the piece of content.

#### Syncing Deleted Content

This package will automatically delete all metable table records for a piece of content when that piece of content is deleted.

If you are using the `SoftDeletingTrait` and you are syncing the `deleted_at` column to your metable table records, this package will automatically soft-delete all metable table records for a piece of content when that piece of content is deleted. If the content is restored, then the metable table records are restored as well.

## Working With Meta

#### Setting Content Meta

To set a meta value on an existing piece of content:

```php
$user->setMeta('employer', 'Company, Inc.');
```

Or set multiple metas at once:

```php
$user->setMeta([
	'employer' => 'Company, Inc.',
	'employed_for_years' => 10,
]);
```

> **Note:** If a piece of content already has a meta the existing value will be updated.

#### Unsetting Content Meta

Similarly, you may unset meta from an existing piece of content:

```php
$user->unsetMeta('employer');
```

Or unset multiple metas at once:

```php
$user->unsetMeta('employer', 'employed_for_years');
// or
$user->unsetMeta(['employer', 'employed_for_years']);
```

> **Note:** The system will not throw an error if the content does not have the requested meta.

#### Checking for Metas

To see if a piece of content has a meta:

```php
if ($user->hasMeta('employer')) {
	
}
```

#### Retrieving Meta

To retrieve a meta value on a piece of content, use the `meta` method:

```php
$employer = $user->meta('employer');
```

Or specify a default value, if not set:

```php
$employer = $user->meta('employer', 'Unemployed');
```

You may also retrieve more than one meta at a time and get back an array of values:

```php
$employer = $user->meta(['employer', 'employed_for_years']);
```

#### Retrieving All Metas

To fetch all metas associated with a piece of content, use the `metas` relationship:

```php
$metas = $user->metas;
```

#### Retrieving an Array of All Metas

To fetch all metas associated with a piece of content and return them as an array, use the `metasArray` method:

```php
$metas = $user->metasArray();
```

## Querying for Content from Meta

#### Performing Queries

Now let's say you want to query for all content that has a given meta:

```php
$users = User::withMeta('employer')->take(10)->get();
```

Or optionally specify the meta value to match:

```php
$users = User::whereMeta('employer', 'Company, Inc.')->take(10)->get();
```

Or optionally specify the meta value and operator to match:

```php
$users = User::whereMeta('employed_for_years', '>', '5')->take(10)->get();
```

These queries extend the same `QueryBuilder` class that you are used to working with, so all of those methods work as well:

```php
$users = User::whereMeta('employer', 'Company, Inc.')
	->where('meta_created_at', '>', '2015-01-01 00:00:00')
	->with('company')
	->orderBy('meta_created_at', 'desc')
	->paginate(10);
```

> **Note:** The `update` and `delete` methods on a QueryBuilder object do not work for these queries.

You may query for content that has any of the given meta:

```php
$users = User::withAnyMeta('company', 'employed_for_years')->get();
// or
$users = User::withAnyMeta(['company', 'employed_for_years'])->get();
```

Or query for content that has any of the given meta that matches the given values:

```php
$users = User::whereAnyMeta([
	'company' => 'Company, Inc.',
	'employed_for_years' => '10'
])->get();
```

> **Note:** Query performance can be reduced for the `withAnyMeta` and `whereAnyMeta` queries if your queries match thousands of records or more.

And you may combine multiple filters:

```php
// Fetch all users who have the 'agent' meta and who have 'company' or 'employed_for_years'.
$users = User::whereMeta('agent', '1')->withAnyMeta('company', 'employed_for_years')->get();
```

#### Meta Contexts

Sometimes you might want to associate your metas (summary) table records with some custom context for your application. For example, say you have a `companies` table and a `users` table and each user belongs to a company. And now you also want to associate each meta record with a company allowing you to fetch all meta used by each individual company. In order to do so, we have to tell this package to be aware of this company context and modify it's queries accordingly.

To get started, make sure you **modify your metas migration** to include any context fields (`company_id`, in this case). You might also need to update the unique index, if necessary.

Then modify your metable model by adding a `metaContext` method:

```php
class User extends Eloquent
{
	public function metaContext()
	{
		return $this->company;
	}
}
```

Next modify your `Meta` model (or whatever name you specified during configuration) to apply any contexts:

```php
class Meta extends Eloquent
{
	public static function applyQueryContext($query, $context)
	{
		$query->where('company_id', $context->id);
	}
	
	public static function applyModelContext($model, $context)
	{
		$model->company_id = $context->id;
	}
}
```

The `applyQueryContext` method will adjust any meta queries used by this package to filter on `company_id`.

The `applyModelContext` method is called when creating a new `Meta` record and should set any required context fields.

Finally, when performing queries, specify the context to apply:

```php
$users = User::withMeta('employer')->withMetaContext($company)->take(10)->get();
```
