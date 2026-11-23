<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use PactLaravel\Execution\AgentContext;
use PactLaravel\Execution\StructuredAgentCall;

final class StructuredAgentCallTest extends TestCase
{
    private function fixtureCall(): array
    {
        return [
            'task_id' => 'b17c1bdb-d1cc-4f63-9615-f42b527f6414',
            'run_id' => 'f8b1dbea-c217-4e4b-a299-f7f2c9fceedd',
            'revision' => 2,
            'kind' => 'execution',
            'work' => [
                'task' => ['title' => 'Controlla il catalogo', 'description' => 'Confronta le schede.', 'specifications' => 'Cita la fonte.', 'revision' => 2],
                'attachments' => [],
                'handoffs' => [['title' => 'Prima verifica', 'note' => 'Il catalogo è aggiornato.']],
            ],
        ];
    }

    public function test_the_context_exposes_a_readable_brief_with_the_original_input_intact(): void
    {
        $wire = json_encode($this->fixtureCall(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $context = new AgentContext('pact-task', 'alice', ['message' => $wire]);
        $service = $this->app->make(StructuredAgentCall::class);

        self::assertSame($this->fixtureCall()['run_id'], $service->decode($wire)?->run_id);
        self::assertSame("Controlla il catalogo\n\nConfronta le schede.\n\nSpecifiche\nCita la fonte.\n\nContributi precedenti\n\nPrima verifica\nIl catalogo è aggiornato.", $context->readableMessage());
        self::assertSame($wire, $context->input['message']);
    }

    public function test_plain_text_and_other_structured_payloads_stay_verbatim(): void
    {
        $service = new StructuredAgentCall;
        $withAttachments = $this->fixtureCall();
        $withAttachments['work']['attachments'] = [['name' => 'catalogo.pdf']];
        $internal = $this->fixtureCall();
        $internal['work']['request_context'] = 'Contesto del coordinatore';

        foreach ([" Testo semplice.\n", '{"query":"Quali sono le schede?"}', '{invalid', json_encode($withAttachments), json_encode($internal)] as $message) {
            self::assertNull($service->decode($message));
            self::assertSame($message, $service->readable($message));
        }
    }
}
