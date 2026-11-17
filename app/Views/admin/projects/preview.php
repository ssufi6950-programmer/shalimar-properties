<?php

$editing = $editing ?? false;
$linkUrl = $linkUrl ?? static fn (string $url): string => $url;

$attribute = static fn (string $path): string => $editing
    ? ' data-field="' . esc($path, 'attr') . '" tabindex="0" title="Double-click or press Enter to edit"'
    : '';

$add = static fn (string $path, string $label): string => $editing
    ? '<button type="button" class="ppe-add" data-add="' . esc($path, 'attr')
        . '" aria-label="' . esc($label, 'attr') . '">+</button>'
    : '';

$remove = static fn (string $path): string => $editing
    ? '<button type="button" class="ppe-remove" data-remove="' . esc($path, 'attr')
        . '" aria-label="Remove item">&times;</button>'
    : '';

$image = static function (string $path, string $reference, string $alt) use ($editing, $imageUrl): string {
    $html = '<div class="ppe-image"'
        . ($editing ? ' data-image="' . esc($path, 'attr') . '"' : '') . '>';
    $html .= $reference !== ''
        ? '<img src="' . esc($imageUrl($reference), 'attr') . '" alt="' . esc($alt, 'attr') . '">'
        : '<span class="ppe-image-empty">Image</span>';

    if ($editing) {
        $html .= '<div class="ppe-image-tools"><button type="button" data-edit-image="'
            . esc($path, 'attr') . '">' . ($reference === '' ? 'Add' : 'Edit') . ' image</button>'
            . '<button type="button" data-clear-image="' . esc($path, 'attr')
            . '" aria-label="Remove image">&times;</button></div>';
    }

    if (!$editing && str_starts_with($path, 'gallery.') && $reference !== '') {
        return '<a href="' . esc($imageUrl($reference), 'attr') . '" data-project-gallery aria-label="Open project photo">' . $html . '</div></a>';
    }

    return $html . '</div>';
};
?>
<article class="ppe-preview ppe-template-<?= esc($template, 'attr') ?>">
    <div class="ppe-media">
        <?= $image('cover', $document['cover'], $document['name']) ?>
        <div class="ppe-logo">
            <?= $image('logo', $document['logo'], $document['name'] . ' logo') ?>
        </div>
    </div>

    <div class="ppe-info">
        <div class="ppe-tags">
            <span class="ppe-pill"<?= $attribute('status') ?>><?= esc($document['status']) ?></span>
            <span class="ppe-pill ppe-gold"<?= $attribute('kind') ?>><?= esc($document['kind']) ?></span>
        </div>
        <h2<?= $attribute('name') ?>><?= esc($document['name']) ?></h2>
        <p class="ppe-tagline"<?= $attribute('tagline') ?>><?= esc($document['tagline']) ?></p>
        <p class="ppe-location">
            <span aria-hidden="true">⌖</span>
            <span<?= $attribute('location') ?>><?= esc($document['location']) ?></span>
        </p>
        <p class="ppe-summary"<?= $attribute('summary') ?>><?= esc($document['summary']) ?></p>

        <div class="ppe-section-heading">
            <small<?= $attribute('labels.features') ?>><?= esc($document['labels']['features']) ?></small>
            <?= $add('features', 'Add feature') ?>
        </div>
        <div class="ppe-features">
            <?php foreach ($document['features'] as $i => $feature): ?>
                <span class="ppe-item">
                    <span<?= $attribute('features.' . $i) ?>><?= esc($feature) ?></span>
                    <?= $remove('features.' . $i) ?>
                </span>
            <?php endforeach ?>
        </div>

        <div class="ppe-facts">
            <div>
                <small<?= $attribute('labels.developer') ?>><?= esc($document['labels']['developer']) ?></small>
                <strong<?= $attribute('developer') ?>><?= esc($document['developer']) ?></strong>
            </div>
            <?php foreach ($document['facts'] as $i => $fact): ?>
                <div>
                    <small<?= $attribute('facts.' . $i . '.label') ?>><?= esc($fact['label']) ?></small>
                    <strong<?= $attribute('facts.' . $i . '.value') ?>><?= esc($fact['value']) ?></strong>
                    <?= $remove('facts.' . $i) ?>
                </div>
            <?php endforeach ?>
            <?= $add('facts', 'Add fact card') ?>
        </div>

        <div class="ppe-progress-label">
            <span<?= $attribute('labels.progress') ?>><?= esc($document['labels']['progress']) ?></span>
            <strong><span<?= $attribute('progress') ?>><?= $document['progress'] ?></span>%</strong>
        </div>
        <div class="ppe-progress"><i style="width:<?= $document['progress'] ?>%"></i></div>

        <div class="ppe-section-heading">
            <small<?= $attribute('labels.gallery') ?>><?= esc($document['labels']['gallery']) ?></small>
            <?= $add('gallery', 'Add gallery image') ?>
        </div>
        <div class="ppe-gallery">
            <?php foreach ($document['gallery'] as $i => $photo): ?>
                <div class="ppe-gallery-item">
                    <?= $image('gallery.' . $i . '.src', $photo['src'], $photo['alt']) ?>
                    <?php if ($editing): ?>
                        <button type="button" class="ppe-remove" data-remove="gallery.<?= $i ?>" aria-label="Delete gallery image">&times;</button>
                        <span class="ppe-alt"<?= $attribute('gallery.' . $i . '.alt') ?>><?= esc($photo['alt']) ?></span>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>

        <div class="ppe-section-heading">
            <small<?= $attribute('labels.categories') ?>><?= esc($document['labels']['categories']) ?></small>
            <?= $add('categories', 'Add category') ?>
        </div>
        <div class="ppe-categories">
            <?php foreach ($document['categories'] as $i => $category): ?>
                <div class="ppe-category">
                    <div class="ppe-category-title">
                        <?php if (!$editing && $category['url'] !== ''): ?>
                            <a href="<?= esc($linkUrl($category['url']), 'attr') ?>"><strong><?= esc($category['label']) ?></strong></a>
                        <?php else: ?>
                            <strong<?= $attribute('categories.' . $i . '.label') ?>><?= esc($category['label']) ?></strong>
                        <?php endif ?>
                        <?php if ($editing): ?>
                            <button type="button" class="ppe-setting" data-settings="categories.<?= $i ?>" aria-label="Category settings">⚙</button>
                        <?php endif ?>
                        <?= $remove('categories.' . $i) ?>
                        <?= $add('categories.' . $i . '.blocks', 'Add block') ?>
                    </div>
                    <div class="ppe-blocks">
                        <?php foreach ($category['blocks'] as $j => $block): ?>
                            <span class="ppe-item">
                                <?php if (!$editing && $block['url'] !== ''): ?>
                                    <a href="<?= esc($linkUrl($block['url']), 'attr') ?>"><?= esc($block['label']) ?></a>
                                <?php else: ?>
                                    <span<?= $attribute('categories.' . $i . '.blocks.' . $j . '.label') ?>><?= esc($block['label']) ?></span>
                                <?php endif ?>
                                <?php if ($editing): ?>
                                    <button type="button" class="ppe-setting" data-settings="categories.<?= $i ?>.blocks.<?= $j ?>" aria-label="Block settings">⚙</button>
                                <?php endif ?>
                                <?= $remove('categories.' . $i . '.blocks.' . $j) ?>
                            </span>
                        <?php endforeach ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>

        <div class="ppe-price-actions">
            <strong class="ppe-price"<?= $attribute('price_from') ?>><?= esc($document['price_from']) ?></strong>
            <div class="ppe-actions">
                <?php foreach ($document['actions'] as $i => $action): ?>
                    <?php if (!$editing && $action['url'] !== ''): ?>
                        <a class="ppe-item ppe-action" href="<?= esc($linkUrl($action['url']), 'attr') ?>"><?= esc($action['label']) ?></a>
                    <?php else: ?>
                    <span class="ppe-item ppe-action">
                        <span<?= $attribute('actions.' . $i . '.label') ?>><?= esc($action['label']) ?></span>
                        <?php if ($editing): ?>
                            <button type="button" class="ppe-setting" data-settings="actions.<?= $i ?>" aria-label="Button settings">⚙</button>
                        <?php endif ?>
                        <?= $remove('actions.' . $i) ?>
                    </span>
                    <?php endif ?>
                <?php endforeach ?>
                <?= $add('actions', 'Add button') ?>
            </div>
        </div>
    </div>
</article>
