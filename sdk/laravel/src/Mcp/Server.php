<?php

declare(strict_types=1);

namespace PactLaravel\Mcp;

use Laravel\Mcp\Server as McpServer;
use PactLaravel\Mcp\Tools\GetManifest;
use PactLaravel\Mcp\Tools\GetTask;
use PactLaravel\Mcp\Tools\ListMembers;
use PactLaravel\Mcp\Tools\ReadConversation;
use PactLaravel\Mcp\Tools\RequestDelegation;
use PactLaravel\Mcp\Tools\ReserveStep;
use PactLaravel\Mcp\Tools\SendMessage;

final class Server extends McpServer
{
    protected string $name = 'pact-federation';

    protected string $version = '0.1.0';

    protected array $tools = [ListMembers::class, GetManifest::class, SendMessage::class, RequestDelegation::class, GetTask::class, ReadConversation::class, ReserveStep::class];
}
