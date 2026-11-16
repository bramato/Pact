<?php

declare(strict_types=1);

namespace PactLaravel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

abstract class PactModel extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function getConnectionName()
    {
        return config('pact.database_connection') ?: parent::getConnectionName();
    }
}
