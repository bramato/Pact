<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class Receipt extends PactModel
{
    protected $table = 'pact_receipts';

    protected $casts = ['response' => 'array'];
}
