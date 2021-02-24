# Call

Вызыватор mysql процедур.

## Соединение

```php
Call::set_connection([
    'name'     => 'main',
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => 'my_password',
    'database' => 'my_database',
    'port'     => null,
    'socket'   => null,
    'charset'  => 'utf8mb4',
    'report'   => MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_INDEX
]);

Call::set_options([
    'format'  => 'escape',
    'type'    => true,
    'null'    => true,
    'boolean' => true
]);
```