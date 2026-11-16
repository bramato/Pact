<?php

declare(strict_types=1);

namespace PactLaravel\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use PactLaravel\Contracts\IdentityResolver;

final class DefaultIdentityResolver implements IdentityResolver
{
    public function current(): ?Authenticatable
    {
        return Auth::guard(config('pact.auth.guard', 'web'))->user();
    }

    public function find(string $id): ?Authenticatable
    {
        return Auth::createUserProvider(config('pact.auth.provider', 'users'))->retrieveById($id);
    }

    public function id(Authenticatable $user): string
    {
        return (string) $user->getAuthIdentifier();
    }
}
