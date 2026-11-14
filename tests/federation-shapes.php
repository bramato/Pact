<?php

declare(strict_types=1);
require __DIR__.'/../sdk/php/vendor/autoload.php';
$pact = new Pact\Pact;
$roster = json_decode('{"federation_id":"f","coordinator_url":"https://coordinator.example/","revision":1,"mcp_endpoint":"https://coordinator.example/mcp","members":[{"id":"alice","kind":"user","active":true}]}');
$pact->federationRoster($roster);
$roster->members[] = clone $roster->members[0];
try {
    $pact->federationRoster($roster);
    throw new LogicException('duplicate member accepted');
} catch (Pact\PactError $e) {
    if ($e->errorCode !== 'FEDERATION_MEMBER_DUPLICATE') throw $e;
}
$delegation = json_decode('{"root_task_id":"root","parent_task_id":"parent","delegation_id":"d","path":["a","b"],"steps":1,"delegations":1,"task_id":"child","created_at":"2026-11-14T11:59:00Z","deadline":"2026-11-14T12:00:00Z"}');
$pact->federationDelegation($delegation);
$delegation->path[] = 'a';
try {
    $pact->federationDelegation($delegation);
    throw new LogicException('cycle accepted');
} catch (Pact\PactError $e) {
    if ($e->errorCode !== 'SCHEMA_INVALID') throw $e;
}
echo "Federation shapes passed\n";
// Shared wire vectors are exercised in both SDKs.
foreach (json_decode(file_get_contents(__DIR__.'/../conformance/federation/delegation-vectors.json')) as $vector) {
    $accepted = true;
    try { $pact->federationDelegation($vector->delegation); } catch (Pact\PactError $e) { $accepted = false; }
    if ($accepted !== $vector->valid) throw new LogicException('Vector mismatch: '.$vector->name);
}
echo "Federation vectors passed\n";
