<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class Outbox extends PactModel
{
    protected $table = 'pact_outbox';

    protected $hidden = ['payload'];

    protected $casts = ['payload' => 'encrypted:array', 'attempts' => 'integer', 'available_at' => 'immutable_datetime', 'lease_until' => 'immutable_datetime'];
}
