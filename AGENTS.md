# AGENTS.md

This file defines working rules for AI agents and contributors in this repository.
Treat this as the project contract.

## 1) Project Context

- Product: Uninest Kuppi Platform (peer learning for Sri Lankan university students).
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
  - `students_*` in `modules/students/*`
  - `batches_*` in `modules/batches/*`
  - `moderators_*` in `modules/moderators/*`
  - `topics_*` in `modules/topics/*`
  - `resources_*` in `modules/resources/*`
  - `comments_*` in `modules/comments/*`
  - `community_*` in `modules/community/*`
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
- Operational role hierarchy is: `student < coordinator < moderator`.
- `admin` is a separate control role (provisioned internally), not part of onboarding flow.
- Moderator signup creates a pending batch request.
- Admin approves/rejects moderator batch request.
- On approval:
  - batch becomes `approved`,
  - immutable `batch_code` is assigned (`BATCH-XXXXXX` style),
  - moderator `users.batch_id` is set.
- On rejected moderator batch requests, only the primary batch owner (`batches.moderator_user_id`) can resubmit.
- Student signup requires active approved `batch_code`.
- Student signup creates pending join request.
- Moderator (or admin override) approves/rejects student join request.
- Student can access batch content only when join request is `approved` and batch status is `approved`.
- Student batch reassignment after first approved assignment is blocked.
- `users.first_approved_batch_id` is the immutable lock source for this rule once set.
- If a student is removed from a batch, resubmission is allowed only for their locked batch (`first_approved_batch_id`).
- Pending/rejected users can log in but must be gated through `/onboarding`.
- Admin bypasses onboarding gate.

## 6) Data Model Rules

Primary tables:

- `users`
- `universities`
- `batches`
- `student_batch_requests`
- `subjects` (batch-scoped)
- `topics` (subject-scoped)
- `subject_coordinators`
- `resources` (topic-scoped, approval-based)
- `resource_update_requests` (staged edits)
- `resource_ratings`
- `feed_posts` (batch-scoped community posts)
- `feed_post_likes`
- `feed_post_saves`
- `feed_reports`
- `comments` (polymorphic target)
- `password_reset_tokens`

Non-negotiable integrity rules:

- `subjects.batch_id` is required.
- `topics.subject_id` is required.
- `batch_code` is unique.
- Each batch has exactly one primary moderator owner (`batches.moderator_user_id`).
- A moderator can own at most one batch (`batches.moderator_user_id` unique).
- Multiple moderators may be assigned to the same batch via `users.batch_id`.
- One join-request row per student (`student_user_id` unique in `student_batch_requests`).
- `users.first_approved_batch_id` becomes immutable once first approved assignment is recorded.
- One save row per `(post_id, user_id)` in `feed_post_saves`.
- `feed_reports.target_type` is restricted to `post | comment`.
- `feed_reports.status` is restricted to `open | dismissed | resolved`.

When changing DB schema:

- Update `database/schema.sql` first (authoritative source).
- Keep foreign keys consistent with existing delete policies.

## 7) Access and Scoping Rules

- Student:
  - can only see subjects from their `users.batch_id`.
  - can only view topics for subjects in their own batch.
- Moderator:
  - can only manage data for their own batch (unless explicitly admin flow).
  - can remove students from their own batch only (no student add/edit/delete account actions).
  - can CRUD topics only for subjects in their own batch.
- Coordinator:
  - can CRUD topics only for subjects assigned to them in `subject_coordinators`.
- Admin:
  - unrestricted access for approvals and cross-batch management.
  - has full student CRUD access from admin flows.
  - has full moderator CRUD access from admin provisioning flows.
  - has full batch CRUD access from admin provisioning flows.
  - has full topic CRUD access for all subjects.

Never introduce queries that bypass batch scoping for non-admin users.
Use `middleware_exact_role('admin')` for admin provisioning routes.

## 7.2) Resource Interaction Rules (Do Not Break)

- Published resources stay scoped by existing subject/topic access checks.
- Ratings apply to published resources only.
  - rating is `student`-only,
  - one rating per `(resource_id, student_user_id)`,
  - uploader cannot rate own resource.
- Comments apply to published resources only in v1 using `comments.target_type = 'resource'`.
- Comment nesting is capped at 3 visible levels (`depth` 0..2).
- Comment permissions:
  - any readable onboarded user can create comments/replies,
  - only author can edit their own comment,
  - delete allowed for author, moderator/admin, and coordinator only within assigned subjects.
- Keep comment target access centralized in helpers/controllers; do not hardcode target-specific access rules in generic comment model functions.

## 7.3) Community Interaction Rules (Do Not Break)

- Community feed posts are batch-scoped for non-admin users.
- Feed search scope is posts only (post body + author + subject fields), not comments.
- Pinned behavior:
  - only `announcement` posts may be pinned,
  - only moderator for own batch (or admin override) can pin/unpin,
  - max pinned announcements per batch: `3`.
- Question workflow:
  - only `question` posts can be marked solved/reopened,
  - only the post author can resolve/reopen.
- Save/report actions:
  - any authenticated onboarded user with read access can save/report,
  - self-reporting (own post/comment) is blocked,
  - saved posts are private to the saving user.
- Moderation queue:
  - moderator sees/actions reports only for own batch,
  - admin sees/actions reports across all batches.
- Report actions:
  - dismiss closes report with no content deletion,
  - remove deletes reported target (post/comment) and resolves related open reports.

## 7.1) Admin Provisioning Route Groups

- Student management:
  - `GET /students`
  - `GET /students/create`
  - `POST /students`
  - `GET /students/{id}/edit`
  - `POST /students/{id}`
  - `POST /students/{id}/delete`
- Moderator scoped student removal:
  - `POST /students/{id}/remove` (moderator own-batch only)
- Moderator management:
  - `GET /admin/moderators`
  - `GET /admin/moderators/create`
  - `POST /admin/moderators`
  - `GET /admin/moderators/{id}/edit`
  - `POST /admin/moderators/{id}`
  - `POST /admin/moderators/{id}/delete`
- Batch management:
  - `GET /admin/batches`
  - `GET /admin/batches/create`
  - `POST /admin/batches`
  - `GET /admin/batches/{id}/edit`
  - `POST /admin/batches/{id}`
  - `POST /admin/batches/{id}/delete`
- Topic management (subject-scoped):
  - `GET /dashboard/subjects/{id}/topics` (batch-scoped read view)
  - `GET /subjects/{id}/topics`
  - `GET /subjects/{id}/topics/create`
  - `POST /subjects/{id}/topics`
  - `GET /subjects/{id}/topics/{topicId}/edit`
  - `POST /subjects/{id}/topics/{topicId}`
  - `POST /subjects/{id}/topics/{topicId}/delete`
- Community:
  - `GET /dashboard/community`
  - `GET /dashboard/community/create`
  - `POST /dashboard/community`
  - `GET /dashboard/community/{id}`
  - `POST /dashboard/community/{id}`
  - `POST /dashboard/community/{id}/delete`
  - `POST /dashboard/community/{id}/like`
  - `POST /dashboard/community/{id}/save`
  - `POST /dashboard/community/{id}/report`
  - `POST /dashboard/community/{id}/question/resolve`
  - `POST /dashboard/community/{id}/question/reopen`
  - `POST /dashboard/community/{id}/pin`
  - `POST /dashboard/community/{id}/unpin`
  - `POST /dashboard/community/{id}/comments`
  - `POST /dashboard/community/{id}/comments/{commentId}`
  - `POST /dashboard/community/{id}/comments/{commentId}/delete`
  - `POST /dashboard/community/{id}/comments/{commentId}/report`
  - `GET /community/{id}/image`
  - `GET /my-posts`
  - `GET /my-posts/{id}/edit`
  - `POST /my-posts/{id}`
  - `POST /my-posts/{id}/delete`
  - `GET /saved-posts`
  - `GET /dashboard/community/reports`
  - `POST /dashboard/community/reports/{id}/dismiss`
  - `POST /dashboard/community/reports/{id}/remove`

## 8) Auth and Password Reset Rules

- Forgot/reset password flow is email-token based.
- SMTP config uses:
  - `GMAIL_USERNAME`
  - `GMAIL_APP_PASSWORD`
- `GMAIL_APP_PASSWORD` must not contain unquoted spaces in `.env`.
  - Prefer: no spaces (recommended) or wrap value in quotes.

## 9) UI/UX Rules for This Repo

- Use existing style system in `public/assets/css/style.css` and `public/assets/css/dashboard-modern.css`.
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
- Do not allow batch deletion if any user is locked to it through `users.first_approved_batch_id`.
- Do not introduce direct SQL using untrusted input without bound params.
- Do not add duplicate global helper names.
- Do not remove polymorphic resource-comment cleanup from resource/topic/subject/batch deletion flows.

## 12) Definition of Done

A change is complete when:

1. business rules still hold,
2. route -> handler mapping is valid,
3. function checker passes,
4. syntax checks pass,
5. UI remains consistent with current product style.
