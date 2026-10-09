# PACT PHP SDK

`pact-protocol/core` implements PACT rules independently of TypeScript. It requires PHP 8.2+, JSON, mbstring, BCMath and cURL. Opis JSON Schema performs Draft 2020-12 shape/contract validation. The package ships its own canonical JSON serialization and exact rational progress arithmetic.

From the repository root:

```sh
npm run build:sdk
composer install --working-dir=sdk/php --no-interaction
php conformance/v0.2/runner.php
```

The package has not been published to Packagist. Use a local Composer path repository or the verified Composer archive until the owner releases it. Build resources before archiving; `resources/schemas.json` and `LICENSE.md` are included in the standalone archive. The [reference Composer project](https://github.com/bramato/Pact/blob/main/reference/composer.json) shows local installation.

```php
use Pact\Pact;
use Pact\Client;

$pact = new Pact();
$pact->contractData($contract->input, $input, $registryPath);
$client = new Client($endpoint, $token, $contract, $registryPath);
$raw = json_encode((object)[
    'jsonrpc'=>'2.0', 'id'=>'request-1', 'method'=>'SendMessage',
    'params'=>(object)['message'=>$message],
], JSON_THROW_ON_ERROR);
$result = $client->raw($raw);        // Persist $raw before retrying it.
$task = $client->task($result->task); // Independently validates output artifacts.
```

Decode JSON objects as `stdClass`, JSON arrays as arrays. Arrays with named keys are not canonical JSON objects. The public rules are `shape`, `progress`, `event`, `contractData`, `answers`, `transition`, `negotiate`, `graph` and `swarmProgress`; static helpers include `canonical`, `eventDigest` and `signEvent`. `PactError::errorCode` contains the portable error code. The SDK resolves schemas locally and rejects unpinned external references.

`EventReceipts` is an in-memory consumer helper. The [reference binding](https://github.com/bramato/Pact/blob/main/docs/implementation.md) provides transactional SQLite receipts/outbox; Laravel routing is kept in a [separate package](https://github.com/bramato/Pact/blob/main/sdk/laravel/README.md). See [Core](https://github.com/bramato/Pact/blob/main/specification/PACT-CORE-v0.2.md) for digest coverage and lifecycle rules.
