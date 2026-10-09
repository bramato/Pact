<?php

declare(strict_types=1);

namespace Pact;

interface HttpBinding
{
    /** @return array{0:int,1:array<string,string>,2:mixed} */
    public function handle(string $method, string $path, array $headers, string $raw): array;
}
