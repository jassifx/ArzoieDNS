# Workstream / session handoff template

Copy the sections below into a task handoff or coordinator message. Keep root HANDOFF concise; link detailed evidence where available. Do not include secrets or private infrastructure identifiers.

```text
Task ID / title:
Owner / coordinator:
As-of date/time (UTC):
Status: in_progress / blocked / review / integrated

Requested outcome:
Important user requirements / constraints:

Baseline / working-tree state:
- Branch / base commit if known (do not invent):
- Existing unrelated changes:
- Shared checkout or worktree:
- Owned write paths:
- Files still being edited by others:

Implemented changes:
- Behavior and why it changed:
- Exact files/modules changed:
- Schema/routes/job/helper/data contracts changed:
- Compatibility / migration requirements:

Evidence actually executed:
- Command:
- Runtime/engine/OS and relevant versions:
- Result:
- Evidence location (sanitized):
- What this does NOT establish:

Not run / failures:
- Missing dependency, service, credentials or VM:
- Failed check and current hypothesis:
- Safe reproduction / next command:

Remaining work and risks:
- Acceptance criteria still open:
- Security / availability / recovery concerns:
- Dependencies or ownership transfers needed:

Next useful action:
- Concrete first step for the next contributor:
- Files to inspect first:
- Work already done that should not be repeated:

Documentation/status updates:
- Behavior docs updated:
- Coordinator HANDOFF/task-board update needed:
- Commit/deploy status (state explicitly; never imply):
```

For a documentation-only change, record path/link/claim verification instead of inventing application test evidence. For a blocked task, describe the precise blocker and any independent useful work completed. Planned checks belong under "Not run," never under executed evidence.
