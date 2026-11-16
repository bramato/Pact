<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class Federation extends PactModel
{
    protected $table = 'pact_federations';

    protected $casts = ['state' => 'array', 'revision' => 'integer'];
}
