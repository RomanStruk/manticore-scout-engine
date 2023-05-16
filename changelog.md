# Changelog

All notable changes to `romanstruk/manticore-scout-engine` will be documented in this file.

## Version 5

### 5.2.8 (16.05.2023)
- fix facet by

### 5.2.7 (06.04.2023)
- update `readme.md`
- fix `getElapsedTime()` type hint
- update package version

### 5.2.6 (06.04.2023)
- [bug] reconnect mysql

### 5.2.5 (04.04.2023)
- default config for mysql builder `auto_escape_search_phrase`

### 5.2.4 (03.04.2023)
- mysql reconnecting
- configure auto escaping search phrase `config('manticore.auto_escape_search_phrase')`

### 5.2.2 (22.03.2023)
- [bug] bind double to PARAM_INT is returned

### 5.2.1 (22.03.2023)
- readme update

### 5.2.0 (22.03.2023)
- Quorum matching operator
- Proximity search operator
- Fix delete index
- Added `orWhrere`, `whereNotIn`, `whereNotAny`, `whereNotAll`

### 5.1.1 (08.03.2023)
- Bind Value for float value `PDO::PARAM_STR`

### 5.1.0 (17.12.2022)
- Додано facet distinct `distinctFacet('brand_name', 'property')` `FACET brand_name distinct property;`
- Додано facet по виразу `facetExpressions('INTERVAL(price,200,400,600,800)', 'price_range')` `FACET INTERVAL(price,200,400,600,800) AS price_range;`
- Додано select для виразів та конкретних полів `select(['*'])` `selectRaw('INTERVAL(price,200,400) as price_range')`

### 5.0.0 (17.12.2022)
- Support php8.1