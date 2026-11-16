<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class Heartbeat extends PactModel
{
    protected $table = 'pact_heartbeats';

    protected $casts = [];
}
