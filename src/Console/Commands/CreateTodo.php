<?php

namespace MattReesJenkins\LaravelTrelloCommands\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Laravel\Prompts\Concerns\Colors;
use MattReesJenkins\LaravelTrelloCommands\Exceptions\ExitException;
use MattReesJenkins\LaravelTrelloCommands\Models\Todo;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class CreateTodo extends Command
{
    use Colors;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'todo
    {--configure : Change the current Workspace, Board, and List}
    {--l|list : Show the current list}
    {--b|board : Show the current board}
    {--w|workspace : Show the current workspace} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a Trello item or lists';

    private ?Todo $todo;
    private array $conf = [];
    private string $trello_api_key = '0f1146d1e5cdd282742d8398c50b2922';
    private string $trello_auth_url = 'https://trello.com/1/authorize?expiration=never&scope=read,write&response_type=token&key=';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {

            $this->loadConfig();

            $this->config(
                $this->conf['organisation'] ?? null,
                $this->conf['board'] ?? null,
                $this->conf['list'] ?? null,
            );

            if ($this->option('list')) {
                $this->showList();
                return 0;
            }

            if ($this->option('board')) {
                $this->showBoard();
                return 0;
            }

            if ($this->option('workspace')) {
                $this->showWorkspace();
                return 0;
            }

            $this->showMenu();

        } catch (ExitException $e) {
            return (int)$e->getCode();
        }

        return 0;

    }

    private function loadConfig(): void
    {
        $this->todo = Todo::latest()->first();

        if (!$this->todo) {
            $this->todo = new Todo();
        } else {
            $this->conf = $this->todo->conf;
        }
    }

    private function saveConfig()
    {
        $this->todo->conf = $this->conf;
        $this->todo->save();
        note('Config Saved');
    }

    private function config(?array $organisation = null, ?array $board = null, ?array $list = null): void
    {
        if ($this->option('configure')) {
            $this->conf = [];
            $this->input->setOption('configure', false);
        } else {
            $this->conf = [
                'organisation' => $organisation,
                'board' => $board,
                'list' => $list,
            ];
        }

        if (!isset($this->conf['organisation']['id'])) {
            $this->getOrganisation();
        }

        if (!isset($this->conf['board']['id'])) {
            $this->getBoard();
        }

        if (!isset($this->conf['list']['id'])) {
            $this->getList();
        }
    }

    private function getOrganisation(bool $save = true, bool $showCreate = true, bool $showExit = true)
    {
        $selectActions = [];
        if ($showCreate) {
            $selectActions['create'] = $this->cyan('Create New Workspace');
        }
        if ($showExit) {
            $selectActions['exit'] = $this->cyan('Exit');
        }

        $response = spin(
            fn() => Http::get("https://api.trello.com/1/members/me/organizations?fields=id,displayName&key={$this->trello_api_key}&token=" . config('laravel-trello-commands.trello_auth_token')),
            "Fetching Workspaces..."
        );

        if ($response->status() === 200) {
            $organizations = collect(json_decode($response));
            $organizationId = select(
                'Select Workspace...',
                [
                    ...$organizations->pluck('displayName', 'id')->toArray(),
                    ...$selectActions
                ],
            );

            if ($organizationId === 'create') {
                $this->createOrganisation();
            } elseif ($organizationId === 'exit') {
                $this->exit();
            } else {
                $this->conf['organisation'] = [
                    'id' => $organizationId,
                    'name' => $organizations->firstWhere('id', $organizationId)->displayName,
                ];
                if ($save) {
                    $this->saveConfig();
                } else {
                    return $organizations->firstWhere('id', $organizationId)->id;
                }
            }
        } else {
            $this->handleResponseError($response);
        }
    }

    private function createOrganisation(): void
    {
        $name = text('What do you want to call your workspace?');

        $response = spin(
            fn() => Http::post("https://api.trello.com/1/organizations", [
                'displayName' => $name,
                'key' => $this->trello_api_key,
                'token' => config('laravel-trello-commands.trello_auth_token')
            ]),
            'Creating workspace...'
        );

        if ($response->status() === 200) {
            $organisation = json_decode($response);
            $this->conf['organisation'] = [
                'id' => $organisation->id,
                'name' => $organisation->displayName,
            ];
            $this->saveConfig();
            note('Workspace created');
        } else {
            $this->handleResponseError($response);
        }
    }

    private function getBoard(bool $save = true, bool $showCreate = true, bool $showExit = true)
    {
        $selectActions = [];
        if ($showCreate) {
            $selectActions['create'] = $this->cyan('Create New Board');
        }
        if ($showExit) {
            $selectActions['exit'] = $this->cyan('Exit');
        }

        $boards = $this->getBoards();

        if ($boards->count() === 0) {
            $this->createBoard();
        } else {
            $boardId = select(
                'Select Board...', [
                ...$boards->pluck('name', 'id')->toArray(),
                ...$selectActions
            ]);
            if ($boardId === 'create') {
                $this->createBoard();
            } elseif ($boardId === 'exit') {
                $this->exit();
            } else {
                $this->conf['board'] = [
                    'id' => $boardId,
                    'name' => $boards->firstWhere('id', $boardId)['name'],
                ];
                if ($save) {
                    $this->saveConfig();
                } else {
                    return $boards->firstWhere('id', $boardId)->id;
                }
            }
        }
    }

    private function getBoards()
    {
        $response = spin(
            fn() => Http::get("https://api.trello.com/1/organizations/{$this->conf['organisation']['id']}/boards?fields=id,name&key={$this->trello_api_key}&token=" . config('laravel-trello-commands.trello_auth_token')),
            "Fetching Boards..."
        );

        if ($response->status() === 200) {
            return collect(json_decode($response, true));
        } else {
            $this->handleResponseError($response);
        }
    }

    private function createBoard()
    {
        $name = text('What do you want to call your board?');

        $response = spin(
            fn() => Http::post("https://api.trello.com/1/boards", [
                'name' => $name,
                'idOrganization' => $this->conf['organisation']['id'],
                'defaultLists' => config('laravel-trello-commands.create_default_lists'),
                'key' => $this->trello_api_key,
                'token' => config('laravel-trello-commands.trello_auth_token')
            ]),

            'Creating board...'
        );

        if ($response->status() === 200) {
            $board = json_decode($response);
            $this->conf['board'] = [
                'id' => $board->id,
                'name' => $board->name,
            ];
            $this->saveConfig();

            note('Board created');

        } else {
            $this->handleResponseError($response);
        }
    }

    private function getList(bool $save = true, bool $showCreate = true, bool $showExit = true)
    {
        $selectActions = [];
        if ($showCreate) {
            $selectActions['create'] = $this->cyan('Create New List');
        }
        if ($showExit) {
            $selectActions['exit'] = $this->cyan('Exit');
        }

        $lists = $this->getLists();
        $listId = select(
            'Select List...',
            [
                ...$lists->pluck('name', 'id')->toArray(),
                ...$selectActions
            ],
        );
        if ($listId === 'create') {
            $this->createList();
        } elseif ($listId === 'exit') {
            $this->exit();
        } else {
            $this->conf['list'] = [
                'id' => $listId,
                'name' => $lists->firstWhere('id', $listId)->name,
            ];
            if ($save)
                $this->saveConfig();
            else
                return $lists->firstWhere('id', $listId)->id;
        }
    }

    private function getLists()
    {
        $response = spin(
            fn() => Http::get("https://api.trello.com/1/boards/{$this->conf['board']['id']}/lists?fields=id,name&key={$this->trello_api_key}&token=" . config('laravel-trello-commands.trello_auth_token')),
            "Fetching Lists..."
        );

        if ($response->status() === 200) {
            return collect(json_decode($response));
        } else {
            $this->handleResponseError($response);
        }
    }

    private function createList()
    {
        $name = text('What do you want to call your list?');

        $response = spin(
            fn() => Http::post("https://api.trello.com/1/lists", [
                'name' => $name,
                'idBoard' => $this->conf['board']['id'],
                'key' => $this->trello_api_key,
                'token' => config('laravel-trello-commands.trello_auth_token')
            ]),
            'Creating list...'
        );

        if ($response->status() === 200) {
            $list = json_decode($response);
            $this->conf['list'] = [
                'id' => $list->id,
                'name' => $list->name,
            ];
            $this->saveConfig();

            note('List created');

        } else {
            $this->handleResponseError($response);
        }
    }

    private function getCards(?string $listId = null)
    {
        $listId = $listId ?? $this->conf['list']['id'];

        $response = spin(
            fn() => Http::get("https://api.trello.com/1/lists/{$listId}/cards?fields=id,name&key={$this->trello_api_key}&token=" . config('laravel-trello-commands.trello_auth_token')),
            "Fetching Cards..."
        );

        if ($response->status() === 200) {
            return collect(json_decode($response, true));
        } else {
            $this->handleResponseError($response);
        }
    }


    private function showMenu()
    {
        info("Current list: Workspace: {$this->conf['organisation']['name']} > Board: {$this->conf['board']['name']} > List: {$this->conf['list']['name']}");

        $choice = select("What would you like to do?", [
            'create-card' => 'Create Card',
            'create-list' => 'Create List',
            'move-card' => 'Move Card',
            'show-board' => 'Show Board',
            'change-list' => 'Change List',
            'change-board' => 'Change Board',
            'change-workspace' => 'Change Workspace',
            'delete-card' => 'Delete Card',
            'exit' => $this->cyan('Exit'),
        ],
            default: 'Create Card'
        );

        if ($choice === 'create-card') {
            $this->createCard();
        } elseif ($choice === 'create-list') {
            $this->createList();
        } elseif ($choice === 'move-card') {
            $this->moveCard();
        } elseif ($choice === 'show-board') {
            $this->showBoard();
        } elseif ($choice === 'change-list') {
            $this->config($this->conf['organisation'], $this->conf['board']);
        } elseif ($choice === 'change-board') {
            $this->config($this->conf['organisation']);
        } elseif ($choice === 'change-workspace') {
            $this->config();
        } elseif ($choice === 'delete-card') {
            $this->deleteCard();
        } elseif ($choice === 'exit') {
            $this->exit();
        }

        if (confirm(label: 'Continue?')) {
            $this->showMenu();
        }

    }

    private function createCard()
    {
        $name = text('What do you want to call your card?');

        $response = spin(
            fn() => Http::post("https://api.trello.com/1/cards", [
                'name' => $name,
                'idList' => $this->conf['list']['id'],
                'key' => $this->trello_api_key,
                'token' => config('laravel-trello-commands.trello_auth_token')
            ]),
            'Creating Card...'
        );

        if ($response->status() === 200) {
            $card = json_decode($response);
            $this->conf['card'] = [
                'id' => $card->id,
                'name' => $card->name,
            ];
            $this->saveConfig();

            note('Card created');

        } else {
            $this->handleResponseError($response);
        }
    }


    private function moveCard()
    {

        info("Workspace: {$this->conf['organisation']['name']} > Board: {$this->conf['board']['name']} > List: {$this->conf['list']['name']}");

        $listId = $this->getList(false, false, false);

        $cardId = select('Select Card...', [
            ...$this->getCards($listId)->pluck('name', 'id'),
            'exit' => $this->cyan('Exit')
        ]);

        if ($cardId === 'exit') {
            $this->exit();
        }

        $listId = select('Select Destination List...', $this->getLists()->pluck('name', 'id'));

        $response = Http::put("https://api.trello.com/1/cards/{$cardId}", [
            'idList' => $listId,
            'key' => $this->trello_api_key,
            'token' => config('laravel-trello-commands.trello_auth_token')
        ]);

        if ($response->status() === 200) {
            note('Card moved');
        } else {
            $this->handleResponseError($response);
        }


    }

    private function showList()
    {
        info("Workspace: {$this->conf['organisation']['name']} > Board: {$this->conf['board']['name']} > List: {$this->conf['list']['name']}");

        table(
            ['Cards'],
            $this->getCards()->transform(function (array $item) {
                return Arr::except($item, 'id');
            })
        );
    }

    private function showBoard(): void
    {
        // Needed to do a fair bit of rejigging to get the table data correct for this... Don't judge!

        $lists = $this->getLists();
        $headings = [];
        $rows = [];
        $cardData = [];

        $lists->each(function ($list) use (&$headings) {
            $headings[] = $list->name;
        });

        $maxNumCards = 0;
        for ($i = 0; $i < $lists->count(); $i++) {
            $cards = $this->getCards($lists[$i]->id)->transform(function (array $item) {
                return Arr::except($item, 'id');
            });
            $cardData[] = $cards;
            $maxNumCards = max($cards->count(), $maxNumCards);
        }

        for ($i = 0; $i < $maxNumCards; $i++) {
            if (!isset($rows[$i])) {
                $rows[$i] = [];
            }
            for ($j = 0; $j < $lists->count(); $j++) {
                $rows[$i][] = isset($cardData[$j][$i]) ? $cardData[$j][$i]['name'] : null;
            }
        }

        info("Workspace ({$this->conf['organisation']['name']}) > Board ({$this->conf['board']['name']})");

        table(
            $headings,
            $rows,
        );
    }

    private function showWorkspace()
    {
        info("Workspace ({$this->conf['organisation']['name']})");
        table(
            ['Boards'],
            $this->getBoards()->transform(function (array $item) {
                return Arr::except($item, 'id');
            })
        );
    }


    private function deleteCard()
    {
        info("Workspace: {$this->conf['organisation']['name']} > Board: {$this->conf['board']['name']} > List: {$this->conf['list']['name']}");

        $cards = $this->getCards($this->conf['list']['id']);

        if ($cards->count()) {

            $cardId = select('Select Card...', [
                ...$cards->pluck('name', 'id'),
                'change-list' => $this->cyan('Change List'),
                'exit' => $this->cyan('Exit')
            ]);

            if ($cardId === 'exit') {
                $this->exit();
            } elseif ($cardId === 'change-list') {
                $this->getList(true, false, true);
                $this->deleteCard();
            } else {
                $response = Http::delete("https://api.trello.com/1/cards/{$cardId}", [
                    'key' => $this->trello_api_key,
                    'token' => config('laravel-trello-commands.trello_auth_token')
                ]);
                if ($response->status() === 200) {
                    note('Card deleted');
                } else {
                    $this->handleResponseError($response);
                }
            }
        } else {
            info('This list does not contain any cards');
            if (confirm('Select a different List?')) {
                $this->getList(true, false, true);
                $this->deleteCard();
            }
        }
    }

    private function exit(string $message = 'Exit', int $code = 0)
    {
        throw new ExitException($message, $code);
    }

    private function handleResponseError(mixed $response)
    {
        $statusMessage = $response->status() . " - " . $response->reason();

        if ($response->status() === 400) {
            error($statusMessage . " - Have you setup your token?");
            info($this->trello_auth_url . $this->trello_api_key);
        } elseif ($response->status() === 401) {
            error($statusMessage . " - Please check your token");
            info($this->trello_auth_url . $this->trello_api_key);
        }

        $this->exit($response->status(), 1);

    }
}
