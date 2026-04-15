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
  - `quizzes_*` in `modules/quizzes/*`
  - `comments_*` in `modules/comments/*`
  - `community_*` in `modules/community/*`
  - `kuppi_*` in `modules/kuppi/*`
  - `feed_*` in `modules/feed/*`
  - `gpa_*` in `modules/gpa/*`
  - `profile_*` in `modules/profile/*`
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
- `quizzes` (subject-scoped, approval-gated, mode-based)
- `quiz_questions`
- `quiz_options`
- `quiz_attempts`
- `quiz_attempt_answers`
- `resources` (topic-scoped, approval-based)
- `resource_update_requests` (staged edits)
- `resource_ratings`
- `feed_posts` (batch-scoped community posts)
- `feed_post_likes`
- `feed_post_saves`
- `feed_reports`
- `comments` (polymorphic target)
- `kuppi_requests` (batch-scoped requested sessions)
- `kuppi_request_votes`
- `kuppi_conductor_applications`
- `kuppi_conductor_votes`
- `kuppi_scheduled_sessions`
- `kuppi_scheduled_session_hosts`
- `gpa_batch_grade_scales`
- `gpa_term_records`
- `gpa_term_subject_entries`
- `password_reset_tokens`

Non-negotiable integrity rules:

- `subjects.batch_id` is required.
- `topics.subject_id` is required.
- `quizzes.subject_id` is required.
- `quizzes.mode` is required and restricted to `practice | exam`.
- `batch_code` is unique.
- Each batch has exactly one primary moderator owner (`batches.moderator_user_id`).
- A moderator can own at most one batch (`batches.moderator_user_id` unique).
- Multiple moderators may be assigned to the same batch via `users.batch_id`.
- One join-request row per student (`student_user_id` unique in `student_batch_requests`).
- `users.first_approved_batch_id` becomes immutable once first approved assignment is recorded.
- One save row per `(post_id, user_id)` in `feed_post_saves`.
- One request-vote row per `(request_id, user_id)` in `kuppi_request_votes`.
- One conductor-application row per `(request_id, applicant_user_id)` in `kuppi_conductor_applications`.
- One conductor-vote row per `(application_id, voter_user_id)` in `kuppi_conductor_votes`.
- One attempt-answer row per `(attempt_id, question_id)` in `quiz_attempt_answers`.
- One grade-scale row per `(batch_id, letter_grade)` in `gpa_batch_grade_scales`.
- One GPA term record per `(user_id, batch_id, academic_year, semester)` in `gpa_term_records`.
- `feed_reports.target_type` is restricted to `post | comment`.
- `feed_reports.status` is restricted to `open | dismissed | resolved`.
- Leaderboard data is derived only from approved exam quizzes and student attempts.

When changing DB schema:

- Update `database/schema.sql` first (authoritative source).
- Keep foreign keys consistent with existing delete policies.

## 7) Access and Scoping Rules

- Student:
  - can only see subjects from their `users.batch_id`.
  - can only view topics for subjects in their own batch.
  - can view central feed items only from own batch.
  - can create quizzes in readable subjects.
  - can attempt approved quizzes in readable subjects.
  - can use GPA calculator for own records in own batch.
  - can manage own profile settings only.
- Moderator:
  - can only manage data for their own batch (unless explicitly admin flow).
  - can remove students from their own batch only (no student add/edit/delete account actions).
  - can CRUD topics only for subjects in their own batch.
  - can view central feed items only from own batch.
  - can review pending quizzes only for own-batch subjects.
  - can manage GPA grade scale only for own batch.
  - can manage own profile settings only.
- Coordinator:
  - can CRUD topics only for subjects assigned to them in `subject_coordinators`.
  - can view central feed items only from own batch.
  - can create quizzes in readable subjects.
  - can review pending quizzes only for assigned subjects.
  - can use GPA calculator for own records in own batch.
  - can manage own profile settings only.
- Admin:
  - unrestricted access for approvals and cross-batch management.
  - has full student CRUD access from admin flows.
  - has full moderator CRUD access from admin provisioning flows.
  - has full batch CRUD access from admin provisioning flows.
  - has full topic CRUD access for all subjects.
  - has full quiz review and analytics access across all subjects.
  - can view central feed only with explicit selected batch context.
  - has cross-batch GPA grade-scale management with explicit selected batch context.
  - can manage own profile settings only from profile utility routes.

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
- Quiz discussions also use `comments` polymorphic targets:
  - `target_type = 'quiz'` for quiz-level thread,
  - `target_type = 'quiz_question'` for per-question thread.
  - keep quiz comment access checks centralized in quiz controllers/helpers.

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

## 7.4) Kuppi Interaction Rules (Do Not Break)

- Requested Kuppi sessions are batch-scoped for non-admin users.
- Admin cross-batch browsing must use explicit selected batch context (for example `batch_id` on index), not implicit unscoped reads.
- Request CRUD:
  - create allowed only for `student` and `coordinator`,
  - edit allowed only for owner and only when request `status` is `open`,
  - delete allowed for owner, moderator of that request batch, and admin.
- Request voting:
  - allowed for readable batch members (`student`, `coordinator`, `moderator`) and admin in selected batch context,
  - self-voting on own request is blocked,
  - one active vote per user using `kuppi_request_votes` with toggle/switch behavior (`up`, `down`, or none).
- Conductor applications:
  - apply allowed only for `student` on open requests within readable scope,
  - one application per user per request (`kuppi_conductor_applications` unique `(request_id, applicant_user_id)`).
- Conductor voting:
  - allowed only for `student` on open requests within readable scope,
  - self-voting on own application is blocked,
  - one vote per `(application_id, voter_user_id)` with toggle behavior.
- Kuppi comments:
  - use `comments.target_type = 'kuppi_request'`,
  - keep comment depth/permissions centralized in comments helpers/controllers (no ad-hoc target checks inside generic model functions).
- Kuppi scheduling:
  - scheduler roles are `coordinator | moderator | admin` (students are read-only for scheduled pages),
  - wizard flow order is `Select Request -> Assign Hosts -> Set Schedule -> Review & Confirm`,
  - request-linked scheduling enforces one active linked session per request (`status = 'scheduled'`),
  - if no conductor applications exist for a request, schedulers may assign any batch `student|coordinator` as hosts,
  - conductor availability is a recommendation signal in scheduling UI (not a hard blocker),
  - linked request status transitions:
    - on schedule create: request `open -> scheduled`,
    - on linked session cancel/delete: request reopens to `open`,
    - on linked session complete: request moves to `completed`.
- Kuppi scheduling notifications:
  - notifications are best-effort and must not rollback successful schedule persistence,
  - failures should be logged via `error_log`,
  - recipient deduplication is required before send.

## 7.5) Quiz Interaction Rules (Do Not Break)

- Scope and visibility:
  - quiz belongs to exactly one subject,
  - learners can browse/attempt only approved quizzes in readable subjects,
  - non-admin users must never get cross-batch quiz leakage.
- Roles:
  - create/edit draft/rejected quizzes: `student | coordinator`,
  - review queue actions (approve/reject): `coordinator | moderator | admin` with scope checks,
  - coordinator review is assigned-subject only; moderator review is own-batch only; admin review is global.
- Lifecycle:
  - `draft -> pending -> approved/rejected`,
  - rejected quizzes are editable and can be resubmitted,
  - approved quizzes are immutable in v1/v2 flows.
- Mode behavior:
  - `practice`: immediate per-question server-side check and lock-on-check,
  - `exam`: correctness hidden until result page,
  - timer and expiry are server-enforced for all modes.
- Attempts and scoring:
  - multiple attempts allowed, best score is retained (`score_percent DESC`, tie by latest `submitted_at DESC`),
  - score formula remains `correct / total * 100` with no negative marking.
- Leaderboard:
  - subject-scoped only,
  - includes only student attempts on approved exam quizzes,
  - ranking tie-break: latest high-score `submitted_at DESC`.
- Analytics:
  - student analytics shows only own data,
  - reviewer analytics must be scope-filtered by role and subject visibility,
  - v2 analytics depth is question-level (no topic-tagging assumption).

## 7.6) GPA Utility Rules (Do Not Break)

- GPA calculator is a utility for official university exam grades only; do not derive GPA from platform quiz/resource marks.
- Subject rows in calculator must come from batch-scoped `subjects` filtered by `academic_year` + `semester`.
- Grade scale is batch-scoped:
  - manage roles are `moderator | admin`,
  - moderator scope is own batch only,
  - admin scope requires explicit selected `batch_id` context.
- GPA records are user-owned and term-scoped:
  - save roles are `student | coordinator`,
  - persistence key is `(user_id, batch_id, academic_year, semester)` (update in place),
  - non-admin users must never read/write GPA data across batches.

## 7.7) Profile Settings Rules (Do Not Break)

- Profile settings are self-service only:
  - route access is authenticated + onboarding-complete users,
  - updates must target only `auth_id()` (no user-id path/body targeting).
- Editable profile fields in v1:
  - `name`, `email`, `academic_year` only.
  - `role`, `university_id`, and `batch_id` remain read-only in this module.
- Password change in v1:
  - require `current_password`,
  - require `new_password` + confirmation with min length `8`,
  - reject reuse of current password.

## 7.8) Central Feed Rules (Do Not Break)

- Central feed is additive and read-only aggregation (no new feed storage tables).
- Route: `GET /dashboard/feed`.
- Data sources in v1:
  - `feed_posts` (batch-scoped),
  - `resources` where `status = 'published'`,
  - `quizzes` where `status = 'approved'`,
  - `kuppi_requests` where `status = 'open'`,
  - `kuppi_scheduled_sessions` where `status = 'scheduled'`.
- Scoping:
  - non-admin users: own batch only,
  - admin: explicit `batch_id` context required.
- Inline interactions from central feed must reuse existing endpoints only:
  - community like/save,
  - kuppi vote.
- `return_to` allow-lists for community/kuppi must continue to allow `/dashboard/feed`.

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
- Central feed:
  - `GET /dashboard/feed`
- Quizzes:
  - `GET /dashboard/quizzes`
  - `GET /dashboard/subjects/{id}/quizzes`
  - `GET /dashboard/subjects/{id}/quizzes/create`
  - `POST /dashboard/subjects/{id}/quizzes`
  - `GET /dashboard/subjects/{id}/quizzes/leaderboard`
  - `GET /dashboard/subjects/{id}/quizzes/{quizId}`
  - `POST /dashboard/subjects/{id}/quizzes/{quizId}/attempts/start`
  - `GET /dashboard/subjects/{id}/quizzes/{quizId}/attempts/{attemptId}`
  - `POST /dashboard/subjects/{id}/quizzes/{quizId}/attempts/{attemptId}/questions/{questionId}/check`
  - `POST /dashboard/subjects/{id}/quizzes/{quizId}/attempts/{attemptId}/submit`
  - `GET /dashboard/subjects/{id}/quizzes/{quizId}/attempts/{attemptId}/result`
  - `GET /my-quizzes`
  - `GET /my-quizzes/{id}/edit`
  - `POST /my-quizzes/{id}`
  - `POST /my-quizzes/{id}/submit`
  - `POST /my-quizzes/{id}/delete`
  - `GET /dashboard/quiz-requests`
  - `POST /dashboard/quiz-requests/{id}/approve`
  - `POST /dashboard/quiz-requests/{id}/reject`
  - `GET /my-quiz-analytics`
  - `GET /dashboard/quiz-analytics`
  - `POST /dashboard/subjects/{id}/quizzes/{quizId}/comments`
  - `POST /dashboard/subjects/{id}/quizzes/{quizId}/comments/{commentId}`
  - `POST /dashboard/subjects/{id}/quizzes/{quizId}/comments/{commentId}/delete`
  - `POST /dashboard/subjects/{id}/quizzes/{quizId}/questions/{questionId}/comments`
  - `POST /dashboard/subjects/{id}/quizzes/{quizId}/questions/{questionId}/comments/{commentId}`
  - `POST /dashboard/subjects/{id}/quizzes/{quizId}/questions/{questionId}/comments/{commentId}/delete`
- GPA utility:
  - `GET /dashboard/gpa`
  - `POST /dashboard/gpa`
  - `GET /dashboard/gpa/analytics`
  - `GET /dashboard/gpa/grade-scale`
  - `POST /dashboard/gpa/grade-scale`
  - `POST /dashboard/gpa/grade-scale/{id}`
  - `POST /dashboard/gpa/grade-scale/{id}/delete`
- Profile utility:
  - `GET /dashboard/profile`
  - `POST /dashboard/profile`
  - `POST /dashboard/profile/password`
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
- Requested Kuppi:
  - `GET /dashboard/kuppi`
  - `GET /dashboard/kuppi/create`
  - `POST /dashboard/kuppi`
  - `GET /dashboard/kuppi/{id}`
  - `GET /dashboard/kuppi/{id}/edit`
  - `POST /dashboard/kuppi/{id}`
  - `POST /dashboard/kuppi/{id}/delete`
  - `POST /dashboard/kuppi/{id}/vote`
  - `GET /dashboard/kuppi/{id}/conductors/apply`
  - `POST /dashboard/kuppi/{id}/conductors/apply`
  - `POST /dashboard/kuppi/{id}/conductors/{applicationId}/vote`
  - `POST /dashboard/kuppi/{id}/comments`
  - `POST /dashboard/kuppi/{id}/comments/{commentId}`
  - `POST /dashboard/kuppi/{id}/comments/{commentId}/delete`
  - `GET /my-kuppi-requests`
  - `GET /dashboard/kuppi/schedule`
  - `GET /dashboard/kuppi/schedule/manual`
  - `POST /dashboard/kuppi/schedule/select-request`
  - `GET /dashboard/kuppi/schedule/assign`
  - `POST /dashboard/kuppi/schedule/assign`
  - `GET /dashboard/kuppi/schedule/set`
  - `POST /dashboard/kuppi/schedule/set`
  - `GET /dashboard/kuppi/schedule/review`
  - `POST /dashboard/kuppi/schedule/confirm`
  - `GET /dashboard/kuppi/schedule/success`
  - `GET /dashboard/kuppi/scheduled`
  - `GET /dashboard/kuppi/scheduled/{id}`
  - `GET /dashboard/kuppi/scheduled/{id}/edit`
  - `POST /dashboard/kuppi/scheduled/{id}`
  - `POST /dashboard/kuppi/scheduled/{id}/cancel`
  - `POST /dashboard/kuppi/scheduled/{id}/delete`

## 8) Auth and Password Reset Rules

- Forgot/reset password flow is email-token based.
- SMTP config uses:
  - `GMAIL_USERNAME`
  - `GMAIL_APP_PASSWORD`
  - `EMAIL_NOTIFICATIONS_ENABLED` (`true|false`, global email on/off switch)
  - `SMTP_TIMEOUT_SECONDS` (optional; socket timeout override)
- `GMAIL_APP_PASSWORD` must not contain unquoted spaces in `.env`.
  - Prefer: no spaces (recommended) or wrap value in quotes.
- If `EMAIL_NOTIFICATIONS_ENABLED=false`, app behavior must continue without failing user flows.

## 9) UI/UX Rules for This Repo

- Use existing style system in `public/assets/css/style.css` and `public/assets/css/dashboard-modern.css`.
- Keep auth/dashboard views server-rendered, consistent with current aesthetic.
- Avoid introducing a new design system per page.
- Keep forms and spacing clean, readable, and consistent with existing components.
- For Kuppi vote controls, active states must be clearly distinguishable from inactive and disabled states on both list and detail views.

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
- Do not weaken quiz mode gating, timer enforcement, or attempt scope checks.
- Do not expose leaderboard/analytics rows outside subject and role scope rules.

## 12) Definition of Done

A change is complete when:

1. business rules still hold,
2. route -> handler mapping is valid,
3. function checker passes,
4. syntax checks pass,
5. UI remains consistent with current product style.
