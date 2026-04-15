<?php
$batchCode = trim((string) ($batch['batch_code'] ?? ''));
$batchStatus = trim((string) ($batch['status'] ?? 'pending'));
$statusLabel = ucfirst($batchStatus ?: 'pending');
?>

<section class="dash-hero">
    <p class="dash-eyebrow">Moderator Workspace</p>
    <h1>Run your batch with clarity and controlled access.</h1>
    <p class="dash-copy">
        Welcome back, <?= e($user['name']) ?>.
        <?php if ($batchCode !== ''): ?>
            You are managing <span class="inline-strong"><?= e($batchCode) ?></span>.
        <?php else: ?>
            Your batch is still in onboarding review.
        <?php endif; ?>
    </p>
    <div class="dash-action-row">
        <a href="/moderator/join-requests" class="btn btn-primary">Review Join Requests</a>
        <a href="/dashboard/feed" class="btn btn-outline">Central Feed</a>
        <a href="/dashboard/quizzes" class="btn btn-outline">Quiz Hub</a>
        <a href="/dashboard/community" class="btn btn-outline">Community Feed</a>
        <a href="/my-posts" class="btn btn-outline">My Posts</a>
        <a href="/students" class="btn btn-outline">Manage Students</a>
        <a href="/dashboard/quiz-requests" class="btn btn-outline">Review Quiz Requests</a>
        <a href="/dashboard/gpa/grade-scale" class="btn btn-outline">Grade Point Config</a>
        <a href="/my-resources" class="btn btn-outline">My Resources</a>
        <a href="/subjects/create" class="btn btn-outline">Create Subject</a>
        <a href="/subjects" class="btn btn-outline">Manage Subjects</a>
    </div>
</section>

<section class="dash-kpi-grid">
    <article class="kpi-card">
        <span class="kpi-label">Subjects in Batch</span>
        <strong><?= (int) $subject_count ?></strong>
        <p>Total subjects available for your students.</p>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Pending Join Requests</span>
        <strong><?= (int) $pending_student_requests ?></strong>
        <p>Students waiting for approval.</p>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Pending Quiz Requests</span>
        <strong><?= (int) ($pending_quiz_requests ?? 0) ?></strong>
        <p>Quiz approvals waiting for moderation.</p>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Batch Status</span>
        <strong><?= e($statusLabel) ?></strong>
        <p><?= $batchCode !== '' ? 'Batch ID: ' . e($batchCode) : 'Batch ID will appear after approval.' ?></p>
    </article>
</section>

<section class="dash-grid-2">
    <article class="dash-panel">
        <header class="dash-panel-header">
            <h2>Student Invite</h2>
        </header>
        <?php if (!empty($invite_link) && !empty($invite_qr_url)): ?>
            <p class="text-muted">Share this link or QR so students can join with the correct batch ID pre-filled.</p>
            <div class="invite-grid">
                <div>
                    <p class="text-muted">
                        <strong>Batch ID:</strong>
                        <span class="badge badge-info"><?= e($batchCode) ?></span>
                    </p>
                    <div class="invite-link-box">
                        <input type="text" id="invite-link-input" value="<?= e($invite_link) ?>" readonly>
                        <button type="button" class="btn btn-sm btn-primary" id="copy-invite-btn">Copy Link</button>
                    </div>
                    <a href="<?= e($invite_link) ?>" target="_blank" rel="noopener">Open Invite URL</a>
                </div>
                <div class="invite-qr">
                    <img src="<?= e($invite_qr_url) ?>" alt="Batch invite QR code">
                </div>
            </div>
        <?php else: ?>
            <p class="text-muted">Invite link appears once your batch is approved and a batch ID is issued.</p>
        <?php endif; ?>
    </article>

    <article class="dash-panel">
        <header class="dash-panel-header">
            <h2>Recent Batch Subjects</h2>
            <a href="/subjects" class="btn btn-sm btn-outline">Manage All</a>
        </header>
        <?php if (empty($subjects)): ?>
            <p class="text-muted">No subjects yet. <a href="/subjects/create">Create your first subject</a>.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
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
                                <a href="/subjects/<?= (int) $subject['id'] ?>/coordinators" class="table-icon-btn" title="Manage coordinators" aria-label="Manage coordinators">
                                    <?= ui_lucide_icon('users') ?>
                                </a>
                                <a href="/subjects/<?= (int) $subject['id'] ?>/edit" class="table-icon-btn" title="Edit subject" aria-label="Edit subject">
                                    <?= ui_lucide_icon('pencil') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>

<script>
    (function () {
        const input = document.getElementById('invite-link-input');
        const button = document.getElementById('copy-invite-btn');
        if (!input || !button) return;

        button.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(input.value);
                button.textContent = 'Copied';
                setTimeout(() => {
                    button.textContent = 'Copy Link';
                }, 1500);
            } catch (e) {
                input.focus();
                input.select();
                document.execCommand('copy');
                button.textContent = 'Copied';
                setTimeout(() => {
                    button.textContent = 'Copy Link';
                }, 1500);
            }
        });
    })();
</script>
