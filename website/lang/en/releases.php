<?php

return [
    '0.3.0' => [
        'changes' => [
            'Core questions can carry a v1 form with typed fields, required values and labeled single or multiple choices.',
            'The PHP and TypeScript SDKs validate answers against the form and the contract. Laravel discovery advertises question_form_versions.',
        ],
        'instructions' => [
            'master' => 'For question forms v1, render the prompt and labeled fields in order, preserve JSON types (including false and zero), omit unanswered optional fields, and submit one object keyed by field name as the correlated answer value. Validate against the form and contract, display field errors, and report unsupported forms when the UI cannot render them. Test a required field, multiple selection and task resumption. Keep support for existing questions without a form.',
            'client' => 'When an agent needs structured input, use AgentResult::question() with the question form v1 descriptor and a pinned answer schema. Enable it once the receiving coordinator advertises question_form_versions containing 1 and can render it. Keep the form fixed while the question is open, validate the returned object against both form and schema and resume the original task. Keep the legacy question flow for peers without v1 support.',
        ],
    ],
    '0.2.2' => [
        'changes' => [
            'Shared PACT theme for connection, consent, team selection and agent status.',
            'Hosts can pass a per-response CSP nonce to inline connection styles.',
        ],
        'instructions' => [
            'master' => 'Reconcile customized views with the shared 0.2.2 theme for linking, team selection and agent status. If the host enforces CSP, pass its per-response nonce to the package styles as described by the connection interface guide.',
            'client' => 'Review the consent view against the shared 0.2.2 theme and keep the requesting domain and both permission directions visible. If the host enforces CSP, use the documented per-response nonce for package styles.',
        ],
    ],
    '0.2.1' => [
        'changes' => [
            'The master can link an agent by domain with discovery and automatic OAuth registration.',
            'Users can choose or create a named team. The worker confirms credentials in both directions before activation.',
        ],
        'instructions' => [
            'master' => 'When upgrading from 0.2.0, run the additive migration via pact:install and verify that the original personal federation and connections remain accessible. Test owned team selection and retry the same selection to verify it does not duplicate the connection.',
            'client' => 'When upgrading from 0.2.0, review published consent views and config for the new linking flow. Verify that using the same registration request again returns the same client ID and that a tenant change requires fresh consent.',
        ],
    ],
    '0.2.0' => [
        'changes' => [
            'First public Composer release of the PHP Core and Laravel packages.',
            'Master and client roles with OAuth/PKCE, personal federations, a database worker and validated A2A task outputs.',
        ],
        'instructions' => [
            'master' => 'Build the interface around the personal federation. Use Pact::members(), sendMessage(), task(), answer() and cancel() with the signatures from v0.2.0.',
            'client' => 'Verify that authorization returns the user to the same authenticated master session that started linking. For question continuations, verify that the selected contract and both peers register the answer schema they use.',
        ],
    ],
];
