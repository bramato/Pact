<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class Member extends PactModel
{
    protected $table = 'pact_members';

    protected $casts = ['active' => 'boolean', 'manifest' => 'array'];
}
