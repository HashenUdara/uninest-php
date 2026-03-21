<section class="dash-hero">
    <p class="dash-eyebrow">Administration Console</p>
    <h1>Platform operations for <?= e(config('app.name')) ?>.</h1>
    <p class="dash-copy">Welcome back, <?= e($user['name']) ?>. Monitor onboarding approvals and keep academic content quality high across all batches.</p>
    <div class="dash-action-row">
        <a href="/admin/batch-requests" class="btn btn-primary">Review Batch Requests</a>
        <a href="/admin/student-requests" class="btn btn-outline">Review Student Requests</a>
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
        </ul>
    </article>

    <article class="dash-panel">
        <header class="dash-panel-header">
            <h2>Admin Actions</h2>
        </header>
        <div class="dash-action-grid">
            <a href="/admin/batch-requests" class="btn btn-primary">Open Batch Queue</a>
            <a href="/admin/student-requests" class="btn btn-outline">Open Student Queue</a>
            <a href="/subjects/create" class="btn btn-outline">Add New Subject</a>
            <a href="/subjects" class="btn btn-outline">View Subject Catalog</a>
        </div>
    </article>
</section>
