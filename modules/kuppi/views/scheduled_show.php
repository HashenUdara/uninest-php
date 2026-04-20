<?php
$session = (array) ($session ?? []);
$hosts = (array) ($hosts ?? []);
$canManage = !empty($can_manage);
$availabilityOptions = (array) ($availability_options ?? []);
$sessionId = (int) ($session['id'] ?? 0);
$status = (string) ($session['status'] ?? 'scheduled');

$statusClassMap = [
    'scheduled' => 'badge-info',
    'completed' => 'badge-success',
    'cancelled' => 'badge-danger',
];
$statusClass = $statusClassMap[$status] ?? 'badge-info';

$locationType = (string) ($session['location_type'] ?? 'physical');
$locationValue = $locationType === 'online'
    ? (string) ($session['meeting_link'] ?? '')
    : (string) ($session['location_text'] ?? '');
$meetingJoinUrl = '';
if ($locationType === 'online') {
    $meetingLink = trim((string) ($session['meeting_link'] ?? ''));
    if ($meetingLink !== '') {
        $meetingJoinUrl = preg_match('#^https?://#i', $meetingLink) === 1
            ? $meetingLink
            : 'https://' . ltrim($meetingLink, '/');
    }
}
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Dashboard / Kuppi Sessions / Scheduled Session Details</p>
        <h1><?= e((string) ($session['title'] ?? 'Scheduled Session')) ?></h1>
        <p class="page-subtitle">Review session timing, hosts, and linked request details.</p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/kuppi/scheduled" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Scheduled Sessions</a>
        <?php if ($canManage && $status !== 'cancelled'): ?>
            <a href="/dashboard/kuppi/scheduled/<?= $sessionId ?>/edit" class="btn btn-primary">Edit Session</a>
        <?php endif; ?>
    </div>
</div>

<section class="kuppi-detail-layout">
    <main class="kuppi-detail-main">
        <article class="kuppi-request-card kuppi-request-card--single">
            <div class="kuppi-request-main">
                <header class="kuppi-request-header">
                    <div class="kuppi-request-badges">
                        <?php if (!empty($session['subject_code'])): ?>
                            <span class="badge"><?= e((string) $session['subject_code']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($session['batch_code'])): ?>
                            <span class="badge"><?= e((string) $session['batch_code']) ?></span>
                        <?php endif; ?>
                        <span class="badge <?= e($statusClass) ?>"><?= e(ucfirst($status)) ?></span>
                    </div>
                    <h2 class="kuppi-request-title kuppi-request-title--fixed"><?= e((string) ($session['title'] ?? 'Scheduled Session')) ?></h2>
                    <p class="kuppi-meta-row">
                        <?= e((string) ($session['session_date'] ?? '')) ?>
                        <span class="kuppi-meta-dot">•</span>
                        <?= e(substr((string) ($session['start_time'] ?? ''), 0, 5)) ?> - <?= e(substr((string) ($session['end_time'] ?? ''), 0, 5)) ?>
                        <span class="kuppi-meta-dot">•</span>
                        <?= (int) ($session['duration_minutes'] ?? 0) ?> minutes
                    </p>
                </header>

                <p class="kuppi-request-description"><?= nl2br(e((string) ($session['description'] ?? ''))) ?></p>

                <footer class="kuppi-request-footer">
                    <div class="kuppi-vote-stats">
                        <span><strong>Max attendees:</strong> <?= (int) ($session['max_attendees'] ?? 0) ?></span>
                        <span><strong>Location type:</strong> <?= e(ucfirst($locationType)) ?></span>
                        <?php if ((int) ($session['request_id'] ?? 0) > 0): ?>
                            <span><strong>Linked request:</strong> #<?= (int) ($session['request_id'] ?? 0) ?></span>
                        <?php endif; ?>
                    </div>
                </footer>
            </div>
        </article>

        <section class="kuppi-section-card">
            <h2>Assigned Hosts</h2>

            <?php if (empty($hosts)): ?>
                <p class="text-muted">No hosts assigned.</p>
            <?php else: ?>
                <div class="kuppi-conductor-list">
                    <?php foreach ($hosts as $host): ?>
                        <?php
                        $hostName = trim((string) ($host['host_name'] ?? 'Unknown User'));
                        if ($hostName === '') {
                            $hostName = 'Unknown User';
                        }
                        $toneClass = ui_avatar_tone_class((string) (((int) ($host['host_user_id'] ?? 0)) . '-' . $hostName));
                        $availability = kuppi_conductor_availability_from_csv((string) ($host['availability_csv'] ?? ''));
                        ?>
                        <article class="kuppi-conductor-card">
                            <aside class="kuppi-conductor-vote">
                                <strong class="kuppi-vote-score"><?= (int) ($host['conductor_vote_count'] ?? 0) ?></strong>
                                <small class="text-muted">votes</small>
                            </aside>
                            <div class="kuppi-conductor-body">
                                <header class="kuppi-conductor-title-row">
                                    <div class="kuppi-conductor-identity">
                                        <span class="kuppi-conductor-avatar <?= e($toneClass) ?>"><?= e(ui_initials($hostName)) ?></span>
                                        <div class="kuppi-conductor-identity-text">
                                            <h3><?= e($hostName) ?></h3>
                                            <p class="kuppi-request-meta">
                                                <?= e(ucfirst((string) ($host['host_role'] ?? 'student'))) ?>
                                                <?php if ((int) ($host['host_academic_year'] ?? 0) > 0): ?>
                                                    • Year <?= (int) ($host['host_academic_year'] ?? 0) ?>
                                                <?php endif; ?>
                                                • <?= e(str_replace('_', ' ', (string) ($host['source_type'] ?? 'manual'))) ?>
                                            </p>
                                        </div>
                                    </div>
                                </header>

                                <?php if (!empty($availability)): ?>
                                    <div class="kuppi-tags">
                                        <?php foreach ($availability as $slot): ?>
                                            <span class="badge"><?= e((string) ($availabilityOptions[$slot] ?? $slot)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <aside class="kuppi-detail-side">
        <article class="kuppi-side-card">
            <h3>Session Details</h3>
            <ul class="kuppi-stat-list">
                <li><span>Status</span><strong><?= e(ucfirst($status)) ?></strong></li>
                <li><span>Date</span><strong><?= e((string) ($session['session_date'] ?? '')) ?></strong></li>
                <li><span>Time</span><strong><?= e(substr((string) ($session['start_time'] ?? ''), 0, 5)) ?> - <?= e(substr((string) ($session['end_time'] ?? ''), 0, 5)) ?></strong></li>
                <li><span>Location</span><strong><?= e($locationValue !== '' ? $locationValue : 'TBD') ?></strong></li>
            </ul>
            <?php if ($meetingJoinUrl !== ''): ?>
                <a href="<?= e($meetingJoinUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="width:100%;margin-top:12px;">
                    <?= ui_lucide_icon('external-link') ?> Join Online Session
                </a>
            <?php endif; ?>
        </article>

        <?php if (trim((string) ($session['notes'] ?? '')) !== ''): ?>
            <article class="kuppi-side-card">
                <h3>Notes</h3>
                <p class="text-muted"><?= nl2br(e((string) ($session['notes'] ?? ''))) ?></p>
            </article>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <article class="kuppi-side-card">
                <h3>Manage</h3>
                <div class="kuppi-side-actions">
                    <?php if ($status !== 'cancelled'): ?>
                        <a href="/dashboard/kuppi/scheduled/<?= $sessionId ?>/edit" class="btn btn-outline">Edit Session</a>
                    <?php endif; ?>

                    <?php if ($status === 'scheduled'): ?>
                        <form method="POST" action="/dashboard/kuppi/scheduled/<?= $sessionId ?>/cancel" onsubmit="return confirm('Cancel this scheduled session?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline">Cancel Session</button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" action="/dashboard/kuppi/scheduled/<?= $sessionId ?>/delete" onsubmit="return confirm('Delete this scheduled session? This cannot be undone.');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline">Delete Session</button>
                    </form>
                </div>
            </article>
        <?php endif; ?>
    </aside>
</section>
