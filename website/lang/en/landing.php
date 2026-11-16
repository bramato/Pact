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
    'resources' => [
        'label' => '07 / From reading to code',
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
