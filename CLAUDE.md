# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel ShareLink is a comprehensive Laravel package for generating secure, time-limited sharing capabilities. It allows applications to share files, routes, and model previews with advanced security features including password protection, IP filtering, rate limiting, and audit trails.

**Package Name**: `grazulex/laravel-sharelink`
**Requirements**: PHP 8.3+, Laravel 11.x | 12.x

## Development Commands

### Testing
```bash
# Run all tests with coverage
composer test
# Or directly with Pest
./vendor/bin/pest --colors=always --coverage
```

### Code Quality
```bash
# Run Laravel Pint (code style fixer)
composer pint
# Or directly
./vendor/bin/pint

# Run PHPStan static analysis (level 5)
composer phpstan
# Or directly
./vendor/bin/phpstan analyse --memory-limit=2G --configuration=phpstan.neon

# Run Rector for automated refactoring
composer rector
# Or directly
./vendor/bin/rector

# Run all quality checks in sequence
composer full
```

### Artisan Commands (Package)
The package provides these commands that can be tested during development:
```bash
# Create a share link
php artisan sharelink:create /path/to/file --expires=60 --max-clicks=5

# List all share links
php artisan sharelink:list --active --expired

# Revoke a link by token
php artisan sharelink:revoke abc123xyz

# Prune expired/revoked links older than N days
php artisan sharelink:prune --days=7
```

## Architecture Overview

### Core Service Layer
The package follows a service-oriented architecture with clear separation of concerns:

**ShareLinkManager** (`src/Services/ShareLinkManager.php`):
- Primary service for creating share links via a fluent `PendingShareLink` builder
- Handles URL generation (regular and signed URLs)
- Provides link extension functionality
- The `PendingShareLink` class builds up link configuration before calling `generate()` to persist

**ShareLinkRevoker** (`src/Services/ShareLinkRevoker.php`):
- Dedicated service for revoking links
- Fires `ShareLinkRevoked` events

### Model Layer
**ShareLink Model** (`src/Models/ShareLink.php`):
- Uses UUID primary keys via `HasUuids` trait
- Stores `resource` as JSON (can be string path or array for routes/models)
- Stores `metadata` as JSON for extensibility (IP filters, burn flags, etc.)
- Automatically generates 32-character random tokens on creation
- Dynamic `fillable` attributes: adds `created_by` when user tracking is enabled
- Key methods: `isExpired()`, `isRevoked()`, `incrementClicks()`, `markAccessed()`

### HTTP Layer
**ShareLinkController** (`src/Http/Controllers/ShareLinkController.php`):
- Main controller handling link access via `show()` method
- Middleware (`EnsureShareLinkIsValid`) validates token, expiration, revocation, IP filters, and rate limits BEFORE controller execution
- Handles password validation with throttling (429 responses on too many attempts)
- Increments click count and updates audit fields (first/last access, IP)
- Implements burn-after-reading: revokes or deletes link after first successful access
- Supports multiple resource types:
  - **Local files**: Direct streaming, supports HTTP Range requests for partial content (206)
  - **X-Sendfile/X-Accel-Redirect**: Optimized file delivery via web server
  - **Storage disks**: Handles `disk:path` format for Laravel Storage
  - **Routes**: Redirects to named routes with parameters
  - **Models**: Returns JSON preview (app can customize)
- Content negotiation: Returns JSON resource for API clients

**ManageShareLinkController** (`src/Http/Controllers/ManageShareLinkController.php`):
- Optional management endpoints (enabled via config)
- POST `/share/{token}/revoke` - Revoke a link
- POST `/share/{token}/extend` - Extend expiration
- Optional Gate authorization via `sharelink.management.gate` config

### Middleware & Security
**EnsureShareLinkIsValid** (`src/Http/Middleware/EnsureShareLinkIsValid.php`):
- Validates token exists and not revoked/expired
- Enforces max_clicks limits
- Checks IP allow/deny lists (global config + per-link metadata)
- Rate limiting per token (configurable max attempts/decay window)
- Returns standardized JSON error responses with status codes

### Events & Observability
The package fires events for all major actions:
- `ShareLinkCreated` - When a link is generated
- `ShareLinkAccessed` - On successful access
- `ShareLinkRevoked` - When manually revoked
- `ShareLinkExpired` - When accessed after expiration

**ShareLinkObserver** (`src/Support/ShareLinkObserver.php`):
- Subscribes to all events when `observability.enabled` is true
- Logs events (non-PII) when `observability.log` is enabled
- Can push to metrics sinks when `observability.metrics` is enabled

### Configuration Architecture
Configuration (`config/sharelink.php`) uses a nested structure:

- **route**: Prefix, middleware for access routes
- **management**: Enable/disable, middleware, gate for management endpoints
- **signed**: Enable signed URLs, require them, default TTL
- **burn**: Burn-after-reading settings (enabled, auto-detect, strategy)
- **limits**: IP filtering (allow/deny), rate limiting, password throttling
- **delivery**: X-Sendfile, X-Accel-Redirect support
- **schedule**: Automated pruning via Laravel scheduler
- **observability**: Logging and metrics integration
- **user_tracking**: Optional foreign key to users table (flexible ID types: bigint, uuid, ulid)

All settings have environment variable overrides (e.g., `SHARELINK_BURN_ENABLED`).

### Database Schema
Single table `share_links` with columns:
- `id` (UUID)
- `token` (string, indexed, unique) - Used in URLs
- `resource` (JSON) - File path or route/model definition
- `password` (nullable, hashed)
- `expires_at`, `revoked_at` (nullable timestamps)
- `first_access_at`, `last_access_at`, `last_ip` (audit fields)
- `max_clicks`, `click_count` (usage limits)
- `metadata` (JSON) - Extensible storage for IP filters, burn flags, etc.
- `created_by` (nullable, added conditionally based on config)

## Key Design Patterns

### Fluent Builder Pattern
Link creation uses method chaining via `PendingShareLink`:
```php
ShareLink::create($resource)
    ->expiresIn(60)
    ->maxClicks(5)
    ->withPassword('secret')
    ->metadata(['allowed_ips' => ['192.168.1.0/24']])
    ->burnAfterReading()
    ->generate(); // Persists and returns ShareLink model
```

### Resource Type Polymorphism
The `resource` field stores different types:
- String: Local file path or `disk:path` format
- Array with `type=route`: `['type' => 'route', 'name' => 'route.name', 'params' => [...]]`
- Array with `type=model`: `['type' => 'model', 'class' => User::class, 'id' => 1]`

Controller logic branches based on resource structure.

### Middleware Validation
Security checks happen in middleware before controller execution, allowing early rejection with standardized error responses. This keeps controller focused on resource delivery.

### Event-Driven Observability
All actions fire events; observers can be enabled/disabled via config. This allows flexible logging, metrics, and integrations without cluttering core logic.

### Dynamic User Tracking
The `created_by` column and foreign key are conditionally added based on `user_tracking.enabled` config. This allows the package to work standalone or integrate with user systems flexibly.

## Testing Strategy

Tests use Pest with Orchestra Testbench for Laravel package testing. Test structure:
- `tests/Unit/` - Unit tests for services, models, validation
- `tests/Feature/` - HTTP tests for controllers, middleware
- Tests use in-memory SQLite databases
- The `workbench/` directory provides a minimal Laravel app structure for testing

When writing tests:
- Use Pest's `it()` syntax
- Mock external dependencies (filesystem, storage)
- Test all security features (expiration, revocation, password, IP filtering, rate limiting)
- Verify event firing with `Event::fake()`

## Common Development Patterns

### Adding New Security Features
1. Add configuration in `config/sharelink.php`
2. Update middleware (`EnsureShareLinkIsValid`) if it's an access control feature
3. Update controller (`ShareLinkController`) if it affects delivery
4. Add tests for the new feature
5. Update documentation in README/Wiki

### Adding New Resource Types
1. Extend the `resource` JSON structure with a new `type` value
2. Add handling logic in `ShareLinkController::show()` to detect and process the type
3. Update `PendingShareLink` if builder methods are needed
4. Add tests covering the new resource type

### Modifying Validation Logic
Validation primarily happens in `EnsureShareLinkIsValid` middleware. Consider:
- Order of checks (fast checks first, expensive checks last)
- Standardized JSON error format with proper HTTP status codes
- Rate limiting and throttling for abuse prevention

## Package Publishing

This is a Laravel package meant for Composer distribution:
- Source code in `src/`
- Config published via `sharelink-config` tag
- Migrations published via `sharelink-migrations` tag
- Auto-discovery via `extra.laravel.providers` in composer.json
- Facade: `Grazulex\ShareLink\Facades\ShareLink`

## Dependencies

Core dependencies:
- `illuminate/support` ^11.0|^12.0
- `nesbot/carbon` ^3.10
- `symfony/yaml` ^7.3

Dev dependencies:
- `pestphp/pest` ^3.8 (testing)
- `laravel/pint` 1.24.0 (code style)
- `larastan/larastan` ^3.4 (static analysis)
- `rector/rector` ^2.0 (refactoring)
- `orchestra/testbench` ^9.0|^10.0 (package testing)

<!-- BACKLOG.MD GUIDELINES START -->
# Instructions for the usage of Backlog.md CLI Tool

## Backlog.md: Comprehensive Project Management Tool via CLI

### Assistant Objective

Efficiently manage all project tasks, status, and documentation using the Backlog.md CLI, ensuring all project metadata
remains fully synchronized and up-to-date.

### Core Capabilities

- ✅ **Task Management**: Create, edit, assign, prioritize, and track tasks with full metadata
- ✅ **Search**: Fuzzy search across tasks, documents, and decisions with `backlog search`
- ✅ **Acceptance Criteria**: Granular control with add/remove/check/uncheck by index
- ✅ **Board Visualization**: Terminal-based Kanban board (`backlog board`) and web UI (`backlog browser`)
- ✅ **Git Integration**: Automatic tracking of task states across branches
- ✅ **Dependencies**: Task relationships and subtask hierarchies
- ✅ **Documentation & Decisions**: Structured docs and architectural decision records
- ✅ **Export & Reporting**: Generate markdown reports and board snapshots
- ✅ **AI-Optimized**: `--plain` flag provides clean text output for AI processing

### Why This Matters to You (AI Agent)

1. **Comprehensive system** - Full project management capabilities through CLI
2. **The CLI is the interface** - All operations go through `backlog` commands
3. **Unified interaction model** - You can use CLI for both reading (`backlog task 1 --plain`) and writing (
   `backlog task edit 1`)
4. **Metadata stays synchronized** - The CLI handles all the complex relationships

### Key Understanding

- **Tasks** live in `backlog/tasks/` as `task-<id> - <title>.md` files
- **You interact via CLI only**: `backlog task create`, `backlog task edit`, etc.
- **Use `--plain` flag** for AI-friendly output when viewing/listing
- **Never bypass the CLI** - It handles Git, metadata, file naming, and relationships

---

# ⚠️ CRITICAL: NEVER EDIT TASK FILES DIRECTLY. Edit Only via CLI

**ALL task operations MUST use the Backlog.md CLI commands**

- ✅ **DO**: Use `backlog task edit` and other CLI commands
- ✅ **DO**: Use `backlog task create` to create new tasks
- ✅ **DO**: Use `backlog task edit <id> --check-ac <index>` to mark acceptance criteria
- ❌ **DON'T**: Edit markdown files directly
- ❌ **DON'T**: Manually change checkboxes in files
- ❌ **DON'T**: Add or modify text in task files without using CLI

**Why?** Direct file editing breaks metadata synchronization, Git tracking, and task relationships.

---

## 1. Source of Truth & File Structure

### 📖 **UNDERSTANDING** (What you'll see when reading)

- Markdown task files live under **`backlog/tasks/`** (drafts under **`backlog/drafts/`**)
- Files are named: `task-<id> - <title>.md` (e.g., `task-42 - Add GraphQL resolver.md`)
- Project documentation is in **`backlog/docs/`**
- Project decisions are in **`backlog/decisions/`**

### 🔧 **ACTING** (How to change things)

- **All task operations MUST use the Backlog.md CLI tool**
- This ensures metadata is correctly updated and the project stays in sync
- **Always use `--plain` flag** when listing or viewing tasks for AI-friendly text output

---

## 2. Common Mistakes to Avoid

### ❌ **WRONG: Direct File Editing**

```markdown
# DON'T DO THIS:

1. Open backlog/tasks/task-7 - Feature.md in editor
2. Change "- [ ]" to "- [x]" manually
3. Add notes directly to the file
4. Save the file
```

### ✅ **CORRECT: Using CLI Commands**

```bash
# DO THIS INSTEAD:
backlog task edit 7 --check-ac 1  # Mark AC #1 as complete
backlog task edit 7 --notes "Implementation complete"  # Add notes
backlog task edit 7 -s "In Progress" -a @agent-k  # Multiple commands: change status and assign the task when you start working on the task
```

---

## 3. Understanding Task Format (Read-Only Reference)

⚠️ **FORMAT REFERENCE ONLY** - The following sections show what you'll SEE in task files.
**Never edit these directly! Use CLI commands to make changes.**

### Task Structure You'll See

```markdown
---
id: task-42
title: Add GraphQL resolver
status: To Do
assignee: [@sara]
labels: [backend, api]
---

## Description

Brief explanation of the task purpose.

## Acceptance Criteria

<!-- AC:BEGIN -->

- [ ] #1 First criterion
- [x] #2 Second criterion (completed)
- [ ] #3 Third criterion

<!-- AC:END -->

## Implementation Plan

1. Research approach
2. Implement solution

## Implementation Notes

Summary of what was done.
```

### How to Modify Each Section

| What You Want to Change | CLI Command to Use                                       |
|-------------------------|----------------------------------------------------------|
| Title                   | `backlog task edit 42 -t "New Title"`                    |
| Status                  | `backlog task edit 42 -s "In Progress"`                  |
| Assignee                | `backlog task edit 42 -a @sara`                          |
| Labels                  | `backlog task edit 42 -l backend,api`                    |
| Description             | `backlog task edit 42 -d "New description"`              |
| Add AC                  | `backlog task edit 42 --ac "New criterion"`              |
| Check AC #1             | `backlog task edit 42 --check-ac 1`                      |
| Uncheck AC #2           | `backlog task edit 42 --uncheck-ac 2`                    |
| Remove AC #3            | `backlog task edit 42 --remove-ac 3`                     |
| Add Plan                | `backlog task edit 42 --plan "1. Step one\n2. Step two"` |
| Add Notes (replace)     | `backlog task edit 42 --notes "What I did"`              |
| Append Notes            | `backlog task edit 42 --append-notes "Another note"` |

---

## 4. Defining Tasks

### Creating New Tasks

**Always use CLI to create tasks:**

```bash
# Example
backlog task create "Task title" -d "Description" --ac "First criterion" --ac "Second criterion"
```

### Title (one liner)

Use a clear brief title that summarizes the task.

### Description (The "why")

Provide a concise summary of the task purpose and its goal. Explains the context without implementation details.

### Acceptance Criteria (The "what")

**Understanding the Format:**

- Acceptance criteria appear as numbered checkboxes in the markdown files
- Format: `- [ ] #1 Criterion text` (unchecked) or `- [x] #1 Criterion text` (checked)

**Managing Acceptance Criteria via CLI:**

⚠️ **IMPORTANT: How AC Commands Work**

- **Adding criteria (`--ac`)** accepts multiple flags: `--ac "First" --ac "Second"` ✅
- **Checking/unchecking/removing** accept multiple flags too: `--check-ac 1 --check-ac 2` ✅
- **Mixed operations** work in a single command: `--check-ac 1 --uncheck-ac 2 --remove-ac 3` ✅

```bash
# Examples

# Add new criteria (MULTIPLE values allowed)
backlog task edit 42 --ac "User can login" --ac "Session persists"

# Check specific criteria by index (MULTIPLE values supported)
backlog task edit 42 --check-ac 1 --check-ac 2 --check-ac 3  # Check multiple ACs
# Or check them individually if you prefer:
backlog task edit 42 --check-ac 1    # Mark #1 as complete
backlog task edit 42 --check-ac 2    # Mark #2 as complete

# Mixed operations in single command
backlog task edit 42 --check-ac 1 --uncheck-ac 2 --remove-ac 3

# ❌ STILL WRONG - These formats don't work:
# backlog task edit 42 --check-ac 1,2,3  # No comma-separated values
# backlog task edit 42 --check-ac 1-3    # No ranges
# backlog task edit 42 --check 1         # Wrong flag name

# Multiple operations of same type
backlog task edit 42 --uncheck-ac 1 --uncheck-ac 2  # Uncheck multiple ACs
backlog task edit 42 --remove-ac 2 --remove-ac 4    # Remove multiple ACs (processed high-to-low)
```

**Key Principles for Good ACs:**

- **Outcome-Oriented:** Focus on the result, not the method.
- **Testable/Verifiable:** Each criterion should be objectively testable
- **Clear and Concise:** Unambiguous language
- **Complete:** Collectively cover the task scope
- **User-Focused:** Frame from end-user or system behavior perspective

Good Examples:

- "User can successfully log in with valid credentials"
- "System processes 1000 requests per second without errors"
- "CLI preserves literal newlines in description/plan/notes; `\\n` sequences are not auto‑converted"

Bad Example (Implementation Step):

- "Add a new function handleLogin() in auth.ts"
- "Define expected behavior and document supported input patterns"

### Task Breakdown Strategy

1. Identify foundational components first
2. Create tasks in dependency order (foundations before features)
3. Ensure each task delivers value independently
4. Avoid creating tasks that block each other

### Task Requirements

- Tasks must be **atomic** and **testable** or **verifiable**
- Each task should represent a single unit of work for one PR
- **Never** reference future tasks (only tasks with id < current task id)
- Ensure tasks are **independent** and don't depend on future work

---

## 5. Implementing Tasks

### 5.1. First step when implementing a task

The very first things you must do when you take over a task are:

* set the task in progress
* assign it to yourself

```bash
# Example
backlog task edit 42 -s "In Progress" -a @{myself}
```

### 5.2. Create an Implementation Plan (The "how")

Previously created tasks contain the why and the what. Once you are familiar with that part you should think about a
plan on **HOW** to tackle the task and all its acceptance criteria. This is your **Implementation Plan**.
First do a quick check to see if all the tools that you are planning to use are available in the environment you are
working in.   
When you are ready, write it down in the task so that you can refer to it later.

```bash
# Example
backlog task edit 42 --plan "1. Research codebase for references\n2Research on internet for similar cases\n3. Implement\n4. Test"
```

## 5.3. Implementation

Once you have a plan, you can start implementing the task. This is where you write code, run tests, and make sure
everything works as expected. Follow the acceptance criteria one by one and MARK THEM AS COMPLETE as soon as you
finish them.

### 5.4 Implementation Notes (PR description)

When you are done implementing a tasks you need to prepare a PR description for it.
Because you cannot create PRs directly, write the PR as a clean description in the task notes.
Append notes progressively during implementation using `--append-notes`:

```
backlog task edit 42 --append-notes "Implemented X" --append-notes "Added tests"
```

```bash
# Example
backlog task edit 42 --notes "Implemented using pattern X because Reason Y, modified files Z and W"
```

**IMPORTANT**: Do NOT include an Implementation Plan when creating a task. The plan is added only after you start the
implementation.

- Creation phase: provide Title, Description, Acceptance Criteria, and optionally labels/priority/assignee.
- When you begin work, switch to edit, set the task in progress and assign to yourself
  `backlog task edit <id> -s "In Progress" -a "..."`.
- Think about how you would solve the task and add the plan: `backlog task edit <id> --plan "..."`.
- Add Implementation Notes only after completing the work: `backlog task edit <id> --notes "..."` (replace) or append progressively using `--append-notes`.

## Phase discipline: What goes where

- Creation: Title, Description, Acceptance Criteria, labels/priority/assignee.
- Implementation: Implementation Plan (after moving to In Progress and assigning to yourself).
- Wrap-up: Implementation Notes (Like a PR description), AC and Definition of Done checks.

**IMPORTANT**: Only implement what's in the Acceptance Criteria. If you need to do more, either:

1. Update the AC first: `backlog task edit 42 --ac "New requirement"`
2. Or create a new follow up task: `backlog task create "Additional feature"`

---

## 6. Typical Workflow

```bash
# 1. Identify work
backlog task list -s "To Do" --plain

# 2. Read task details
backlog task 42 --plain

# 3. Start work: assign yourself & change status
backlog task edit 42 -s "In Progress" -a @myself

# 4. Add implementation plan
backlog task edit 42 --plan "1. Analyze\n2. Refactor\n3. Test"

# 5. Work on the task (write code, test, etc.)

# 6. Mark acceptance criteria as complete (supports multiple in one command)
backlog task edit 42 --check-ac 1 --check-ac 2 --check-ac 3  # Check all at once
# Or check them individually if preferred:
# backlog task edit 42 --check-ac 1
# backlog task edit 42 --check-ac 2
# backlog task edit 42 --check-ac 3

# 7. Add implementation notes (PR Description)
backlog task edit 42 --notes "Refactored using strategy pattern, updated tests"

# 8. Mark task as done
backlog task edit 42 -s Done
```

---

## 7. Definition of Done (DoD)

A task is **Done** only when **ALL** of the following are complete:

### ✅ Via CLI Commands:

1. **All acceptance criteria checked**: Use `backlog task edit <id> --check-ac <index>` for each
2. **Implementation notes added**: Use `backlog task edit <id> --notes "..."`
3. **Status set to Done**: Use `backlog task edit <id> -s Done`

### ✅ Via Code/Testing:

4. **Tests pass**: Run test suite and linting
5. **Documentation updated**: Update relevant docs if needed
6. **Code reviewed**: Self-review your changes
7. **No regressions**: Performance, security checks pass

⚠️ **NEVER mark a task as Done without completing ALL items above**

---

## 8. Finding Tasks and Content with Search

When users ask you to find tasks related to a topic, use the `backlog search` command with `--plain` flag:

```bash
# Search for tasks about authentication
backlog search "auth" --plain

# Search only in tasks (not docs/decisions)
backlog search "login" --type task --plain

# Search with filters
backlog search "api" --status "In Progress" --plain
backlog search "bug" --priority high --plain
```

**Key points:**
- Uses fuzzy matching - finds "authentication" when searching "auth"
- Searches task titles, descriptions, and content
- Also searches documents and decisions unless filtered with `--type task`
- Always use `--plain` flag for AI-readable output

---

## 9. Quick Reference: DO vs DON'T

### Viewing and Finding Tasks

| Task         | ✅ DO                        | ❌ DON'T                         |
|--------------|-----------------------------|---------------------------------|
| View task    | `backlog task 42 --plain`   | Open and read .md file directly |
| List tasks   | `backlog task list --plain` | Browse backlog/tasks folder     |
| Check status | `backlog task 42 --plain`   | Look at file content            |
| Find by topic| `backlog search "auth" --plain` | Manually grep through files |

### Modifying Tasks

| Task          | ✅ DO                                 | ❌ DON'T                           |
|---------------|--------------------------------------|-----------------------------------|
| Check AC      | `backlog task edit 42 --check-ac 1`  | Change `- [ ]` to `- [x]` in file |
| Add notes     | `backlog task edit 42 --notes "..."` | Type notes into .md file          |
| Change status | `backlog task edit 42 -s Done`       | Edit status in frontmatter        |
| Add AC        | `backlog task edit 42 --ac "New"`    | Add `- [ ] New` to file           |

---

## 10. Complete CLI Command Reference

### Task Creation

| Action           | Command                                                                             |
|------------------|-------------------------------------------------------------------------------------|
| Create task      | `backlog task create "Title"`                                                       |
| With description | `backlog task create "Title" -d "Description"`                                      |
| With AC          | `backlog task create "Title" --ac "Criterion 1" --ac "Criterion 2"`                 |
| With all options | `backlog task create "Title" -d "Desc" -a @sara -s "To Do" -l auth --priority high` |
| Create draft     | `backlog task create "Title" --draft`                                               |
| Create subtask   | `backlog task create "Title" -p 42`                                                 |

### Task Modification

| Action           | Command                                     |
|------------------|---------------------------------------------|
| Edit title       | `backlog task edit 42 -t "New Title"`       |
| Edit description | `backlog task edit 42 -d "New description"` |
| Change status    | `backlog task edit 42 -s "In Progress"`     |
| Assign           | `backlog task edit 42 -a @sara`             |
| Add labels       | `backlog task edit 42 -l backend,api`       |
| Set priority     | `backlog task edit 42 --priority high`      |

### Acceptance Criteria Management

| Action              | Command                                                                     |
|---------------------|-----------------------------------------------------------------------------|
| Add AC              | `backlog task edit 42 --ac "New criterion" --ac "Another"`                  |
| Remove AC #2        | `backlog task edit 42 --remove-ac 2`                                        |
| Remove multiple ACs | `backlog task edit 42 --remove-ac 2 --remove-ac 4`                          |
| Check AC #1         | `backlog task edit 42 --check-ac 1`                                         |
| Check multiple ACs  | `backlog task edit 42 --check-ac 1 --check-ac 3`                            |
| Uncheck AC #3       | `backlog task edit 42 --uncheck-ac 3`                                       |
| Mixed operations    | `backlog task edit 42 --check-ac 1 --uncheck-ac 2 --remove-ac 3 --ac "New"` |

### Task Content

| Action           | Command                                                  |
|------------------|----------------------------------------------------------|
| Add plan         | `backlog task edit 42 --plan "1. Step one\n2. Step two"` |
| Add notes        | `backlog task edit 42 --notes "Implementation details"`  |
| Add dependencies | `backlog task edit 42 --dep task-1 --dep task-2`         |

### Multi‑line Input (Description/Plan/Notes)

The CLI preserves input literally. Shells do not convert `\n` inside normal quotes. Use one of the following to insert real newlines:

- Bash/Zsh (ANSI‑C quoting):
  - Description: `backlog task edit 42 --desc $'Line1\nLine2\n\nFinal'`
  - Plan: `backlog task edit 42 --plan $'1. A\n2. B'`
  - Notes: `backlog task edit 42 --notes $'Done A\nDoing B'`
  - Append notes: `backlog task edit 42 --append-notes $'Progress update line 1\nLine 2'`
- POSIX portable (printf):
  - `backlog task edit 42 --notes "$(printf 'Line1\nLine2')"`
- PowerShell (backtick n):
  - `backlog task edit 42 --notes "Line1`nLine2"`

Do not expect `"...\n..."` to become a newline. That passes the literal backslash + n to the CLI by design.

Descriptions support literal newlines; shell examples may show escaped `\\n`, but enter a single `\n` to create a newline.

### Implementation Notes Formatting

- Keep implementation notes human-friendly and PR-ready: use short paragraphs or
  bullet lists instead of a single long line.
- Lead with the outcome, then add supporting details (e.g., testing, follow-up
  actions) on separate lines or bullets.
- Prefer Markdown bullets (`-` for unordered, `1.` for ordered) so Maintainers
  can paste notes straight into GitHub without additional formatting.
- When using CLI flags like `--append-notes`, remember to include explicit
  newlines. Example:

  ```bash
  backlog task edit 42 --append-notes $'- Added new API endpoint\n- Updated tests\n- TODO: monitor staging deploy'
  ```

### Task Operations

| Action             | Command                                      |
|--------------------|----------------------------------------------|
| View task          | `backlog task 42 --plain`                    |
| List tasks         | `backlog task list --plain`                  |
| Search tasks       | `backlog search "topic" --plain`              |
| Search with filter | `backlog search "api" --status "To Do" --plain` |
| Filter by status   | `backlog task list -s "In Progress" --plain` |
| Filter by assignee | `backlog task list -a @sara --plain`         |
| Archive task       | `backlog task archive 42`                    |
| Demote to draft    | `backlog task demote 42`                     |

---

## Common Issues

| Problem              | Solution                                                           |
|----------------------|--------------------------------------------------------------------|
| Task not found       | Check task ID with `backlog task list --plain`                     |
| AC won't check       | Use correct index: `backlog task 42 --plain` to see AC numbers     |
| Changes not saving   | Ensure you're using CLI, not editing files                         |
| Metadata out of sync | Re-edit via CLI to fix: `backlog task edit 42 -s <current-status>` |

---

## Remember: The Golden Rule

**🎯 If you want to change ANYTHING in a task, use the `backlog task edit` command.**
**📖 Use CLI to read tasks, exceptionally READ task files directly, never WRITE to them.**

Full help available: `backlog --help`

<!-- BACKLOG.MD GUIDELINES END -->
