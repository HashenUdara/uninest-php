<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Dashboard / Requested Kuppi Sessions</p>
        <h1>Edit Kuppi Request</h1>
        <p class="page-subtitle">
            Update your open request while demand is still being gathered.
        </p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/kuppi/<?= (int) $request['id'] ?>" class="btn btn-outline">Open Request</a>
        <a href="<?= e((string) $back_list_url) ?>" class="btn btn-outline">← Back to Requests</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/dashboard/kuppi/<?= (int) $request['id'] ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="subject_id">Subject</label>
                <?php $selectedSubject = (int) old('subject_id', (string) ((int) ($request['subject_id'] ?? 0))); ?>
                <select id="subject_id" name="subject_id" required>
                    <option value="">Select subject</option>
                    <?php foreach ((array) $subject_options as $subject): ?>
                        <?php $subjectId = (int) ($subject['id'] ?? 0); ?>
                        <option value="<?= $subjectId ?>" <?= $selectedSubject === $subjectId ? 'selected' : '' ?>>
                            <?= e((string) ($subject['code'] ?? 'SUB')) ?> — <?= e((string) ($subject['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="title">Session Title</label>
                <input type="text" id="title" name="title" value="<?= old('title', (string) ($request['title'] ?? '')) ?>" required maxlength="200">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" maxlength="2000" required><?= old('description', (string) ($request['description'] ?? '')) ?></textarea>
            </div>

            <div class="form-group">
                <label for="tags_csv">Tags (comma-separated)</label>
                <input type="text" id="tags_csv" name="tags_csv" value="<?= old('tags_csv', (string) ($request['tags_csv'] ?? '')) ?>" maxlength="300">
                <small class="text-muted">Up to 8 tags. Tags are normalized to lowercase and hyphen style.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/dashboard/kuppi/<?= (int) $request['id'] ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
