# Documentation guide

| Reader / task | Start with | Then read |
| --- | --- | --- |
| New human contributor | [Project README](../README.md), [contributing](../CONTRIBUTING.md) | [Project structure](project-structure.md), [handoff](../HANDOFF.md) |
| New AI contributor | [Root instructions](../AGENTS.md), [handoff](../HANDOFF.md) | [AI workflow](ai/instructions.md), [parallel protocol](ai/parallel-work.md) |
| Operator installing the panel | [Installation](installation.md) | [Security](security.md), [validation limits](validation.md) |
| Installer / certificate-management contributor | [Installer design](installer-design.md) | [Architecture](architecture.md), [security](security.md), [task board](ai/tasks.md) |
| DNS / infrastructure engineer | [Architecture](architecture.md) | [Member contracts](../resources/AGENTS.md), [roadmap](roadmap.md) |
| Reviewer | [Contributing](../CONTRIBUTING.md) | Relevant scoped instructions, tests and [validation](validation.md) |
| Coordinator / next session | [Handoff](../HANDOFF.md) | [Task board](ai/tasks.md), [handoff template](ai/handoff-template.md) |

## Sources of truth

- **User requirements and contributor rules:** root/scoped `AGENTS.md`; current user instructions take precedence.
- **Existing behavior:** application source and tests. Architecture describes intent and current design; verify details against code.
- **Planned work:** [roadmap](roadmap.md). A planned feature is not an implemented feature.
- **Executed evidence:** [validation](validation.md), dated test reports and CI output. A test file is not evidence that the test ran.
- **Session continuity:** [HANDOFF.md](../HANDOFF.md) records the latest durable status, limitations and recommended next work.
- **Concurrent ownership:** [task board](ai/tasks.md) and live coordinator assignments. An unclaimed backlog row is not an active assignment.

## Documentation maintenance

Update behavior documentation with code changes; update plans when scope changes; append dated validation only for executed checks. Keep paths relative within repository Markdown. Do not put secrets or machine-specific credentials into any document. The architecture and README should remain understandable without reading chat history.
