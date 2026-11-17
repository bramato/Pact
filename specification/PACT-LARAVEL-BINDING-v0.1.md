# PACT Laravel Federation binding v0.1 — draft

This binding implements the personal Federation profile using Laravel applications installed as `master` or `client`. It adds versioned HTTP and MCP result conventions; existing Core 0.2, agent-chat 0.1 and roster/delegation schemas remain unchanged.

## Installation and identity

A master owns one default personal federation for each local identity and may own additional named teams, each represented by a federation. Every selected team MUST belong to the initiating identity. Existing personal federation IDs and member identities are preserved on upgrade. Its owner appears as a `user` member; connected application agents appear as `agent` members. Each client agent uses the existing product/user-derived identity. Version one permits one outstanding delegation per parent. Root budgets, cycle limits and cancellation semantics follow Federation.

The client application is the OAuth authorization server. A master is its public authorization-code consumer using PKCE S256. New connections start at the master from an agent server domain: discovery and public client registration replace manual preregistration. The client's existing session and application-owned tenant middleware authenticate the consenting local identity. The master's one-use callback state binds the exchange to the initiating local identity. No email matching is inferred. The authorized client identity MUST remain unchanged between consent rendering and approval.

Endpoints live below `/pact` by default. All connected installations use the same configured prefix. Public endpoints require HTTPS; development HTTP requires an explicit local-host allowlist.

## Domain discovery, registration and team selection

`/.well-known/pact` is a PACT-specific discovery document (not a replacement for RFC 8414 OAuth metadata). Its fields are `pact_version: "0.1"`, `role`, `issuer` (canonical origin) and `pact_endpoint` (origin plus configured prefix). A client additionally publishes `authorization_endpoint`, `token_endpoint`, `registration_endpoint`, supported code/refresh grants, the public `none` token authentication method and PKCE `S256`. A master publishes `redirect_uri` and `public_key_endpoint`. An entered bare domain defaults to HTTPS. Origins MUST NOT include userinfo, paths, query or fragment; default ports and host case are normalized.

The master MUST require the discovered issuer, role, version and every endpoint to match that selected origin and configured prefix. It MUST reject redirects, mismatched origins and unsupported PKCE. Public outbound destinations MUST resolve exclusively to public IP addresses, and the HTTP transport MUST pin a vetted address while retaining TLS hostname verification. Explicitly allowlisted local-development hosts are the exception.

Registration uses the RFC 7591 public-client metadata subset: `client_uri`, one `redirect_uris` entry, `grant_types: ["authorization_code", "refresh_token"]`, `response_types: ["code"]` and `token_endpoint_auth_method: "none"`. The single callback MUST equal the canonical master origin plus the configured `/oauth/callback` path. Registration is idempotent per master origin; the client returns `client_id` and the registered metadata with HTTP 201 and never creates a shared client secret. Registration is public metadata registration, not a grant of user access or proof of domain ownership; real consent, PKCE, master-bound callback state and authenticated activation are still required. Registration and master initiation are rate limited. Activation independently verifies the registered coordinator's published signing key.

The guided callback MUST stage the tokens and remote subject encrypted at rest for at most ten minutes and redirect to the master's team selector. No activation may be queued until the same master identity chooses an owned team or creates one. The selection and activation enqueue commit atomically; exact repeat submissions reuse the same result. Cancellation erases the temporary credentials. Expired link payloads are erased by the worker and at subsequent link initiation. The legacy preregistered API retains its direct-to-personal-team behavior.

A remote account agent has at most one active or pending team at a given master for a given master identity. Reauthorization in that team reuses the connection and advances its generation. Assigning it to another team requires confirmed disconnection first; the client also rejects conflicting active federations. Master APIs accept an optional owned team ID and default to the preserved personal team.

## HTTP resources

| Role | Resource | Behavior |
| --- | --- | --- |
| Both | `/.well-known/pact` (at origin root) | Public role, origin and fixed endpoint discovery |
| Client | `/oauth/register` | Idempotent public PKCE consumer registration with exact callback binding |
| Master | `/agents`, `/connect` | Authenticated agents page and domain-only connection form (POST initiates) |
| Master | `/connect/{link}/team` | Owner-only team selection; POST atomically queues activation |
| Master | `/connect/{link}/cancel`, `/connections/{connection}/disconnect` | CSRF-protected owner-only cancellation and disconnection |
| Client | `/oauth/authorize`, `/oauth/token` | Passport authorization code and refresh grants for registered masters; authorization requires PKCE S256 and the current client-owned identity |
| Master | `/oauth/callback` | Exchanges the code and stages the guided team choice; legacy links queue activation in the personal team |
| Client | `/oauth/subject` | Returns only the token-authorized product/user/agent identity and manifest |
| Client | `/oauth/activate` | Idempotently activates a generation after verifying the registered coordinator and its signing key |
| Master | `/oauth/public-key` | Publishes the coordinator signing public key |
| Master | `/roster` | Authenticated roster with strong JCS SHA-256 ETag and conditional refresh |
| Master | `/mcp` | Authenticated Laravel MCP transport for the seven Federation tools |
| Master | `/renew`, `/disconnect` | Separate-audience credential renewal and client-initiated revocation |
| Client | `/oauth/disconnect` | Master-initiated revocation by stable master connection ID and generation; exact replay returns its receipt |
| Client | `/agents/{agent}/a2a` | Native A2A `SendMessage`, `GetTask` and `CancelTask`, with Core activation |
| Client | `/agents/{agent}/.well-known/agent-card.json` | Capabilities and pinned contracts |
| Client | `/agents/{agent}/resume` | Authenticated, idempotent delegation continuation; only a waiting task can resume |
| Client | `/agents/{agent}/events` | Authenticated Core event validation and durable receipts |

OAuth tokens authorize master-to-client requests. Signed master credentials authorize client-to-master requests with distinct scalar audiences and connection generations. Credentials and queued token-bearing payloads are encrypted at rest. The coordinator checks current membership for every authorized operation.

Activation, continuation and disconnect are generation-fenced. An activation response received after local revocation records reconciliation work rather than reactivating the member. Revocation uses the stable identity known before dispatch, so a lost activation acknowledgment does not prevent cleanup. A disconnect accepted before an activation creates a fence against that delayed activation.

## MCP results

The successful JSON value carried by each tool's text content is constrained by [`mcp-results.schema.json`](federation/0.1/mcp-results.schema.json). Tool failures use MCP error results rather than successful resource values.

| Method | Successful value |
| --- | --- |
| `list_members` | Array of roster member records |
| `get_manifest` | Owning application's manifest object; user members may have an empty object |
| `send_message`, `request_delegation` | `{task_id, delegation_id, accepted: true}` durable receipt; delegation ID is nullable for an ordinary message |
| `get_task` | Scoped task snapshot containing local `id` and native `status.state` |
| `reserve_step` | `{reserved: true, steps}` with the root's cumulative count, at most sixteen |
| `read_conversation` | Application-defined authorized JSON; its owning adapter documents the content contract |

Task reads authorize the caller or actual assigned agent, not arbitrary federation members. Conversation reads additionally require the application's resource-specific authorization. A roster does not grant file, tool or conversation access.

## Durable execution

Receipts, tasks, root checkpoints and outbox entries commit before dispatch. The database worker claims a lease and fences acknowledgment/result writes against its owner and expiry. Delivery retries retain serialized command bytes. A caller-provided idempotency key conflicts if reused with different input.

Peer acceptance pins the remote task and context. Polling validates activation, envelope correlation, contracts and actual output artifacts independently. Terminal failures and rejected children settle the parent continuation. Root deadlines are anchored at task creation and trigger durable descendant cancellation. Late responses remain observations and cannot revive terminal tasks.

Application handlers execute outside database transactions and receive a stable invocation identity for idempotent side effects. This binding does not claim exactly-once execution of arbitrary external tools. An outage exceeding bounded retry or credential lifetimes remains an audited, unconfirmed delivery failure requiring operator recovery.

The runtime and schemas are a draft implementation, not certification of every A2A or MCP feature. Swarm scheduling and public package publication are separate concerns.
