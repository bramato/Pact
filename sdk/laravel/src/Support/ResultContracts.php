<?php

declare(strict_types=1);

namespace PactLaravel\Support;

use Opis\JsonSchema\Validator;

final class ResultContracts
{
    public static function validate(string $method, mixed $value): void
    {
        $schema = json_decode(file_get_contents(__DIR__.'/../../resources/contracts/mcp-results.json'), false, 512, JSON_THROW_ON_ERROR);
        if (! property_exists($schema->{'$defs'}, $method)) {
            throw new \UnexpectedValueException('Unknown federation result contract.');
        }
        $schema->{'$ref'} = '#/$defs/'.$method;
        $data = json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        if (! (new Validator)->validate($data, $schema)->isValid()) {
            throw new \UnexpectedValueException('Federation result does not satisfy its contract.');
        }
    }
}
