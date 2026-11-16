<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
Schema::create('users', function ($t) {
    $t->id();
    $t->string('name');
    $t->string('email');
    $t->string('password');
    $t->rememberToken();
});
FixtureUser::query()->create(['name' => 'Smoke User', 'email' => 'smoke@example.test', 'password' => 'unused']);
