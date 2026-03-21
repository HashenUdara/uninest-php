<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($warning = get_flash('warning')): ?>
    <div class="alert alert-warning"><?= e($warning) ?></div>
<?php endif; ?>
<?php if ($success = get_flash('success')): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>Onboarding Status</h1>
    <?php if (!onboarding_complete_for_user($user)): ?>
        <span class="badge badge-warning">Action Required</span>
    <?php endif; ?>
</div>

<?php if ($role === 'moderator'): ?>
    <?php if (!$batch): ?>
        <div class="card">
            <div class="card-body">
                <p class="text-muted">No batch request found. Contact admin support.</p>
            </div>
        </div>
    <?php elseif ($batch['status'] === 'pending'): ?>
        <div class="card">
            <div class="card-header">
                <h2>Batch Approval Pending</h2>
            </div>
            <div class="card-body">
                <p>Your batch request is awaiting admin review.</p>
                <p class="text-muted">
                    <strong>Batch:</strong> <?= e($batch['name']) ?><br>
                    <strong>Program:</strong> <?= e($batch['program']) ?><br>
                    <strong>Intake Year:</strong> <?= (int) $batch['intake_year'] ?>
                </p>
            </div>
        </div>
    <?php elseif ($batch['status'] === 'approved'): ?>
        <div class="card">
            <div class="card-header">
                <h2>Batch Approved</h2>
            </div>
            <div class="card-body">
                <p>Your batch is approved. You can now access moderator features.</p>
                <p class="text-muted">
                    <strong>Batch ID:</strong> <span class="badge"><?= e($batch['batch_code']) ?></span><br>
                    <strong>Batch:</strong> <?= e($batch['name']) ?><br>
                    <strong>Program:</strong> <?= e($batch['program']) ?>
                </p>
                <a href="/dashboard" class="btn btn-primary">Go to Dashboard</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2>Batch Request Rejected</h2>
            </div>
            <div class="card-body">
                <p class="text-muted">Update your details and resubmit the request.</p>
                <?php if (!empty($batch['rejection_reason'])): ?>
                    <div class="alert alert-warning"><?= e($batch['rejection_reason']) ?></div>
                <?php endif; ?>

                <form method="POST" action="/onboarding/moderator/resubmit">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="batch_name">Batch Name</label>
                        <input type="text" id="batch_name" name="batch_name" value="<?= old('batch_name', $batch['name']) ?>" required maxlength="150">
                    </div>

                    <div class="form-group">
                        <label for="program">Program</label>
                        <input type="text" id="program" name="program" value="<?= old('program', $batch['program']) ?>" required maxlength="150">
                    </div>

                    <div class="form-group">
                        <label for="intake_year">Intake Year</label>
                        <input type="number" id="intake_year" name="intake_year" value="<?= old('intake_year', (string) $batch['intake_year']) ?>" min="2000" max="2100" required>
                    </div>

                    <div class="form-group">
                        <label for="university_id">University</label>
                        <select id="university_id" name="university_id" required>
                            <option value="">Select university</option>
                            <?php $selectedUniversity = old('university_id', (string) $batch['university_id']); ?>
                            <?php foreach ($universities as $uni): ?>
                                <option value="<?= (int) $uni['id'] ?>" <?= $selectedUniversity === (string) $uni['id'] ? 'selected' : '' ?>>
                                    <?= e($uni['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Resubmit Request</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php elseif ($role === 'student'): ?>
    <?php
    $lockedBatchCode = trim((string) ($locked_batch['batch_code'] ?? ''));
    $hasLockedBatch = $lockedBatchCode !== '';
    ?>
    <?php if (!$request): ?>
        <div class="card">
            <div class="card-body">
                <p class="text-muted">No join request found. Contact admin support.</p>
            </div>
        </div>
    <?php elseif ($request['status'] === 'pending'): ?>
        <div class="card">
            <div class="card-header">
                <h2>Join Request Pending</h2>
            </div>
            <div class="card-body">
                <p>Your request to join this batch is waiting for moderator approval.</p>
                <p class="text-muted">
                    <strong>Requested Batch:</strong> <?= e($request['batch_name']) ?><br>
                    <strong>Batch ID:</strong> <span class="badge"><?= e($request['batch_code']) ?></span>
                </p>
            </div>
        </div>
    <?php elseif ($request['status'] === 'approved'): ?>
        <div class="card">
            <div class="card-header">
                <h2>Join Request Approved</h2>
            </div>
            <div class="card-body">
                <p>Your request is approved. You now have access to your batch content.</p>
                <p class="text-muted">
                    <strong>Batch:</strong> <?= e($request['batch_name']) ?><br>
                    <strong>Batch ID:</strong> <span class="badge"><?= e($request['batch_code']) ?></span>
                </p>
                <a href="/dashboard" class="btn btn-primary">Go to Dashboard</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2>Join Request Rejected</h2>
            </div>
            <div class="card-body">
                <?php if ($hasLockedBatch): ?>
                    <p class="text-muted">You can only reapply to your original batch after removal.</p>
                    <p class="text-muted">
                        <strong>Allowed Batch ID:</strong>
                        <span class="badge"><?= e($lockedBatchCode) ?></span>
                    </p>
                <?php else: ?>
                    <p class="text-muted">Update your batch ID and resubmit the request.</p>
                <?php endif; ?>
                <?php if (!empty($request['rejection_reason'])): ?>
                    <div class="alert alert-warning"><?= e($request['rejection_reason']) ?></div>
                <?php endif; ?>

                <form method="POST" action="/onboarding/student/resubmit">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="batch_code">Active Batch ID</label>
                        <input type="text" id="batch_code" name="batch_code" value="<?= old('batch_code', $hasLockedBatch ? $lockedBatchCode : $request['batch_code']) ?>" placeholder="e.g. BATCH-AB12CD" required maxlength="20" <?= $hasLockedBatch ? 'readonly' : '' ?>>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Resubmit Request</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
