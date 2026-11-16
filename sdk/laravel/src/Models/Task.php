<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class Task extends PactModel
{
    protected $table = 'pact_tasks';

    protected $casts = ['value' => 'array', 'checkpoint' => 'array', 'version' => 'integer'];
}
