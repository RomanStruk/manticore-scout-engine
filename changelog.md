# Changelog

All notable changes to `romanstruk/manticore-scout-engine` will be documented in this file.

## Version 5

### 5.1.0 (17.12.2022)
- Додано facet distinct `distinctFacet('brand_name', 'property')` `FACET brand_name distinct property;`
- Додано facet по виразу `facetExpressions('INTERVAL(price,200,400,600,800)', 'price_range')` `FACET INTERVAL(price,200,400,600,800) AS price_range;`
- Додано select для виразів та конкретних полів `select(['*'])` `selectRaw('INTERVAL(price,200,400) as price_range')`

### 5.0.0 (17.12.2022)
- Support php8.1