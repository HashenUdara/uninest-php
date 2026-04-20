<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= $is_admin ? 'Admin / Subjects' : 'Moderator / Subjects' ?></p>
        <h1>Edit Subject</h1>
        <p class="page-subtitle">Update subject details while keeping batch alignment and code quality clear.</p>
    </div>
    <div class="page-header-actions">
        <a href="/subjects/<?= (int) $subject['id'] ?>/topics" class="btn btn-outline">Manage Topics</a>
        <a href="/subjects/<?= (int) $subject['id'] ?>/coordinators" class="btn btn-outline">Manage Coordinators</a>
        <a href="/subjects" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Subjects</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/subjects/<?= $subject['id'] ?>">
            <?= csrf_field() ?>

            <?php if ($is_admin): ?>
                <div class="form-group">
                    <label for="batch_id">Batch</label>
                    <?php $selectedBatch = old('batch_id', (string) $subject['batch_id']); ?>
                    <select id="batch_id" name="batch_id" required>
                        <option value="">Select batch</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?= (int) $batch['id'] ?>" <?= $selectedBatch === (string) $batch['id'] ? 'selected' : '' ?>>
                                <?= e($batch['name']) ?> (<?= e($batch['batch_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="code">Subject Code</label>
                <input type="text" id="code" name="code" value="<?= old('code', $subject['code']) ?>" placeholder="e.g. CS101" required maxlength="20">
            </div>

            <div class="form-group">
                <label for="name">Subject Name</label>
                <input type="text" id="name" name="name" value="<?= old('name', $subject['name']) ?>" placeholder="e.g. Introduction to Computer Science" required maxlength="200">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" placeholder="Brief description of the subject..."><?= old('description', $subject['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="credits">Credits</label>
                <input type="number" id="credits" name="credits" value="<?= old('credits', (string) $subject['credits']) ?>" min="1" max="10" required>
            </div>

            <div class="form-group">
                <label for="academic_year">Academic Year</label>
                <?php $selectedAcademicYear = old('academic_year', (string) ($subject['academic_year'] ?? 1)); ?>
                <select id="academic_year" name="academic_year" required>
                    <?php for ($year = 1; $year <= 4; $year++): ?>
                        <option value="<?= $year ?>" <?= $selectedAcademicYear === (string) $year ? 'selected' : '' ?>>
                            Year <?= $year ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="semester">Semester</label>
                <?php $selectedSemester = old('semester', (string) ($subject['semester'] ?? 1)); ?>
                <select id="semester" name="semester" required>
                    <option value="1" <?= $selectedSemester === '1' ? 'selected' : '' ?>>Semester 1</option>
                    <option value="2" <?= $selectedSemester === '2' ? 'selected' : '' ?>>Semester 2</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Subject</button>
                <a href="/subjects" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
