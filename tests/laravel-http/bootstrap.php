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
            Route::get('/fixture/members', fn () => response()->json(app(PactManager::class)->members()))->middleware('auth');
            Route::get('/fixture/send/{member}', fn ($member) => response()->json(app(PactManager::class)->sendMessage($member, 'hello from HTTP smoke', 'smoke-message')))->middleware('auth');
            Route::get('/fixture/task/{id}', fn ($id) => response()->json(app(PactManager::class)->task($id)))->middleware('auth');
        });
    })->create();

return $app;
