# AGENTS.md

This file defines working rules for AI agents and contributors in this repository.
Treat this as the project contract.

## 1) Project Context

- Product: UniNest Kuppi Platform (peer learning for Sri Lankan university students).
- Stack: Plain PHP (function-based), MySQL, server-rendered views, no framework.
- Architecture: Module folders with global functions loaded at bootstrap.
- Current onboarding model is approval-based (admin + moderator approvals).

## 2) Architecture Snapshot

- Front controller: `public/index.php`
- Bootstrap and auto-loading: `core/bootstrap.php`
- Router: `core/router.php`
- Middleware: `core/middleware.php`
- Helpers (global): `core/helpers.php`
- Routes: `routes.php`
- Modules: `modules/<module>/`
- Authoritative DB schema: `database/schema.sql`

## 3) Function Naming Rules (Critical)

- This codebase uses global functions. Name collisions are the main scaling risk.
- Module functions must be prefixed with the module slug:
  - `subjects_*` in `modules/subjects/*`
  - `auth_*` in `modules/auth/*`
  - `dashboard_*` in `modules/dashboard/*`
- Legacy onboarding exceptions currently allowed:
  - `onboarding_*`, `admin_*`, `moderator_*`, `university_*`, `universities_*`
- New code should prefer strict module prefixing, even inside onboarding.

Run this before/after changes:

```bash
composer check:functions
```

The checker validates:
- duplicate global function names,
- module prefix violations,
- missing route handler functions.

## 4) Routing Contract

- Define routes only in `routes.php`.
- Use `route('METHOD', '/path', 'handler_function', [middlewares...]);`
- Handler should be a named function string, not anonymous closure, unless needed.
- Keep middleware explicit and role-aware.

Default protected pattern:

```php
['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('moderator')]
```

## 5) Onboarding Business Rules (Do Not Break)

- Public signup roles: `student`, `moderator` only.
- Moderator signup creates a pending batch request.
- Admin approves/rejects moderator batch request.
- On approval:
  - batch becomes `approved`,
  - immutable `batch_code` is assigned (`BATCH-XXXXXX` style),
  - moderator `users.batch_id` is set.
- Student signup requires active approved `batch_code`.
- Student signup creates pending join request.
- Moderator (or admin override) approves/rejects student join request.
- Student can access batch content only after approval.
- Student batch reassignment after first approved `users.batch_id` is blocked.
- Pending/rejected users can log in but must be gated through `/onboarding`.
- Admin bypasses onboarding gate.

## 6) Data Model Rules

Primary tables:
- `users`
- `universities`
- `batches`
- `student_batch_requests`
- `subjects` (batch-scoped)
- `password_reset_tokens`

Non-negotiable integrity rules:
- `subjects.batch_id` is required.
- `batch_code` is unique.
- One primary moderator per batch (`moderator_user_id` unique).
- One join-request row per student (`student_user_id` unique in `student_batch_requests`).

When changing DB schema:
- Update `database/schema.sql` first (authoritative source).
- Keep foreign keys consistent with existing delete policies.

## 7) Access and Scoping Rules

- Student:
  - can only see subjects from their `users.batch_id`.
- Moderator:
  - can only manage data for their own batch (unless explicitly admin flow).
- Admin:
  - unrestricted access for approvals and cross-batch management.

Never introduce queries that bypass batch scoping for non-admin users.

## 8) Auth and Password Reset Rules

- Forgot/reset password flow is email-token based.
- SMTP config uses:
  - `GMAIL_USERNAME`
  - `GMAIL_APP_PASSWORD`
- `GMAIL_APP_PASSWORD` must not contain unquoted spaces in `.env`.
  - Prefer: no spaces (recommended) or wrap value in quotes.

## 9) UI/UX Rules for This Repo

- Use existing style system in `public/assets/css/style.css`.
- Keep auth/dashboard views server-rendered, consistent with current aesthetic.
- Avoid introducing a new design system per page.
- Keep forms and spacing clean, readable, and consistent with existing components.

## 10) How to Add a New CRUD Module

Use scaffold command:

```bash
composer make:crud -- --module=announcements --table=announcements --field=title --append-routes
```

Generator output:
- `modules/<module>/models.php`
- `modules/<module>/controllers.php`
- `modules/<module>/views/index.php`
- `modules/<module>/views/create.php`
- `modules/<module>/views/edit.php`
- optional route block append to `routes.php`

After generation:
1. adjust model field mapping to real table columns,
2. add/adjust authorization logic,
3. run `composer check:functions`,
4. run `php -l` for changed files.

## 11) Change Safety Rules

- Do not rename existing handlers casually; routes depend on function strings.
- Do not remove onboarding checks from protected routes.
- Do not weaken role middleware.
- Do not introduce direct SQL using untrusted input without bound params.
- Do not add duplicate global helper names.

## 12) Definition of Done

A change is complete when:
1. business rules still hold,
2. route -> handler mapping is valid,
3. function checker passes,
4. syntax checks pass,
5. UI remains consistent with current product style.
