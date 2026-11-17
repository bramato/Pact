<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Routing\Router;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

final class Routes
{
    public static function register(Router $router): void
    {
        $prefix = trim((string) config('pact.prefix', 'pact'), '/');
        $router->get('/.well-known/pact', fn () => response()->json(Discovery::document()))->name('pact.discovery');
        if (config('pact.role') === 'master') {
            $router->group(['prefix' => $prefix, 'middleware' => ['web', 'auth:'.config('pact.auth.guard', 'web')]], static function (Router $router): void {
                $router->get('/agents', [LinkController::class, 'index'])->name('pact.agents');
                $router->get('/connect', [LinkController::class, 'create'])->name('pact.connect');
                $router->post('/connect', [LinkController::class, 'start'])->middleware('throttle:20,1')->name('pact.connect.start');
                $router->get('/connect/{link}/team', [LinkController::class, 'team'])->name('pact.connect.team');
                $router->post('/connect/{link}/team', [LinkController::class, 'finish'])->name('pact.connect.finish');
                $router->post('/connect/{link}/cancel', [LinkController::class, 'cancel'])->name('pact.connect.cancel');
                $router->post('/connections/{connection}/disconnect', [LinkController::class, 'disconnect'])->name('pact.agents.disconnect');
            });
            $router->get('/'.$prefix.'/oauth/callback', CallbackController::class)->middleware(['web', 'auth:'.config('pact.auth.guard', 'web')])->name('pact.oauth.callback');
            $router->post('/'.$prefix.'/disconnect', ClientDisconnectController::class);
            $router->post('/'.$prefix.'/renew', RenewalController::class)->middleware(PeerMiddleware::class.':renew');
            $router->get('/'.$prefix.'/oauth/public-key', static function () {
                $key = @file_get_contents(storage_path('app/pact/public.pem'));
                abort_if($key === false, 503);

                return response()->json(['public_key' => $key]);
            });

            return;
        }
        if (config('pact.role') !== 'client') {
            return;
        }
        $router->post('/'.$prefix.'/oauth/register', RegistrationController::class)->middleware('throttle:20,1')->name('pact.oauth.register');
        $router->group(['prefix' => $prefix.'/oauth', 'middleware' => array_merge(['web', 'auth:'.config('pact.auth.guard', 'web')], config('pact.auth.middleware', []))], static function (Router $router): void {
            $router->get('/authorize', [AuthorizationController::class, 'authorize'])->name('pact.oauth.authorize');
            $router->post('/authorize', [AuthorizationController::class, 'approve'])->name('pact.oauth.approve');
            $router->post('/deny', [DenyAuthorizationController::class, 'deny'])->name('pact.oauth.deny');
        });
        $router->post('/'.$prefix.'/oauth/disconnect', DisconnectController::class)->name('pact.oauth.disconnect');
        $router->post('/'.$prefix.'/oauth/activate', ActivationController::class)->name('pact.oauth.activate');
        $router->get('/'.$prefix.'/oauth/subject', SubjectController::class)->name('pact.oauth.subject');
        $router->post('/'.$prefix.'/oauth/token', [TokenController::class, 'issue'])->name('pact.oauth.token');
    }
}
