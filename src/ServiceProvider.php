<?php

namespace MattReesJenkins\LaravelTrelloCommands;

use MattReesJenkins\LaravelTrelloCommands\Console\Commands\CreateTodo;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-trello-commands.php', 'laravel-trello-commands');
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__ . '/../config/laravel-trello-commands.php' => config_path('laravel-trello-commands.php'),
        ], 'laravel-trello-commands.config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'migrations');
        // Registering package commands.
        $this->commands([
            CreateTodo::class
        ]);
    }
}
