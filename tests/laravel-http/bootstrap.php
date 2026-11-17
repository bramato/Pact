<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Passport\PassportServiceProvider;
use PactLaravel\PactManager;
use PactLaravel\PactServiceProvider;

require getenv('PACT_VENDOR').'/autoload.php';
class FixtureUser extends User
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}
$app = Application::configure(basePath: getenv('FIXTURE_BASE'))
    ->withProviders([PassportServiceProvider::class, McpServiceProvider::class, PactServiceProvider::class])
    ->withExceptions(fn ($exceptions) => null)
    ->withMiddleware()
    ->withRouting(using: function () {
        Route::middleware('web')->group(function () {
            Route::get('/login', function () {
                auth()->login(FixtureUser::query()->firstOrFail());

                return 'logged in';
            })->name('login');
            Route::get('/fixture/reverse-roster', function () {
                abort_unless(config('pact.role') === 'client', 404);
                $connection = \PactLaravel\Models\Connection::query()->where('user_id', (string) auth()->id())->where('status', 'active')->firstOrFail();
                $result = app(\PactLaravel\Support\HttpTransport::class)->request('GET', $connection->remote_url.'/pact/roster', ['Authorization' => 'Bearer '.$connection->credentials['roster']]);

                return response()->json($result['body'], $result['status']);
            })->middleware('auth');
            Route::get('/fixture/members', fn (\Illuminate\Http\Request $request) => response()->json(app(PactManager::class)->members($request->query('team'))))->middleware('auth');
            Route::get('/fixture/send/{member}', fn (\Illuminate\Http\Request $request, $member) => response()->json(app(PactManager::class)->sendMessage($member, 'hello from HTTP smoke', 'smoke-message', $request->query('team'))))->middleware('auth');
            Route::get('/fixture/task/{id}', fn (\Illuminate\Http\Request $request, $id) => response()->json(app(PactManager::class)->task($id, $request->query('team'))))->middleware('auth');
        });
    })->create();

return $app;
