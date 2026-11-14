<?php

declare(strict_types=1);

namespace Pact\Federation;

use Pact\Pact;
use Pact\PactError;
use stdClass;

/** Policy for claims already cryptographically verified by the host. */
final class Policy
{
    public function __construct(private readonly Pact $pact = new Pact, private readonly array $insecureLocalHosts = []) {}

    public function authenticate(stdClass $claims, string $issuer, string $audience, int $now): string
    {
        Pact::ensure(($claims->iss ?? null) === $issuer && ($claims->aud ?? null) === $audience
            && is_string($claims->sub ?? null) && $claims->sub !== ''
            && is_int($claims->exp ?? null) && $claims->exp > $now
            && (! isset($claims->nbf) || (is_int($claims->nbf) && $claims->nbf <= $now)), 'FEDERATION_AUTH', 'Invalid verified credential claims');
        return $claims->sub;
    }

    /** Validate a coordinator response before persisting it. Null means HTTP 304. */
    public function refresh(?stdClass $current, ?stdClass $incoming, string $coordinator, ?string $etag, int $status): ?stdClass
    {
        if ($status === 304) {
            Pact::ensure($current !== null && $etag !== null, 'FEDERATION_ROSTER', 'Unexpected not-modified response');
            $this->pact->federationRoster($current, $this->insecureLocalHosts);
            Pact::ensure($current->coordinator_url === $coordinator && $etag === self::etag($current), 'FEDERATION_ROSTER', 'Unexpected not-modified response');
            return $current;
        }
        Pact::ensure($status === 200 && $incoming !== null, 'FEDERATION_ROSTER', 'Invalid roster response');
        $this->pact->federationRoster($incoming, $this->insecureLocalHosts);
        Pact::ensure($incoming->coordinator_url === $coordinator && $etag === self::etag($incoming), 'FEDERATION_ROSTER', 'Roster authority or ETag mismatch');
        if ($current !== null) {
            $this->pact->federationRoster($current, $this->insecureLocalHosts);
            Pact::ensure($current->federation_id === $incoming->federation_id && $current->coordinator_url === $coordinator && $incoming->revision >= $current->revision, 'FEDERATION_ROSTER', 'Roster rollback or federation mismatch');
            Pact::ensure($incoming->revision !== $current->revision || hash_equals(self::etag($current), $etag), 'FEDERATION_ROSTER', 'Revision reused with different content');
        }
        return $incoming;
    }

    public static function etag(stdClass $roster): string
    {
        return '"'.hash('sha256', Pact::canonical($roster)).'"';
    }

    public function member(stdClass $roster, string $id, ?string $kind = null): stdClass
    {
        $this->pact->federationRoster($roster, $this->insecureLocalHosts);
        foreach ($roster->members as $member) {
            if ($member->id === $id && $member->active === true && ($kind === null || $member->kind === $kind)) return $member;
        }
        throw new PactError('FEDERATION_AUTHORIZATION', 'Active membership required');
    }

    /** Stable opaque identity scoped to the product and user; independent of sessions. */
    public static function externalAgentId(string $product, string $user): string
    {
        Pact::ensure($product !== '' && $user !== '', 'FEDERATION_IDENTITY', 'Product and user are required');
        return 'agent_'.hash('sha256', strlen($product).':'.$product.$user);
    }

    /** Membership alone never authorizes reading a private conversation. */
    public function conversation(stdClass $roster, string $actor, array $participants): void
    {
        $this->member($roster, $actor);
        Pact::ensure(in_array($actor, $participants, true), 'FEDERATION_AUTHORIZATION', 'Conversation participation required');
    }
}
