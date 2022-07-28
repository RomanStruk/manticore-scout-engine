# Manticore Scout Engine
[![Release](https://img.shields.io/badge/Release-v2.0.1-green?style=flat-square)](https://github.com/RomanStruk/Kaca/releases)

Manticore Engine for Laravel Scout

## Installation

Via Composer

``` bash
$ composer require romanstruk/manticore-scout-engine
```

After installing Manticore Scout Engine, you should publish the Manticore configuration file using the vendor:publish Artisan command. This command will publish the manticore.php configuration file to your application's config directory:

```bash
php artisan vendor:publish --provider="RomanStruk\ManticoreScoutEngine\ManticoreServiceProvider"
```
## Usage
Documentation for Scout can be found on the Laravel website.

Run artisan command for create Manticore index
```bash
php artisan manticore:index
```

Manticore allows you to add "whereRaw" methods to your search queries.
```php
$products = Product::search('Brand Name')->whereRaw('category_id ANY (?, ?, ?)', ['1', '2', '3'])->get();
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
