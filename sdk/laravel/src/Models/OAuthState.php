<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class OAuthState extends PactModel
{
    protected $table = 'pact_oauth_states';

    protected $hidden = ['verifier'];

    protected $casts = ['verifier' => 'encrypted', 'expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
}
