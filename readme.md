# Manticore Scout Engine
[![Release](https://img.shields.io/github/v/release/RomanStruk/manticore-scout-engine?style=flat-square)](https://github.com/RomanStruk/manticore-scout-engine/releases)

Manticore Engine for Laravel Scout

## Installation

Via Composer

``` bash
$ composer require romanstruk/manticore-scout-engine
```
## Configuration
After installing Manticore Scout Engine, you should publish the Manticore configuration file using the vendor:publish Artisan command. This command will publish the manticore.php configuration file to your application's config directory:

```bash
php artisan vendor:publish --provider="RomanStruk\ManticoreScoutEngine\ManticoreServiceProvider"
```
```bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```
### Configuring Search Driver
Set up your search driver `manticore` in `.env` file
```dotenv
SCOUT_DRIVER=manticore
```
There is a choice between two ways to connect to the manticore
* http-client - `\Manticoresearch\Client` [github](https://github.com/manticoresoftware/manticoresearch-php)
* mysql-builder - `\RomanStruk\ManticoreScoutEngine\Builder` use mysql connection 

Set up your engine in `.env` file
```dotenv
MANTICORE_ENGINE=http-client
```
### Configuring Driver Connection
For `http-client` in `.env` file
```dotenv
MANTICORE_HOST=localhost
MANTICORE_PORT=9308
```
For `mysql-builder` in `.env` file
```dotenv
MANTICORE_MYSQL_HOST=127.0.0.1
MANTICORE_MYSQL_PORT=9306
```

### Configuring Model Migration
To create a migration, specify the required fields in the searchable model
```php
public function scoutIndexMigration(): array
{
    return [
        'fields' => [
            'id' => ['type' => 'bigint'],
            'name' => ['type' => 'text'],
        ],
        'settings' => [
            'min_prefix_len' => '3',
            'min_infix_len' => '3',
            'prefix_fields' => 'name',
            'expand_keywords' => '1',
        ],
    ];
}
```

### Configuring query options
`max_matches` - Maximum amount of matches that the server keeps in RAM for each index and can return to the client. Default is 1000.

For queries with pagination, you can specify automatic parameter calculation `max_matches`
Set up your `paginate_max_matches` in `manticore.php` config file
```php
'paginate_max_matches' => 1000,
```
Set `null` for calculate offset + limit

Other parameters for queries can be specified in the model
```php
public function scoutMetadata(): array
{
    return [
        'cutoff' => 0,
        'max_matches' => 1000,
    ];
}
```
Config `paginate_max_matches` has higher priority than `scoutMetadata` `max_matches` option

## Usage
Documentation for Scout can be found on the Laravel website.

Run artisan command for create Manticore index
```bash
php artisan manticore:index "App\Models\Product"
```

Manticore allows you to add "whereRaw" methods to your search queries.
```php
$products = Product::search('Brand Name')->whereAny('category_id', ['1', '2', '3'])->get();
```

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email romanuch4@gmail.com instead of using the issue tracker.

## License

MIT. Please see the [license file](license.md) for more information.
