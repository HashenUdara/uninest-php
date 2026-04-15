<section class="dash-hero">
    <p class="dash-eyebrow">Coordinator Workspace</p>
    <h1>Manage the subjects assigned to you.</h1>
    <p class="dash-copy">Welcome back, <?= e($user['name']) ?>. You can coordinate your assigned subjects while keeping normal student access.</p>
    <div class="dash-action-row">
        <a href="/coordinator/subjects" class="btn btn-primary">Manage Assigned Subjects</a>
        <a href="/dashboard/feed" class="btn btn-outline">Central Feed</a>
        <a href="/dashboard/quizzes" class="btn btn-outline">Quiz Hub</a>
        <a href="/dashboard/gpa" class="btn btn-outline">GPA Calculator</a>
        <a href="/dashboard/gpa/analytics" class="btn btn-outline">GPA Analytics</a>
        <a href="/dashboard/kuppi" class="btn btn-outline">Requested Kuppi</a>
        <a href="/dashboard/community" class="btn btn-outline">Community Feed</a>
        <a href="/my-posts" class="btn btn-outline">My Posts</a>
        <a href="/my-quizzes" class="btn btn-outline">My Quizzes</a>
        <a href="/coordinator/resource-requests" class="btn btn-outline">Review Resource Requests</a>
        <a href="/dashboard/quiz-requests" class="btn btn-outline">Review Quiz Requests</a>
        <a href="/my-resources" class="btn btn-outline">My Resources</a>
        <a href="/dashboard/subjects" class="btn btn-outline">Browse Batch Subjects</a>
    </div>
</section>

<section class="dash-kpi-grid">
    <article class="kpi-card">
        <span class="kpi-label">Assigned Subjects</span>
        <strong><?= count($subjects) ?></strong>
        <p>Subjects where you currently hold coordinator responsibility.</p>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Pending Resource Requests</span>
        <strong><?= (int) $pending_resource_requests ?></strong>
        <p>Student submissions waiting for your approval.</p>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Pending Quiz Requests</span>
        <strong><?= (int) ($pending_quiz_requests ?? 0) ?></strong>
        <p>Quizzes in your assigned subjects awaiting review.</p>
    </article>
</section>

<section class="dash-panel">
    <header class="dash-panel-header">
        <h2>Coordinator Notes</h2>
    </header>
    <p class="text-muted">You can edit only assigned subjects from the coordinator management page. Subject creation, deletion, and coordinator assignment remain moderator/admin responsibilities.</p>
</section>

<section class="dash-panel">
    <header class="dash-panel-header">
        <h2>Resource Approvals</h2>
        <a href="/coordinator/resource-requests" class="btn btn-sm btn-outline">Open Queue</a>
    </header>
    <p class="text-muted">Review pending student resource uploads and update requests for subjects assigned to you.</p>
</section>

<section class="dash-panel">
    <header class="dash-panel-header">
        <h2>Quiz Approvals</h2>
        <a href="/dashboard/quiz-requests" class="btn btn-sm btn-outline">Open Queue</a>
    </header>
    <p class="text-muted">Review pending student-created quizzes for your assigned subjects and publish approved ones.</p>
</section>

<section class="dash-panel">
    <header class="dash-panel-header">
        <h2>My Assigned Subjects</h2>
        <a href="/coordinator/subjects" class="btn btn-sm btn-outline">Open Manager</a>
    </header>
    <?php if (empty($subjects)): ?>
        <p class="text-muted">No subjects are assigned to you yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Batch</th>
                    <th>Code</th>
                    <th>Subject</th>
                    <th>Year</th>
                    <th>Sem</th>
                    <th>Status</th>
                    <th>Credits</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $subject): ?>
                    <?php
                    $avatarText = ui_initials((string) $subject['name']);
                    $avatarTone = ui_avatar_tone_class((string) (($subject['code'] ?? '') . '-' . ($subject['name'] ?? '')));
                    $status = (string) ($subject['status'] ?? 'upcoming');
                    $statusClass = match ($status) {
                        'in_progress' => 'badge-info',
                        'completed' => 'badge-warning',
                        default => '',
                    };
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($subject['batch_name'] ?? '-') ?></strong><br>
                            <small class="text-muted"><?= e($subject['batch_code'] ?? '') ?></small>
                        </td>
                        <td><span class="badge"><?= e($subject['code']) ?></span></td>
                        <td>
                            <div class="table-identity">
                                <span class="table-avatar <?= e($avatarTone) ?>"><?= e($avatarText) ?></span>
                                <div class="table-identity-text">
                                    <strong><?= e($subject['name']) ?></strong>
                                </div>
                            </div>
                        </td>
                        <td><?= (int) ($subject['academic_year'] ?? 1) ?></td>
                        <td><?= (int) ($subject['semester'] ?? 1) ?></td>
                        <td><span class="badge <?= e($statusClass) ?>"><?= e(subjects_status_label($status)) ?></span></td>
                        <td><?= (int) $subject['credits'] ?></td>
                        <td class="actions">
                            <a href="/subjects/<?= (int) $subject['id'] ?>/topics" class="table-icon-btn" title="Manage topics" aria-label="Manage topics">
                                <?= ui_lucide_icon('layers') ?>
                            </a>
                            <a href="/coordinator/subjects/<?= (int) $subject['id'] ?>/edit" class="table-icon-btn" title="Edit subject" aria-label="Edit subject">
                                <?= ui_lucide_icon('pencil') ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
