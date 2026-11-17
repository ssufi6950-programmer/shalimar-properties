<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/admin/css/project-editor.css') ?>">
<div class="ppe-editor" id="projectPageEditor">
    <div class="ppe-toolbar">
        <div>
            <a href="<?= site_url('admin/workspace/projects') ?>" class="back-link">&larr; Projects</a>
            <h1><?= esc($pageTitle) ?></h1>
            <p>Double-click text to edit. Use + to add items.</p>
        </div>
        <div class="ppe-toolbar-actions">
            <span id="projectSaveState" role="status">Project content</span>
            <a id="projectPublicJson" class="btn btn-outline-secondary" href="<?= $record ? site_url('api/projects/' . rawurlencode($record['slug'])) : '#' ?>" target="_blank" rel="noopener"<?= $record ? '' : ' hidden' ?>>Public JSON</a>
            <button type="button" id="projectTemplates" class="btn btn-outline-secondary">Templates</button>
            <button type="button" id="projectSave" class="btn btn-primary">Save Project</button>
        </div>
    </div>
    <div id="projectEditorMessage" class="alert" role="alert" hidden></div>
    <div class="ppe-template-picker" id="projectTemplatePicker"<?= $record && $record['template'] ? ' hidden' : '' ?>>
        <h2>Choose a starting template</h2>
        <div class="ppe-template-options">
            <?php foreach ($templates as $key => $preset): ?>
                <button type="button" class="ppe-template-option" data-template="<?= esc($key, 'attr') ?>">
                    <img src="<?= esc(\App\Controllers\Admin\ProjectEditor::imageUrl($preset['document']['cover']), 'attr') ?>" alt="">
                    <strong><?= esc($preset['label']) ?></strong>
                    <span>Use this template</span>
                </button>
            <?php endforeach ?>
        </div>
    </div>
    <div class="ppe-document-details">
        <label for="projectEditorSlug">Project slug</label>
        <input id="projectEditorSlug" class="form-control" maxlength="160" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= esc($record['slug'] ?? '', 'attr') ?>" <?= $record ? 'readonly' : '' ?>>
        <span>HTML, CSS and JS are saved together. Public JSON follows the project's visibility settings.</span>
    </div>
    <div class="ppe-canvas" id="projectEditorCanvas"></div>
    <dialog id="projectItemDialog" class="ppe-dialog">
        <form id="projectItemForm">
            <div class="ppe-dialog-heading">
                <h2 id="projectDialogTitle">Edit item</h2>
                <button type="button" data-close-dialog aria-label="Close">&times;</button>
            </div>
            <div id="projectDialogFields"></div>
            <p id="projectDialogError" role="alert" hidden></p>
            <div class="ppe-dialog-actions">
                <button type="button" class="btn btn-outline-secondary" data-close-dialog>Cancel</button>
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </dialog>
    <input id="projectImageFile" type="file" accept="image/jpeg,image/png,image/webp" hidden>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
window.ProjectPageEditor = <?= json_encode([
    'record' => $record,
    'templates' => $templates,
    'routes' => [
        'save' => site_url('admin/project-editor/save'),
        'upload' => site_url('admin/project-editor/images'),
        'image' => site_url('admin/project-editor/images'),
        'public' => admin_legacy_url(),
        'api' => site_url('api/projects'),
    ],
    'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
</script>
<script src="<?= base_url('assets/admin/js/project-editor.js') ?>"></script>
<?= $this->endSection() ?>
