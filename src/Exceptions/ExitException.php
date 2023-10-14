<?php

namespace MattReesJenkins\LaravelTrelloCommands\Exceptions;

use Exception;
use Symfony\Component\Console\Exception\ExceptionInterface;

class ExitException extends Exception implements ExceptionInterface
{
    // Used to gracefully exit
    // If you have any better ideas, then please let me know!
}
