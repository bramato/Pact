<?php

declare(strict_types=1);

namespace PactLaravel\Support;

use Illuminate\Auth\Access\AuthorizationException;
use PactLaravel\Contracts\ConversationReader;

final class DenyConversationReader implements ConversationReader
{
    public function read(string $userId, string $actorId, string $conversationId): mixed
    {
        throw new AuthorizationException('Conversation access is not configured.');
    }
}
