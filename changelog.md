# Changelog

All notable changes to `romanstruk/manticore-scout-engine` will be documented in this file.

## Version 4

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

## Version 3

### 3.0.0 (13.10.2022)
- Змінений формат міграції
```php
    public function scoutIndexMigration(): array
    {
        return [
            'fields' => [
                'name' => ['type' => 'text'],
            ],
            'settings' => [
                'min_prefix_len' => '3',
                'min_infix_len' => '3',
                'prefix_fields' => 'name',
                'expand_keywords' => '1',
            ],
            'silent' => false,
        ];
    }
```

## Version 2

### 2.0.1 (28.08.2022)
- Виправлення помилки з whereRaws
- Доповнена документація

### 2.0.0 (27.08.2022)
- Sql синтаксис

## Version 1

### 1.1.0 (24.08.2022)
- Додано налаштування max_matches
- Виправлено raw(), дані віддаються масивом

### 1.0.1 (22.08.2022)
- Додано публікацію файла налаштування
- readme.md

### 1.0.0 (22.08.2022)
- Реліз базового функціоналу
