<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class OAuthClient extends PactModel
{
    protected $table = 'pact_oauth_clients';

    protected $casts = [];
}
