<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use PactLaravel\Support\ResultContracts;
use PHPUnit\Framework\TestCase;

final class ResultContractsTest extends TestCase
{
    public function test_valid_receipt_and_empty_member_list_are_portable_json_results(): void
    {
        ResultContracts::validate('list_members', []);
        ResultContracts::validate('send_message', ['task_id' => 'task-1', 'delegation_id' => null, 'accepted' => true]);
        $this->addToAssertionCount(2);
    }

    public function test_an_out_of_budget_result_is_rejected_before_it_is_published(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        ResultContracts::validate('reserve_step', ['reserved' => true, 'steps' => 17]);
    }
}
