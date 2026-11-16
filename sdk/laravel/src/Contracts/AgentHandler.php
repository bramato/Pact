<?php

declare(strict_types=1);

namespace PactLaravel\Contracts;

use PactLaravel\Execution\AgentContext;
use PactLaravel\Execution\AgentResult;

interface AgentHandler
{
    public function handle(AgentContext $context): AgentResult;
}
