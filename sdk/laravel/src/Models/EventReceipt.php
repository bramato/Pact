<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class EventReceipt extends PactModel
{
    protected $table = 'pact_event_receipts';

    protected $casts = ['payload' => 'array', 'sequence' => 'integer'];
}
