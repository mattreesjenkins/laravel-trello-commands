# Laravel Trello Commands

Artisan commands to manage Trello lists and cards.

## Installation

Via Composer

``` bash
composer require --dev mattreesjenkins/laravel-trello-commands
```
You will need a Trello Auth Token which you can get  [here](https://trello.com/power-ups/admin) and add it to your .env file.
``` dotenv
TRELLO_AUTH_TOKEN=xxx
```

There is a single table used to store the ids and names of the current workspace, board, and list. You will need to run the migration to set this up.

``` bash
php artisan migrate
```

## Usage

From the console enter:

``` bash
php artisan todo
```

To reset the current workspace, board, and list, enter:

``` bash
php artisan todo --configure
```

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Roadmap

- ~~Probably should write some tests~~ :grimacing:
- Additional commands
- Models for Workspace, Lists, and Cards
- More tests... :cold_sweat:

## Credits

- [Matt Rees-Jenkins][link-author]

## License

Laravel Prompts is open-sourced software licensed under the [MIT license](license.md).

[link-author]: https://github.com/mattreesjenkins
