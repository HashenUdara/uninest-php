#!/usr/bin/env php
<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$options = parse_cli_options($argv);

if (isset($options['help']) || isset($options['h'])) {
    print_usage();
    exit(0);
}

$module = normalize_identifier((string) ($options['module'] ?? ''));
if ($module === '') {
    fwrite(STDERR, "Missing --module option.\n\n");
    print_usage();
    exit(1);
}

$table = normalize_identifier((string) ($options['table'] ?? $module));
if ($table === '') {
    fwrite(STDERR, "Invalid table name.\n");
    exit(1);
}

$routeBase = trim((string) ($options['route'] ?? $module), '/');
if ($routeBase === '') {
    fwrite(STDERR, "Invalid route path.\n");
    exit(1);
}

$role = normalize_identifier((string) ($options['role'] ?? 'moderator'));
if ($role === '') {
    fwrite(STDERR, "Invalid role name.\n");
    exit(1);
}

$primaryField = normalize_identifier((string) ($options['field'] ?? 'name'));
if ($primaryField === '') {
    fwrite(STDERR, "Invalid primary field name.\n");
    exit(1);
}

$pluralLabel = trim((string) ($options['label'] ?? label_from_identifier($module)));
$singularLabel = trim((string) ($options['item-label'] ?? singularize_label($pluralLabel)));

$force = isset($options['force']);
$appendRoutes = isset($options['append-routes']);

$moduleDir = $basePath . '/modules/' . $module;
$viewsDir = $moduleDir . '/views';

if (is_dir($moduleDir) && !$force) {
    fwrite(STDERR, "Module directory already exists: modules/{$module}\nUse --force to overwrite generated files.\n");
    exit(1);
}

if (!is_dir($viewsDir) && !mkdir($viewsDir, 0775, true) && !is_dir($viewsDir)) {
    fwrite(STDERR, "Failed to create directory: modules/{$module}/views\n");
    exit(1);
}

$renderData = [
    '{{MODULE}}' => $module,
    '{{TABLE}}' => $table,
    '{{PREFIX}}' => $module,
    '{{ROUTE}}' => $routeBase,
    '{{ROLE}}' => $role,
    '{{PRIMARY_FIELD}}' => $primaryField,
    '{{PRIMARY_LABEL}}' => label_from_identifier($primaryField),
    '{{PLURAL_LABEL}}' => $pluralLabel,
    '{{SINGULAR_LABEL}}' => $singularLabel,
];

$files = [
    $moduleDir . '/models.php' => render_template(models_template(), $renderData),
    $moduleDir . '/controllers.php' => render_template(controllers_template(), $renderData),
    $viewsDir . '/index.php' => render_template(index_view_template(), $renderData),
    $viewsDir . '/create.php' => render_template(create_view_template(), $renderData),
    $viewsDir . '/edit.php' => render_template(edit_view_template(), $renderData),
];

$writtenFiles = [];
foreach ($files as $filePath => $content) {
    if (file_exists($filePath) && !$force) {
        fwrite(STDERR, "File already exists: " . relative_path($filePath, $basePath) . "\nUse --force to overwrite.\n");
        exit(1);
    }

    if (file_put_contents($filePath, $content) === false) {
        fwrite(STDERR, "Failed to write file: " . relative_path($filePath, $basePath) . "\n");
        exit(1);
    }

    $writtenFiles[] = relative_path($filePath, $basePath);
}

$routeSnippet = render_template(routes_template(), $renderData);
$routesPath = $basePath . '/routes.php';

if ($appendRoutes) {
    if (!file_exists($routesPath)) {
        fwrite(STDERR, "Cannot append routes. Missing routes.php\n");
        exit(1);
    }

    $routesContents = file_get_contents($routesPath);
    if ($routesContents === false) {
        fwrite(STDERR, "Failed to read routes.php\n");
        exit(1);
    }

    if (str_contains($routesContents, "route('GET', '/{$routeBase}'")) {
        fwrite(STDERR, "Route path /{$routeBase} already appears in routes.php. Skipping append.\n");
        exit(1);
    }

    $routesContents = rtrim($routesContents) . "\n\n" . $routeSnippet . "\n";
    if (file_put_contents($routesPath, $routesContents) === false) {
        fwrite(STDERR, "Failed to update routes.php\n");
        exit(1);
    }

    $writtenFiles[] = 'routes.php';
}

fwrite(STDOUT, "CRUD module scaffolded successfully.\n");
foreach ($writtenFiles as $file) {
    fwrite(STDOUT, " - {$file}\n");
}

if (!$appendRoutes) {
    fwrite(STDOUT, "\nAdd this route block to routes.php:\n\n");
    fwrite(STDOUT, $routeSnippet . "\n");
}

fwrite(STDOUT, "\nNext steps:\n");
fwrite(STDOUT, " 1) Adjust field mapping in modules/{$module}/models.php if your table columns differ.\n");
fwrite(STDOUT, " 2) Run: php scripts/check_functions.php\n");

/**
 * @return array<string, string|bool>
 */
function parse_cli_options(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }

        $arg = substr($arg, 2);
        if ($arg === '') {
            continue;
        }

        if (str_contains($arg, '=')) {
            [$key, $value] = explode('=', $arg, 2);
            $options[$key] = $value;
            continue;
        }

        $options[$arg] = true;
    }

    return $options;
}

function normalize_identifier(string $value): string
{
    $value = strtolower(trim($value));
    $value = str_replace('-', '_', $value);
    return preg_match('/^[a-z][a-z0-9_]*$/', $value) === 1 ? $value : '';
}

function label_from_identifier(string $value): string
{
    $value = str_replace('_', ' ', trim($value));
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return ucwords($value);
}

function singularize_label(string $label): string
{
    $trimmed = trim($label);
    if ($trimmed === '') {
        return '';
    }

    $lastWord = strrchr($trimmed, ' ');
    if ($lastWord === false) {
        return singularize_word($trimmed);
    }

    $prefix = substr($trimmed, 0, -strlen($lastWord));
    $word = ltrim($lastWord);
    return trim($prefix . ' ' . singularize_word($word));
}

function singularize_word(string $word): string
{
    if (strlen($word) > 3 && str_ends_with(strtolower($word), 'ies')) {
        return substr($word, 0, -3) . 'y';
    }
    if (strlen($word) > 1 && str_ends_with(strtolower($word), 's')) {
        return substr($word, 0, -1);
    }
    return $word;
}

function render_template(string $template, array $data): string
{
    return strtr($template, $data);
}

function relative_path(string $filePath, string $basePath): string
{
    $normalizedFile = str_replace('\\', '/', $filePath);
    $normalizedBase = rtrim(str_replace('\\', '/', $basePath), '/');
    return ltrim(str_replace($normalizedBase, '', $normalizedFile), '/');
}

function print_usage(): void
{
    $usage = <<<TXT
Usage:
  php scripts/make_crud.php --module=announcements [options]

Required:
  --module=NAME            Module slug (used for folder and function prefix)

Optional:
  --table=NAME             Database table name (default: module)
  --route=PATH             Route base path without leading slash (default: module)
  --role=ROLE              Route role middleware target (default: moderator)
  --field=FIELD            Primary text field used in forms (default: name)
  --label="Plural Label"   Human-readable module label (default from module)
  --item-label="Singular"  Human-readable single-item label
  --append-routes          Append generated route block to routes.php
  --force                  Overwrite generated files if they already exist
  --help                   Show this message

Example:
  php scripts/make_crud.php --module=announcements --table=announcements --field=title --append-routes
TXT;

    fwrite(STDOUT, $usage . "\n");
}

function models_template(): string
{
    return <<<'PHP'
<?php

/**
 * {{PLURAL_LABEL}} Module — Models
 *
 * Generated by scripts/make_crud.php
 * Adjust field mapping if your table uses different columns.
 */

function {{PREFIX}}_all(): array
{
    return db_fetch_all(
        "SELECT * FROM {{TABLE}} ORDER BY id DESC"
    );
}

function {{PREFIX}}_find(int $id): ?array
{
    return db_fetch(
        "SELECT * FROM {{TABLE}} WHERE id = ?",
        [$id]
    );
}

function {{PREFIX}}_create(array $data): string
{
    return db_insert('{{TABLE}}', [
        '{{PRIMARY_FIELD}}' => $data['{{PRIMARY_FIELD}}'],
    ]);
}

function {{PREFIX}}_update_data(int $id, array $data): int
{
    return db_update('{{TABLE}}', [
        '{{PRIMARY_FIELD}}' => $data['{{PRIMARY_FIELD}}'],
    ], ['id' => $id]);
}

function {{PREFIX}}_delete_by_id(int $id): int
{
    return db_delete('{{TABLE}}', ['id' => $id]);
}
PHP;
}

function controllers_template(): string
{
    return <<<'PHP'
<?php

/**
 * {{PLURAL_LABEL}} Module — Controllers
 *
 * Generated by scripts/make_crud.php
 */

function {{PREFIX}}_index(): void
{
    $records = {{PREFIX}}_all();

    view('{{MODULE}}::index', [
        'records' => $records,
    ], 'dashboard');
}

function {{PREFIX}}_create_form(): void
{
    view('{{MODULE}}::create', [], 'dashboard');
}

function {{PREFIX}}_store(): void
{
    csrf_check();

    $value = trim((string) request_input('{{PRIMARY_FIELD}}', ''));

    if ($value === '') {
        flash('error', '{{PRIMARY_LABEL}} is required.');
        flash_old_input();
        redirect('/{{ROUTE}}/create');
    }

    {{PREFIX}}_create([
        '{{PRIMARY_FIELD}}' => $value,
    ]);

    clear_old_input();
    flash('success', '{{SINGULAR_LABEL}} created successfully.');
    redirect('/{{ROUTE}}');
}

function {{PREFIX}}_edit_form(string $id): void
{
    $recordId = (int) $id;
    $record = {{PREFIX}}_find($recordId);

    if (!$record) {
        abort(404, '{{SINGULAR_LABEL}} not found.');
    }

    view('{{MODULE}}::edit', [
        'record' => $record,
    ], 'dashboard');
}

function {{PREFIX}}_update_action(string $id): void
{
    csrf_check();

    $recordId = (int) $id;
    $record = {{PREFIX}}_find($recordId);

    if (!$record) {
        abort(404, '{{SINGULAR_LABEL}} not found.');
    }

    $value = trim((string) request_input('{{PRIMARY_FIELD}}', ''));

    if ($value === '') {
        flash('error', '{{PRIMARY_LABEL}} is required.');
        flash_old_input();
        redirect('/{{ROUTE}}/' . $recordId . '/edit');
    }

    {{PREFIX}}_update_data($recordId, [
        '{{PRIMARY_FIELD}}' => $value,
    ]);

    clear_old_input();
    flash('success', '{{SINGULAR_LABEL}} updated successfully.');
    redirect('/{{ROUTE}}');
}

function {{PREFIX}}_delete_action(string $id): void
{
    csrf_check();

    $recordId = (int) $id;
    $record = {{PREFIX}}_find($recordId);

    if (!$record) {
        abort(404, '{{SINGULAR_LABEL}} not found.');
    }

    {{PREFIX}}_delete_by_id($recordId);
    flash('success', '{{SINGULAR_LABEL}} deleted.');
    redirect('/{{ROUTE}}');
}
PHP;
}

function index_view_template(): string
{
    return <<<'PHP'
<?php if ($success = get_flash('success')): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>Manage {{PLURAL_LABEL}}</h1>
    <a href="/{{ROUTE}}/create" class="btn btn-primary">+ New {{SINGULAR_LABEL}}</a>
</div>

<?php if (empty($records)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">No {{PLURAL_LABEL}} found. <a href="/{{ROUTE}}/create">Create the first one</a>.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body no-padding">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{PRIMARY_LABEL}}</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?= (int) $record['id'] ?></td>
                            <td><strong><?= e($record['{{PRIMARY_FIELD}}']) ?></strong></td>
                            <td class="actions">
                                <a href="/{{ROUTE}}/<?= (int) $record['id'] ?>/edit" class="btn btn-sm btn-outline">Edit</a>
                                <form method="POST" action="/{{ROUTE}}/<?= (int) $record['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
PHP;
}

function create_view_template(): string
{
    return <<<'PHP'
<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>Create {{SINGULAR_LABEL}}</h1>
    <a href="/{{ROUTE}}" class="btn btn-outline">← Back to {{PLURAL_LABEL}}</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/{{ROUTE}}">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="{{PRIMARY_FIELD}}">{{PRIMARY_LABEL}}</label>
                <input type="text" id="{{PRIMARY_FIELD}}" name="{{PRIMARY_FIELD}}" value="<?= old('{{PRIMARY_FIELD}}') ?>" required maxlength="255">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create {{SINGULAR_LABEL}}</button>
                <a href="/{{ROUTE}}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
PHP;
}

function edit_view_template(): string
{
    return <<<'PHP'
<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>Edit {{SINGULAR_LABEL}}</h1>
    <a href="/{{ROUTE}}" class="btn btn-outline">← Back to {{PLURAL_LABEL}}</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/{{ROUTE}}/<?= (int) $record['id'] ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="{{PRIMARY_FIELD}}">{{PRIMARY_LABEL}}</label>
                <input type="text" id="{{PRIMARY_FIELD}}" name="{{PRIMARY_FIELD}}" value="<?= old('{{PRIMARY_FIELD}}', $record['{{PRIMARY_FIELD}}']) ?>" required maxlength="255">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update {{SINGULAR_LABEL}}</button>
                <a href="/{{ROUTE}}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
PHP;
}

function routes_template(): string
{
    return <<<'PHP'
// {{PLURAL_LABEL}} CRUD
route('GET',  '/{{ROUTE}}',             '{{PREFIX}}_index',         ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('{{ROLE}}')]);
route('GET',  '/{{ROUTE}}/create',      '{{PREFIX}}_create_form',   ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('{{ROLE}}')]);
route('POST', '/{{ROUTE}}',             '{{PREFIX}}_store',         ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('{{ROLE}}')]);
route('GET',  '/{{ROUTE}}/{id}/edit',   '{{PREFIX}}_edit_form',     ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('{{ROLE}}')]);
route('POST', '/{{ROUTE}}/{id}',        '{{PREFIX}}_update_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('{{ROLE}}')]);
route('POST', '/{{ROUTE}}/{id}/delete', '{{PREFIX}}_delete_action', ['middleware_auth', 'middleware_onboarding_complete', fn() => middleware_role('{{ROLE}}')]);
PHP;
}
