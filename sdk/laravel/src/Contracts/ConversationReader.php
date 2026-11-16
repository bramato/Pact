<?php

declare(strict_types=1);

namespace PactLaravel\Contracts;

interface ConversationReader
{
    public function read(string $userId, string $actorId, string $conversationId): mixed;
}
