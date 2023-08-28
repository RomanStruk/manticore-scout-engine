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
            'category' => ['type' => 'string stored indexed'],// string|text [stored|attribute] [indexed]
        ],
        'settings' => [
            'min_prefix_len' => '3',
            'min_infix_len' => '3',
            'prefix_fields' => 'name',
            'expand_keywords' => '1',
            //'engine' => 'columnar', // [default] row-wise - traditional storage available in Manticore Search out of the box; columnar - provided by Manticore Columnar Library
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

As some characters are used as operators in the query string, they should be escaped to avoid query errors or unwanted matching conditions.
Set up your `auto_escape_search_phrase` in `manticore.php` config file
```php
'auto_escape_search_phrase' => true,
```
Set `false` for disable auto escape special symbols `!    "    $    '    (    )    -    /    <    @    \    ^    |    ~`

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
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;

$products = Product::search('Brand Name', function (Builder $builder) {
    return $builder
        ->whereAny('category_id', ['1', '2', '3'])
        ->facet('category_id')
        ->inRandomOrder();
})->get();
```

Quorum matching operator introduces a kind of fuzzy matching. It will only match those documents that pass a given threshold of given words. The example above ("the world is a wonderful place"/3) will match all documents that have at least 3 of the 6 specified words.
```php
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;

$products = Product::search('the world is a wonderful place', function (Builder $builder) {
    return $builder->setQuorumMatchingOperator(3);
})->get();
```

Proximity distance is specified in words, adjusted for word count, and applies to all words within quotes. For instance, "cat dog mouse"~5 query means that there must be less than 8-word span which contains all 3 words, ie.
```php
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;

$products = Product::search('cat dog mouse', function (Builder $builder) {
    return $builder->setProximitySearchOperator(5);
})->get();
```

Autocomplete (or word completion) is a feature in which an application predicts the rest of a word a user is typing. On websites, it's used in search boxes, where a user starts to type a word, and a dropdown with suggestions pops up so the user can select the ending from the list.
```php
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;

//[doc] My cat loves my dog. The cat (Felis catus) is a domestic species of small carnivorous mammal.

$autocomplete = Product::search('my*',function (Builder $builder) {
    return $builder->autocomplete(['"','^'], true); // "" ^ * allow full-text operators; stats - Show statistics of keywords, default is 0
})->raw();
// $autocomplete<array> "my", "my cat", "my dog"
```

Spell correction
```php
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;

//[doc] Crossbody Bag with Tassel
//[doc] microfiber sheet set
//[doc] Pet Hair Remover Glove

$result = Product::search('bagg with tasel',function (Builder $builder) {
    return $builder->spellCorrection(true) // correct first word
})->raw();
// $result<array> 0 => ['suggest' => "bag"]

$result = Product::search('bagg with tasel',function (Builder $builder) {
    return $builder->spellCorrection() // correct last word
})->raw();
// $result<array> 0 => ['suggest' => "tassel"]

$result = Product::search('bagg with tasel',function (Builder $builder) {
    return $builder->spellCorrection(false, true) // correct last word and return sentence
})->raw();
// $result<array> 0 => ['suggest' => "bagg with tassel"]
```
### Percolate Query
To create a migration, specify the required fields in the searchable model
```php
public function scoutIndexMigration(): array
{
    return [
            'fields' => [
                'title' => ['type' => 'text'],
                'color' => ['type' => 'string'],
            ],
            'settings' => [
                'type' => 'pq'
            ],
    ];
}

public function toSearchableArray(): array
{
    return array_filter([
        'id' => $this->name,
        'query' => "@title {$this->title}",
        'filters' => $this->color ? "color='{$this->color}'" : null,
    ]);
}
```

Percolate queries are also known as Persistent queries, Prospective search, document routing, search in reverse, and inverse search.
https://manual.manticoresearch.com/Searching/Percolate_query#Percolate-Query
```php
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;

$products = PercolateProduct::search(json_encode(['title' =>'Beautiful shoes']),
    fn(Builder $builder) => $builder->percolateQuery(docs: true, docsJson: true)
)->get();
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
