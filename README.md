# Call

Вызыватор mysql процедур.

## Установка

`composer require "nazarpunk/call:^1.0"`

[packagist.org](https://packagist.org/packages/nazarpunk/call)

## Соединение
Соединения ленивыи и происходят в момент самого запроса.

```php
use nazarpunk\Call\Call;

// Соединение для dev 
Call::set_connection([
    'name'     => 'dev',
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => 'my_password',
    'database' => 'my_database_dev',
    'port'     => null,
    'socket'   => null,
    'charset'  => 'utf8mb4',
    'report'   => MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_INDEX
]);

// Соединение для production
Call::set_connection([
    'name'     => 'prod',
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => 'my_password',
    'database' => 'my_database',
    'port'     => null,
    'socket'   => null,
    'charset'  => 'utf8mb4',
    'report'   => MYSQLI_REPORT_OFF
]);

// Соединение по умолчанию 
Call::use_connection('dev');

// Соединение для отдельно взятой процедуры
$call = (new Call('my_procedure'))->connection('prod');
```

## Переменные
```php
use nazarpunk\Call\Call;

$call = new Call('my_procedure');

$call->variable('a','now()','raw')
        ->variable('b','now()','quote')
        ->variable('a','now()','escape');

$call->execute();
```

```mysql
set @a := now();
set @b := 'now()';
set @c := 'now()';
call `my_procedure`();
```

## Аргументы
```php
use nazarpunk\Call\Call;
$call = new Call('my_procedure');

$call->argument(3, 'c', 'raw')
	     ->argument(2, 'b', 'quote')
	     ->argument(1, 'a', 'escape');
	     
$call->execute();
```

```mysql
call `my_procedure`('a', 'b', c);
```

## Выборка
```php
use nazarpunk\Call\Call;

$options = [
  'format'  => 'escape', // Форматирование переменных/аргументов по умолчанию
  'type'    => true, // Приведение типов в выборке
  'null'    => true, // Избавляться от null в выборке
  'boolean' => true // приводить tinyint(1) к boolean
]; 

// Параметры по умолчанию
Call::set_options($options);

// Выборка
$results = (new Call('my_procedure'))->execute($options);
```

