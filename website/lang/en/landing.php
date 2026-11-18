<?php

return [
    'meta' => [
        'title' => 'PACT — Reach an agreement. Then act.',
        'description' => 'PACT is a protocol for independent agents to work together: explicit contracts, structured questions and verifiable results on top of A2A. Discover how it works.',
        'og_description' => 'An explicit agreement between agents. From input data to a verifiable result, with contracts, clarification and coordination on top of A2A.',
    ],
    'accessibility' => [
        'skip' => 'Skip to content',
        'home' => 'PACT, back to the top',
        'logo' => 'PACT: a single paper sticker with a horizontal circle, triangle and square, and the name below.',
        'nav' => 'Main navigation',
        'language' => 'Page language',
        'principles' => 'The three principles',
        'steps' => 'Example steps',
        'swarm' => 'Collaboration example: a coordinator assigns extraction, review and composition, then validates the final result.',
    ],
    'nav' => [
        'protocol' => 'The protocol',
        'practice' => 'In practice',
        'handoff' => 'Handoff',
    ],
    'edition' => 'Independent proposal · Draft 0.2.0',
    'hero' => [
        'heading' => 'Reach an<br>agreement.<br><em>Then act.</em>',
        'lead' => 'AI agents can exchange messages.<br> PACT helps them share a task, resolve questions and deliver a verifiable result.',
        'discover' => 'Discover the protocol',
        'spec' => 'Read the specification',
        'figure' => 'FIG. 01 — A SHARED AGREEMENT',
        'alt' => 'Halftone illustration on a paper cutout: the PACT robot connects two systems and presents a verified result.',
        'caption' => 'Different agents. One shared contract.',
        'bottom' => 'Explicit contracts. Verifiable collaboration.',
        'introduction' => 'An introduction to PACT',
    ],
    'intro' => [
        'label' => '01 / The starting point',
        'kicker' => 'FROM A MESSAGE TO A TASK',
        'heading' => 'Understanding is a start.<br><em>Agreement comes next.</em>',
        'paragraphs' => [
            'Ask an agent to analyze a document. It replies. But in what format? With which information? And how do you know when it has actually finished?',
            'When work moves from one system to another, these questions become part of the task. PACT makes the agreement explicit: which data to send, how to ask for what is missing, and which result to accept.',
            'It builds on <a href="https://a2a-protocol.org/">A2A, the Agent2Agent protocol</a>, which provides agent discovery, messages, tasks and artifacts. PACT adds the rules of the agreement in the metadata of a negotiated extension. <strong>The native A2A task status remains authoritative.</strong>',
        ],
        'principles' => [
            [
                'title' => 'First, the contract.',
                'text' => 'The caller and the executor agree on the shape of the data.',
            ],
            [
                'title' => 'During the task, a dialogue.',
                'text' => 'When something is missing, a precise question unblocks the work.',
            ],
            [
                'title' => 'Finally, validation.',
                'text' => 'The delivery must match the agreed output.',
            ],
        ],
    ],
    'contract' => [
        'label' => '02 / The contract',
        'heading' => 'Share the same idea<br>of <em>“done”.</em>',
        'paragraphs' => [
            'Free text leaves room for interpretation. A contract defines the exact structure of what goes in and what must come out.',
            'Each contract has an identity and a version. JSON schemas are also identified by their SHA-256 digest: both parties can check that they are using the same bytes before validating the data.',
            'The result is checked against the agreed schema. <strong>Structural validity can be verified; content quality remains the application’s responsibility.</strong>',
        ],
        'link' => 'Explore PACT Core',
        'visual_heading' => 'AN EXAMPLE, IN PLAIN WORDS',
        'input_title' => 'Here is the document.',
        'input_text' => 'Text to process, in the expected structure.',
        'input_code' => '{ "text": "The text to analyze…" }',
        'connection' => 'shared contract',
        'output_title' => 'Here is the expected result.',
        'output_text' => 'A list of words and an approval value.',
        'footnote' => 'A simplified demo example, not a complete wire message.',
    ],
    'questions' => [
        'label' => '03 / Clarification',
        'heading' => 'A good question<br><em>is part of the work.</em>',
        'paragraphs' => [
            'A task can be clear in structure and incomplete in detail. How many words are needed? Which choice must be approved?',
            'In PACT, a question identifies the task and declares the allowed answers. Each answer refers to the question it resolves: the system can track which questions remain open, even when there is more than one.',
            'Work resumes once every required question blocking it has a valid answer. A request for clarification becomes a verifiable step in the collaboration.',
        ],
        'alt' => 'Halftone collage: the robot compares a document’s shapes with those required by the contract.',
        'caption' => 'FIG. 02 — DATA STRUCTURE IS PART OF THE AGREEMENT',
    ],
    'demo' => [
        'label' => '04 / A task, from start to finish',
        'kicker' => 'FOLLOW THE EXCHANGE',
        'heading' => 'From agreement<br><em>to delivery.</em>',
        'intro' => 'A caller gives an executor a text to process.<br>Follow the steps of this illustrative example.',
        'toolbar' => 'NARRATIVE SCHEMA · ILLUSTRATIVE JSON',
        'note' => 'A guided illustration of the flow; no agent is executed.',
        'noscript_before' => 'To explore the other steps, enable JavaScript or',
        'noscript_link' => 'read the complete flow in the guide',
        'client' => [
            'labels' => [
                'copy' => 'Copy',
                'copied' => 'Copied',
                'copy_success' => 'Example copied to the clipboard.',
                'copy_fallback' => 'Select the code',
                'copy_error' => 'Automatic copying is unavailable: select the code and copy it manually.',
                'next' => 'Next step',
                'restart' => 'Start again',
            ],
            'steps' => [
                [
                    'tab' => 'Agreement',
                    'label' => '01 / Agreement',
                    'heading' => 'Before starting, the same contract.',
                    'description' => 'The caller reads the Agent Card and negotiates the PACT extension and the supported contract. Both parties check which schemas they will use for input and output.',
                    'status' => 'Extension negotiation',
                    'progress' => 'Before the task',
                    'percent' => 0,
                    'data' => [
                        'extension' => 'PACT Core 0.2',
                        'agreement' => [
                            'input' => 'text',
                            'output' => [
                                'words',
                                'approved',
                            ],
                            'schemas' => 'verified by both parties',
                        ],
                    ],
                ],
                [
                    'tab' => 'Task',
                    'label' => '02 / Task',
                    'heading' => 'Work starts with valid input.',
                    'description' => 'The caller sends the text in the agreed structure. The executor validates the input and starts the task. The native A2A status is WORKING; PACT adds progress details.',
                    'status' => 'A2A · WORKING',
                    'progress' => 'Illustrative progress · 20%',
                    'percent' => 20,
                    'data' => [
                        'input' => [
                            'text' => 'Agents collaborate with an explicit agreement.',
                        ],
                        'task' => [
                            'status' => 'WORKING',
                        ],
                        'progress' => [
                            'stage' => 'processing',
                            'percent' => 20,
                        ],
                    ],
                ],
                [
                    'tab' => 'Question',
                    'label' => '03 / Clarification',
                    'heading' => 'A detail is missing. Ask for it.',
                    'description' => 'The executor needs to know how many words to return. It opens a required question with allowed answers. A2A signals INPUT_REQUIRED: the question must be resolved before work resumes.',
                    'status' => 'A2A · INPUT_REQUIRED',
                    'progress' => 'Waiting for an answer',
                    'percent' => 40,
                    'data' => [
                        'task' => [
                            'status' => 'INPUT_REQUIRED',
                        ],
                        'question' => [
                            'id' => 'q-words',
                            'text' => 'How many words do you want in the result?',
                            'allowed' => [
                                3,
                                5,
                                7,
                            ],
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'tab' => 'Resume',
                    'label' => '04 / Resume',
                    'heading' => 'An answer linked to its question.',
                    'description' => 'The caller chooses five words and identifies the question being answered. The executor validates the answer. Once every required question blocking the task is resolved, work resumes.',
                    'status' => 'A2A · WORKING',
                    'progress' => 'Illustrative progress · 60%',
                    'percent' => 60,
                    'data' => [
                        'answer' => [
                            'questionId' => 'q-words',
                            'value' => 5,
                        ],
                        'validation' => 'allowed answer',
                        'task' => [
                            'status' => 'WORKING',
                        ],
                    ],
                ],
                [
                    'tab' => 'Delivery',
                    'label' => '05 / Delivery',
                    'heading' => 'The result matches the agreement.',
                    'description' => 'The executor delivers the artifact, and the caller checks its data against the schema. Valid output and the absence of unresolved required questions allow completion. Reaching 100% alone would not be enough.',
                    'status' => 'A2A · COMPLETED',
                    'progress' => 'Output validated',
                    'percent' => 100,
                    'data' => [
                        'artifact' => [
                            'words' => [
                                'Agents',
                                'collaborate',
                                'with',
                                'an',
                                'explicit',
                            ],
                            'approved' => true,
                        ],
                        'outputValidation' => 'valid',
                        'task' => [
                            'status' => 'COMPLETED',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'swarm' => [
        'label' => '05 / PACT Swarm',
        'kicker' => 'WHEN THE WORK TAKES A TEAM',
        'heading' => 'More agents.<br><em>Clear responsibility.</em>',
        'paragraphs' => [
            'Some tasks need several kinds of expertise. The optional Swarm profile adds a coordinator that splits the work into child tasks and respects their dependencies.',
            'The contributor aggregating the results declares which outputs are needed and how to evaluate them. The <code>all_required</code> and <code>quorum</code> policies make the success condition explicit. The final result must also pass validation.',
            'Each contribution keeps its provenance. Core remains the foundation; Swarm is enabled only when both parties support and negotiate it.',
        ],
        'parent' => 'PARENT TASK',
        'coordinator' => 'Coordinator',
        'coordinator_text' => 'Assigns, collects, validates.',
        'children' => [
            [
                'title' => 'Extraction',
                'text' => 'Reads the material.',
            ],
            [
                'title' => 'Review',
                'text' => 'Checks the contribution.',
            ],
            [
                'title' => 'Composition',
                'text' => 'Prepares the delivery.',
            ],
        ],
        'result' => 'Validated result',
        'result_text' => 'Traceable contributions.<br>One agreed output.',
        'caption' => 'An illustrative flow. Dependencies determine when each task can start.',
        'link' => 'Read Swarm',
    ],
    'completion' => [
        'number_caption' => 'PROGRESS TELLS THE STORY OF THE WORK.',
        'label' => '06 / Completion',
        'heading' => 'Finishing takes more<br>than <em>a percentage.</em>',
        'paragraphs' => [
            'Reaching 100% progress is not enough to complete a task. Neither is a successful HTTP response.',
            'Native A2A completion requires artifacts that conform to the contract, no unresolved required questions and, where applicable, completed aggregation. The caller can validate the output it receives.',
            'Stages, counters, sequences and event identities describe the process. <strong>A valid delivery demonstrates its outcome.</strong>',
        ],
    ],
    'handoff' => [
        'label' => '07 / Integrate PACT',
        'kicker' => 'TWO ROLES, TWO HANDOFFS',
        'heading' => 'Bring it to your project.<br><em>Start with the right role.</em>',
        'intro' => 'Copy the handoff into the assistant chat working on your Laravel project. It includes the goal, integration instructions and checks to complete.',
        'preview' => 'Read the full handoff',
        'copied' => 'Handoff copied. Paste it into your project chat.',
        'copy_error' => 'Automatic copying is unavailable. The text is selected: copy it manually.',
        'note' => 'The master coordinates personal teams; the client exposes the application’s agent. Connect them with the user’s consent.',
        'guide' => 'Laravel installation guide',
        'roles' => [
            'master' => [
                'title' => 'The project that coordinates.',
                'description' => 'For the application where each user connects their agents, sends work and follows the results.',
                'copy' => 'Copy master handoff',
                'text' => <<<'HANDOFF'
Integrate PACT into this Laravel project with the MASTER role.

Goal: each authenticated user can connect agents from other applications to their own teams, send work and review results and questions.

Minimum version for this flow: pact-protocol/laravel 0.2.1. Source reference: bramato/Pact, tag v0.2.1; Core protocol 0.2.0.

Read these sources before changing the project:
- https://github.com/bramato/Pact/blob/v0.2.1/docs/laravel-installation.md
- https://github.com/bramato/Pact/blob/v0.2.1/specification/PACT-LARAVEL-BINDING-v0.1.md
- https://github.com/bramato/Pact/blob/v0.2.1/docs/releases.md

1. Inspect project instructions, PHP/Laravel, database, authentication and the existing AI agent. The package supports Laravel 12.41.1+ and 13 with their compatible PHP versions. Reuse the application’s login, guard and provider. Locally use Herd with the project folder name followed by .test, its existing HTTP/HTTPS scheme and a matching APP_URL.

2. Install or update the versioned distribution using composer require pact-protocol/laravel:^0.2.1 pact-protocol/core:^0.2.1 --with-all-dependencies. Check composer show pact-protocol/laravel and composer show pact-protocol/core, then verify that the code actually loaded from vendor exposes PactManager::connectDomain(). If the project uses local Composer path or VCS repositories, compare them with the published distribution and adjust only the PACT overrides needed to resolve the version; preserve other repositories. For source development, check out tag v0.2.1, run npm ci --ignore-scripts and npm run build:sdk there, and assign local version 0.2.1 to both sdk/php and sdk/laravel Composer path repositories. Keep the updated composer.json and composer.lock in the project.

3. Verify the database, APP_KEY and APP_URL, then run:
php artisan config:clear
php artisan pact:install --role=master --no-interaction
For local HTTP peers, add one --allow-local-host per host to the installation command, including the master. Use HTTPS in production. Preserve APP_KEY, the database and storage/app/pact across deployments; reinstallation preserves identity and keys and cannot change the role.

For an existing PACT installation, rerun pact:install --no-interaction after updating to apply the new migrations: existing identity, connections and keys are preserved. Update both servers. Compare any published, customised views with the package views to include the new pages, and verify pact.auth.middleware in configuration.

Connection interface: use the PACT website style on both servers, with cobalt #075bc7, ivory paper #eee7d7 and #f5efdf, ink #122923, Georgia headings with italic emphasis, system body text, thin borders and rectangular buttons. Share tokens and primitives across master initiation, incoming client consent, return and team selection. Reuse the existing PACT symbol and SVG icons; controls are at least 52 px tall, fields use 16 px text, focus remains visible and errors are linked to fields. Consent shows the requesting domain and permissions in both directions. If the installed version does not include the theme, apply it to published views while preserving routes, @csrf and auth_token. The client layout must work with client-only routes.

4. Configure php artisan pact:work as a persistent process; Redis is not required. Use pact:work --once for individual checks. Run php artisan pact:doctor and resolve its findings. Rebuild configuration and route caches after installation.

5. Linking starts ON THE MASTER: add route('pact.agents') or route('pact.connect') to navigation. The package provides a «Collega agente» button: the user enters only the client domain, the master reads /.well-known/pact and registers its public OAuth client automatically. Do not require manual registration or a copied client ID. The CLI equivalent is:
php artisan pact:connect CLIENT_DOMAIN --domain --user=MASTER_USER_ID
The user signs into the client, consents, and returns to the master to select an owned team or create one. Email addresses may differ. Only after selection does the worker deliver return credentials and confirm both directions. Show pending, failed or confirmed activation. A remote account agent belongs to one team per master; confirm disconnection before moving it. Both peers must support discovery/registration and use the same PACT prefix. If the client is unavailable, complete the master and list the domain needed for testing.

6. In the authenticated interface, use PactLaravel\Facades\Pact: connectDomain(), teams(), createTeam(), members(), sendMessage(), task(), answer() and cancel(). Pass the selected team as the optional team argument; omission uses the personal team. Preserve the team ownership check. Show connections, members, task status, questions and results. Reuse idempotency keys when retrying the same command. Use contracts supported by both peers and validate artifacts: a receipt or HTTP 200 does not mean completion. For agent tools use the documented MCP binding and required credentials, without inventing endpoints.

7. Scope every operation to the current user’s authorised team. Membership does not grant general access to conversations or tools. When a client is available, test the UI path with only a domain, remote consent, team selection or creation, and confirmed activation. Verify both a master-to-client task and an authenticated client call to the master roster. Also test rejection of another user’s team, denied consent, expired linking, duplicate team submission without duplicate work, a complete task, questions/answers, cancellation and disconnection with pact:disconnect CONNECTION_ID --user=MASTER_USER_ID.

Deliver working changes, completed checks, worker configuration and operating instructions. Distinguish verified behavior from work awaiting a client or user consent. Do not include tokens or private keys in the report.
HANDOFF,
            ],
            'client' => [
                'title' => 'The project that contributes.',
                'description' => 'For the application that makes its agent and capabilities available to connected users.',
                'copy' => 'Copy client handoff',
                'text' => <<<'HANDOFF'
Integrate PACT into this Laravel project with the CLIENT role.

Goal: expose one agent per application user, connectable to a PACT master through OAuth consent, and execute work using the project’s existing AI capabilities and permissions.

Minimum version for this flow: pact-protocol/laravel 0.2.1. Source reference: bramato/Pact, tag v0.2.1; Core protocol 0.2.0.

Read these sources before changing the project:
- https://github.com/bramato/Pact/blob/v0.2.1/docs/laravel-installation.md
- https://github.com/bramato/Pact/blob/v0.2.1/specification/PACT-LARAVEL-BINDING-v0.1.md
- https://github.com/bramato/Pact/blob/v0.2.1/docs/releases.md

1. Inspect project instructions, PHP/Laravel, database, authentication, AI agent and available tools. The package supports Laravel 12.41.1+ and 13 with their compatible PHP versions. Reuse existing login, guard, provider and Passport keys. Locally use Herd with the project folder name followed by .test, its existing HTTP/HTTPS scheme and a matching APP_URL.

2. Install or update the versioned distribution using composer require pact-protocol/laravel:^0.2.1 pact-protocol/core:^0.2.1 --with-all-dependencies. Check composer show pact-protocol/laravel and composer show pact-protocol/core, then verify that the code actually loaded from vendor exposes PactManager::connectDomain(). If the project uses local Composer path or VCS repositories, compare them with the published distribution and adjust only the PACT overrides needed to resolve the version; preserve other repositories. For source development, check out tag v0.2.1, run npm ci --ignore-scripts and npm run build:sdk there, and assign local version 0.2.1 to both sdk/php and sdk/laravel Composer path repositories. Keep the updated composer.json and composer.lock in the project.

3. Verify the database, APP_KEY and APP_URL, then run:
php artisan config:clear
php artisan pact:install --role=client --no-interaction
For local HTTP peers, add one --allow-local-host per host to the installation command, including both client and master. Use HTTPS in production. Installation handles PACT migrations and missing Passport migrations. Preserve APP_KEY, the database, Passport keys and storage/app/pact across deployments; do not replace existing credentials.

For an existing PACT installation, rerun pact:install --no-interaction after updating to apply the new migrations: existing identity, connections and keys are preserved. Update both servers. Compare any published, customised views with the package views to include the new pages, and verify pact.auth.middleware in configuration.

Connection interface: use the PACT website style on both servers, with cobalt #075bc7, ivory paper #eee7d7 and #f5efdf, ink #122923, Georgia headings with italic emphasis, system body text, thin borders and rectangular buttons. Share tokens and primitives across master initiation, incoming client consent, return and team selection. Reuse the existing PACT symbol and SVG icons; controls are at least 52 px tall, fields use 16 px text, focus remains visible and errors are linked to fields. Consent shows the requesting domain and permissions in both directions. If the installed version does not include the theme, apply it to published views while preserving routes, @csrf and auth_token. The client layout must work with client-only routes.

4. Configure php artisan pact:work as a persistent process; Redis is not required. Use pact:work --once for individual checks. Run php artisan pact:doctor and resolve its findings. Rebuild configuration and route caches after installation.

5. The client automatically publishes /.well-known/pact and public OAuth registration. There is no need to know the master in advance or run pact:oauth-client. Give the user only this application's domain: ON THE MASTER they choose «Collega agente», enter the domain, sign in here, consent and return to select a team. The master worker completes the credentials in both directions. Peers must use the same PACT prefix. For a multitenant app, manage account/tenant selection here using the existing flow and pact.auth.middleware; configure IdentityResolver with a stable local subject including the authorized context. Approval rechecks that same identity. Email addresses need not match. Customise pages using php artisan vendor:publish --tag=pact-views. If the master is unavailable, complete the client and provide its domain for testing.

6. First verify connectivity with the default diagnostic contract, which does not use AI. Then implement PactLaravel\Contracts\AgentHandler with handle(AgentContext $context): AgentResult and connect the application’s real agent. Configure handler, contract_path and contract_registry in config/pact.php, using local schemas pinned by exact-byte digests; reuse the included agent-chat contract where appropriate. Return conforming output with AgentResult::completed(); use question(), waiting() or error() for other outcomes. Make external effects idempotent using context->invocationId.

7. Execute every task with context->userId’s permissions, including the authorised tenant. ConversationReader denies reads by default: enable only reads authorised for actual participants. Membership does not grant general access to data or tools. Verify public discovery, repeated registration returning the same client ID and a callback bound to the master. When a master is available, test the domain-only flow, consent, activation and authenticated calls in both directions. For multitenant apps, verify that changing tenant before approval requires fresh consent. Test a task with validated output, user isolation, idempotent retries, questions/resume and revocation with pact:disconnect CONNECTION_ID --user=CLIENT_USER_ID.

Deliver working changes, completed checks, worker configuration, exposed capabilities and the domain to pass to the master. Distinguish diagnostic connectivity from real AI integration and identify work awaiting a master or user consent. Do not include tokens or private keys in the report.
HANDOFF,
            ],
        ],
    ],
    'resources' => [
        'label' => '08 / From reading to code',
        'kicker' => 'OPEN THE PROJECT',
        'heading' => 'An open protocol.<br><em>Read it, try it, discuss it.</em>',
        'intro' => 'PACT is an independent draft proposal, without official A2A endorsement. Specifications, implementations and examples are available in the repository under the MIT license.',
        'items' => [
            [
                'title' => 'Start with the demo',
                'description' => 'A TypeScript caller and a PHP service: a complete task, without AI model API keys.',
                'path' => 'docs/quickstart.md',
                'label' => 'Quickstart',
            ],
            [
                'title' => 'Choose your implementation',
                'description' => 'Independent TypeScript and PHP SDKs, with a Laravel integration. Find the code and instructions in the repository.',
                'path' => 'tree/main/sdk',
                'label' => 'SDK',
            ],
            [
                'title' => 'Verify the rules',
                'description' => 'Schemas, shared vectors and conformance tests to check agreements, outputs and events.',
                'path' => 'tree/main/conformance',
                'label' => 'Conformance',
            ],
            [
                'title' => 'Understand the boundaries',
                'description' => 'Compatibility, implementation limits and decisions still open before publication.',
                'path' => 'docs/implementation.md',
                'label' => 'Project status',
            ],
        ],
    ],
    'faq' => [
        'label' => 'A few clarifications',
        'heading' => 'Before<br><em>you begin.</em>',
        'items' => [
            [
                'question' => 'Does PACT replace A2A?',
                'answer' => 'PACT uses A2A for agent discovery, messages, tasks, artifacts and extension negotiation. It adds the rules for contracts and collaboration. The native task lifecycle remains authoritative.',
            ],
            [
                'question' => 'Do I need an AI model to try it?',
                'answer' => 'The reference demo is deterministic: it uses TypeScript and PHP and can run without an AI model API key. The protocol describes exchanges between agents; the implementation decides how to carry out the work.',
            ],
            [
                'question' => 'Is valid output always correct output?',
                'answer' => 'Validation checks that data matches the agreed structure. Fact-checking, usefulness and content accuracy require application-level checks. A digest can detect changes, but does not authenticate the producer on its own.',
            ],
            [
                'question' => 'Is this already an official standard?',
                'answer' => 'PACT 0.2.0 is an independent draft proposal. The repository includes specifications, SDKs and verifiable examples; the available tests do not certify every A2A feature or full production readiness. The roadmap sets out release steps and decisions.',
            ],
        ],
    ],
    'closing' => [
        'eyebrow' => 'THE NEXT STEP IS AN AGREEMENT.',
        'heading' => 'Let’s put agents<br><em>to work together.</em>',
        'cta' => 'Explore PACT on GitHub',
    ],
    'footer' => [
        'edition' => 'Draft 0.2.0 · MIT',
        'governance' => 'Governance',
    ],
];
