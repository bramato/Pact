<?php

declare(strict_types=1);

namespace PactLaravel\Models;

final class Application extends PactModel
{
    protected $table = 'pact_applications';

    protected $casts = [];
}
