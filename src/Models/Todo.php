<?php

namespace MattReesJenkins\LaravelTrelloCommands\Models;

use Illuminate\Database\Eloquent\Model;

class Todo extends Model
{
    protected $casts = [
        'conf' => 'array'
    ];
}
