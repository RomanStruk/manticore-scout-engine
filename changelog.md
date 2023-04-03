# Changelog

All notable changes to `romanstruk/manticore-scout-engine` will be documented in this file.

## Version 4

### 4.5.3 (03.04.2023)
- mysql reconnecting
- configure auto escaping search phrase `config('manticore.auto_escape_search_phrase')`

### 4.5.2 (22.03.2023)
- [bug] bind double to PARAM_INT is returned

### 4.5.1 (22.03.2023)
- readme update

### 4.5.0 (21.03.2023)
- Quorum matching operator
- Proximity search operator
- Fix delete index
- Added `orWhrere`, `whereNotIn`, `whereNotAny`, `whereNotAll` 

### 4.4.1 (08.03.2023)
- Bind Value for float value `PDO::PARAM_STR`

### 4.4.0 (17.12.2022)
- Додано facet distinct `distinctFacet('brand_name', 'property')` `FACET brand_name distinct property;`
- Додано facet по виразу `facetExpressions('INTERVAL(price,200,400,600,800)', 'price_range')` `FACET INTERVAL(price,200,400,600,800) AS price_range;`
- Додано select для виразів та конкретних полів `select(['*'])` `selectRaw('INTERVAL(price,200,400) as price_range')`

### 4.3.0 (22.11.2022)
- Додано групування по полю `groupBy('field_name')`

### 4.2.0 (07.11.2022)
- Виправлено видалення індекса
- Додано сортування в рандомному порядку `inRandomOrder()`, по вазі `inWeightOrder()`
- Додано можливість логування запитів `app(ManticoreConnection::class)->enableQueryLog();` та вивід результатів логування `app(ManticoreConnection::class)->getQueryLog();`

### 4.1.0 (31.10.2022)
- Додані оператори `in` `not in` для вибірок `whereAllMva` `whereAnyMva`

### 4.0.5 (31.10.2022)
- виправлення truncate

### 4.0.4 (31.10.2022)
- виправлення з total_found для пагінації
- виправлення bool для методу when 

### 4.0.0 (30.10.2022)

- Додана альтернатива для стандартного клієнта у вигляді laravel подібного builder'а з підключення до мантікори через порт mysql
- Змінено файл конфігурації
- Доповнено readme
- реалізовано whereAnyMva