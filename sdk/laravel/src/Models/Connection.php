<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class Connection extends PactModel
{
    protected $table = 'pact_connections';

    protected $hidden = ['credentials'];

    protected $casts = ['credentials' => 'encrypted:array', 'metadata' => 'array'];
}
