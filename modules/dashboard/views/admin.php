<section class="dash-hero">
    <p class="dash-eyebrow">Administration Console</p>
    <h1>Platform operations for <?= e(config('app.name')) ?>.</h1>
    <p class="dash-copy">Welcome back, <?= e($user['name']) ?>. Monitor onboarding approvals and keep academic content quality high across all batches.</p>
    <div class="dash-action-row">
        <a href="/admin/batch-requests" class="btn btn-primary">Review Batch Requests</a>
        <a href="/dashboard/feed" class="btn btn-outline">Central Feed</a>
        <a href="/dashboard/quizzes" class="btn btn-outline">Quiz Hub</a>
        <a href="/admin/student-requests" class="btn btn-outline">Review Student Requests</a>
        <a href="/dashboard/quiz-requests" class="btn btn-outline">Review Quiz Requests</a>
        <a href="/dashboard/gpa/grade-scale" class="btn btn-outline">Grade Point Config</a>
        <a href="/dashboard/community" class="btn btn-outline">Community Feed</a>
        <a href="/my-resources" class="btn btn-outline">My Resources</a>
        <a href="/admin/moderators" class="btn btn-outline">Manage Moderators</a>
        <a href="/admin/batches" class="btn btn-outline">Manage Batches</a>
        <a href="/students" class="btn btn-outline">Manage Students</a>
        <a href="/subjects" class="btn btn-outline">Manage Subjects</a>
    </div>
</section>

<section class="dash-kpi-grid">
    <article class="kpi-card">
        <span class="kpi-label">Total Users</span>
        <strong><?= (int) $user_count ?></strong>
        <p>Registered accounts in platform.</p>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Total Subjects</span>
        <strong><?= (int) $subject_count ?></strong>
        <p>Active learning subjects.</p>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Pending Batch Requests</span>
        <strong><?= (int) $pending_batch_requests ?></strong>
        <p>Moderator batch approvals awaiting action.</p>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Pending Student Requests</span>
        <strong><?= (int) $pending_student_requests ?></strong>
        <p>Student join requests requiring review.</p>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Pending Quiz Requests</span>
        <strong><?= (int) ($pending_quiz_requests ?? 0) ?></strong>
        <p>Quiz submissions awaiting approval.</p>
    </article>
</section>

<section class="dash-grid-2">
    <article class="dash-panel">
        <header class="dash-panel-header">
            <h2>Approval Queue</h2>
        </header>
        <ul class="dash-list">
            <li>
                <div>
                    <strong>Batch Creation Requests</strong>
                    <p>Moderator onboarding approvals.</p>
                </div>
                <span class="badge badge-warning"><?= (int) $pending_batch_requests ?> pending</span>
            </li>
            <li>
                <div>
                    <strong>Student Join Requests</strong>
                    <p>Cross-batch membership validation.</p>
                </div>
                <span class="badge badge-warning"><?= (int) $pending_student_requests ?> pending</span>
            </li>
            <li>
                <div>
                    <strong>Quiz Publication Requests</strong>
                    <p>Subject-scoped quiz approvals from students.</p>
                </div>
                <span class="badge badge-warning"><?= (int) ($pending_quiz_requests ?? 0) ?> pending</span>
            </li>
        </ul>
    </article>

    <article class="dash-panel">
        <header class="dash-panel-header">
            <h2>Admin Actions</h2>
        </header>
        <div class="dash-action-grid">
            <a href="/admin/batch-requests" class="btn btn-primary">Open Batch Queue</a>
            <a href="/admin/student-requests" class="btn btn-outline">Open Student Queue</a>
            <a href="/dashboard/quiz-requests" class="btn btn-outline">Open Quiz Queue</a>
            <a href="/students/create" class="btn btn-outline">Add New Student</a>
            <a href="/students" class="btn btn-outline">View Students</a>
            <a href="/subjects/create" class="btn btn-outline">Add New Subject</a>
            <a href="/subjects" class="btn btn-outline">View Subject Catalog</a>
            <a href="/admin/moderators" class="btn btn-outline">Open Moderator Manager</a>
            <a href="/admin/batches" class="btn btn-outline">Open Batch Manager</a>
        </div>
    </article>
</section>
