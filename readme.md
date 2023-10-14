# Laravel Trello Commands

Artisan commands to manage Trello lists and cards.

## Requirements

``` json
"php": "^8.1",
"laravel/framework": "^10.10"
````

## Installation

Via Composer

``` bash
composer require --dev mattreesjenkins/laravel-trello-commands
```
You will need a Trello Auth Token which you can get  [here](https://trello.com/power-ups/admin) and add it to your .env file.
``` bash
TRELLO_AUTH_TOKEN=xxx
```

There is a single table used to store the ids and names of the current workspace, board, and list. You will need to run the migration to set this up.

``` bash
php artisan migrate
```

## Usage

From the console enter:

```php artisan todo```

To reset the current workspace, board, and list, enter:

```php artisan todo --configure```

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Roadmap

- Probably should write some tests :grimacing:
- Additional commands

## Credits

- [Matt Rees-Jenkins][link-author]

## License

Laravel Prompts is open-sourced software licensed under the [MIT license](license.md).

[link-author]: https://github.com/mattreesjenkins
