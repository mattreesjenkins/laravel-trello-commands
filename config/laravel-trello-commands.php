<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trello Auth Token
    |--------------------------------------------------------------------------
    |
    | The authorisation token used for API requests
    |
    */
    'trello_auth_token' => env('TRELLO_AUTH_TOKEN', ''),


    /*
    |--------------------------------------------------------------------------
    | Create Default Lists
    |--------------------------------------------------------------------------
    |
    | If true, when creating a board it will automatically create the lists:
    | 'To Do', 'Doing', and 'Done'. If false, no lists will be created
    |
    */
    'create_default_lists' => env('TRELLO_CREATE_DEFAULT_LISTS', true)
];
