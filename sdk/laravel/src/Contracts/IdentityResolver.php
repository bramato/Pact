<?php

declare(strict_types=1);

namespace PactLaravel\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface IdentityResolver
{
    public function current(): ?Authenticatable;

    public function find(string $id): ?Authenticatable;

    public function id(Authenticatable $user): string;
}
