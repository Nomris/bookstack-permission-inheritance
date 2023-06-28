# Configuration Details

## Web Server
The Web-Root of the Web-Server must point to the `public` directory.

## Bookstack Configuration
In Bookstack two Webhook's must be added:
1. A Webhook that gets executed on the following events, targeting `{Web-Root}/permission.php?t=cc` to set Permissions when creating new Objects 
    + page_create
    + chapter_create
    + book_create
2. A Webhook that gets executed on the following events, targeting `{Web-Root}/permission.php?t=pc` to set Permissions when Permissions have been changed 
    + permissions_update

## Basic
1. Copy `config.php.example` to `config.php`
2. Replace all Values within `< ... >` with the Values fitting your Installation
    - `bookstack/baseUri` -> Url from APP_URL in the `.env` file
    - `bookstack/api/id` -> The Id of the API-Token
    - `bookstack/api/secret` -> The Secret of the API-Token

### Example
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
- `global/logDir` -> The Directory where the Debug-Logs are saved
- `global/debug` -> `true` to enable debugging, `false` to disable debugging

## Permission
- `bookstack/permission/tag` -> Specifies the Tag which contains the Permission-Entries
- `bookstack/permission/entrySeperator` -> Used to seperate the individual Permission-Entries
- `bookstack/permission/permissionIdentifier` -> Used to seperate the Permission from the Role of a Permission-Entry
- `bookstack/permission/ignorFallbackTag` -> Used to disbale copying the "Everyone Else" Permissions

A Permission-Entry is constructed as described in the [Usage Document](../USAGE.md#permission-entry)
