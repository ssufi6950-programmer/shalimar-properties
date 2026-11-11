<?php $pager=$pagination;$query=$filters; ?>
<nav class="app-pagination" aria-label="Listing pagination">
    <span><?= $pager['total']?number_format(($pager['page']-1)*$pager['per_page']+1):0 ?>–<?= number_format(min($pager['page']*$pager['per_page'],$pager['total'])) ?> of <?= number_format($pager['total']) ?> listings</span>
    <div>
        <?php foreach([['Previous',-1,'chevron-left'],['Next',1,'chevron-right']]as[$label,$delta,$icon]):$target=$pager['page']+$delta; ?>
            <?php if($target<1||$target>$pager['pages']): ?><span class="btn btn-outline btn-sm disabled" aria-disabled="true"><?= $label ?></span>
            <?php else: ?><a class="btn btn-outline btn-sm" href="<?= esc(current_url().'?'.http_build_query(array_replace($query,['page'=>$target])),'attr') ?>" rel="<?= $delta<0?'prev':'next' ?>"><?= $label ?><i class="bi bi-<?= $icon ?>" aria-hidden="true"></i></a><?php endif ?>
        <?php endforeach ?>
        <span class="app-page-number">Page <?= $pager['page'] ?> / <?= $pager['pages'] ?></span>
    </div>
</nav>
