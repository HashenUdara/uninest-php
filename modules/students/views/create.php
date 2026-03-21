<div class="page-header">
    <h1>Create Student</h1>
    <a href="/students" class="btn btn-outline">← Back to Students</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/students">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?= old('name') ?>" required maxlength="100">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= old('email') ?>" required maxlength="150">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="6">
            </div>

            <div class="form-group">
                <label for="academic_year">Academic Year</label>
                <?php $selectedYear = old('academic_year', '1'); ?>
                <select id="academic_year" name="academic_year" required>
                    <?php for ($year = 1; $year <= 8; $year++): ?>
                        <option value="<?= $year ?>" <?= $selectedYear === (string) $year ? 'selected' : '' ?>>Year <?= $year ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="university_id">University</label>
                <?php $selectedUniversity = old('university_id'); ?>
                <select id="university_id" name="university_id" required>
                    <option value="">Select university</option>
                    <?php foreach ($universities as $university): ?>
                        <option value="<?= (int) $university['id'] ?>" <?= $selectedUniversity === (string) $university['id'] ? 'selected' : '' ?>>
                            <?= e($university['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="batch_id">Approved Batch</label>
                <?php $selectedBatch = old('batch_id'); ?>
                <select id="batch_id" name="batch_id" required>
                    <option value="">Select batch</option>
                    <?php foreach ($batches as $batch): ?>
                        <option value="<?= (int) $batch['id'] ?>" <?= $selectedBatch === (string) $batch['id'] ? 'selected' : '' ?>>
                            <?= e($batch['name']) ?> (<?= e($batch['batch_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Student</button>
                <a href="/students" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
