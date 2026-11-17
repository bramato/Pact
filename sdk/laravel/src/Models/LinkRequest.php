<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class LinkRequest extends PactModel
{
    protected $table = 'pact_link_requests';

    protected $hidden = ['payload'];

    protected $casts = ['payload' => 'encrypted:array', 'expires_at' => 'immutable_datetime'];
}
