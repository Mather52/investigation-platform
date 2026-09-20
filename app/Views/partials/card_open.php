<section class="card" <?= isset($id) ? 'id="' . esc($id, 'attr') . '"' : '' ?>>
    <div class="card-head">
        <div class="card-title"><?= icon($icon ?? 'file', 22) ?><h2><?= esc($title) ?></h2></div>
        <?= $right ?? '' ?>
    </div>
    <div class="card-body <?= $bodyClass ?? '' ?>">
