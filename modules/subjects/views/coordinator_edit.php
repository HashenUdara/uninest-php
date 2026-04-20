<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Coordinator / Subjects</p>
        <h1>Edit Assigned Subject</h1>
        <p class="page-subtitle">Update content details for your assigned subject.</p>
    </div>
    <div class="page-header-actions">
        <a href="/subjects/<?= (int) $subject['id'] ?>/topics" class="btn btn-outline">Manage Topics</a>
        <a href="/coordinator/subjects" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Assigned Subjects</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/coordinator/subjects/<?= (int) $subject['id'] ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Batch</label>
                <input type="text" value="<?= e($subject['batch_name']) ?> (<?= e($subject['batch_code']) ?>)" readonly>
            </div>

            <div class="form-group">
                <label for="code">Subject Code</label>
                <input type="text" id="code" name="code" value="<?= old('code', (string) $subject['code']) ?>" placeholder="e.g. CS101" required maxlength="20">
            </div>

            <div class="form-group">
                <label for="name">Subject Name</label>
                <input type="text" id="name" name="name" value="<?= old('name', (string) $subject['name']) ?>" placeholder="e.g. Introduction to Computer Science" required maxlength="200">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" placeholder="Brief description of the subject..."><?= old('description', (string) ($subject['description'] ?? '')) ?></textarea>
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
                <a href="/coordinator/subjects" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
