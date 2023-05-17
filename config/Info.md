# Configuration Details

## Basic
1. Copy `config.php.example` to `config.php`
2. Replace all Values within `< ... >` with the Values fitting your Installation
    - `bookstack`/`baseUri` -> Url from APP_URL in the `.env` file
    - `bookstack`/`api`/`id` -> The Id of the API-Token
    - `bookstack`/`api`/`secret` -> The Secret of the API-Token
```php
$GLOBALS['config'] = array(
    'global' => array(
        'debug' => false,
        'logDir' => $_SERVER['DOCUMENT_ROOT'] . '../log/'
    ),
    'bookstack' => array(
        'apiRateLimit' => '180',
        'baseUri' => 'https://bookstack.example.com',
        'ignorCertificate' => true,
        'api' => array(
            'id' => 'AoZ5W9gqj3f9B8BQhQlKaSQ7qIC1zb00',
            'secret' => 'od995W5iE8ZjFzbUkEnBqtsmkwu5hnaX'
        ),
        'permission' => array(
            'tag' => 'prem',
            'entrySeperator' => '|',
            'permissionIdentifier' => '=',
            'ignoreFallbackTag' => 'igf'
        )
    )
);
```

## Debugging
- `global`/`logDir` -> The Directory where the Debug-Logs are saved
- `global`/`debug` -> `true` to enable debugging, `false` to disable debugging

## Permission
- `bookstack`/`permission`/`tag` -> Specifies the Tag which contains the Permission-Entries
- `bookstack`/`permission`/`entrySeperator` -> Used to seperate the individual Permission-Entries
- `bookstack`/`permission`/`permissionIdentifier` -> Used to seperate the Permission from the Role of a Permission-Entry
- `bookstack`/`permission`/`ignorFallbackTag` -> Used to disbale copying the "Everyone Else" Permissions
