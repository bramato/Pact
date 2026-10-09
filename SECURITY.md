# Security policy

PACT v0.1.0 is a draft specification and local validation toolkit. There is no production service or supported deployment in this repository. Maintainers review reports against the current draft; no response-time guarantee has been established.

Report vulnerabilities privately using GitHub's **Report a vulnerability** feature when enabled, or a private contact method listed by a repository maintainer. Include the affected file/version, reproduction steps, expected boundary, and impact. Do not publish credentials, private artifacts, or exploitable tenant data in an issue. If no private channel is available, open an issue requesting one without disclosing the exploit.

The harness resolves schemas only from an explicit local URI-to-file registry. It never fetches a contract URI, executes artifact content, authenticates peers, or accesses artifact URLs. Production adapters must implement HTTPS, peer authentication, server-side tenant/delegation checks, constrained schema resolution, SSRF protection, size and MIME limits, durable atomic idempotency, retention, and bounded retries. JSON Schema validates structure, not the truth or safety of agent outputs.
