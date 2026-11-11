<div class="app-property-purpose-actions">
<?php foreach(['sale'=>'List for Resale','rent'=>'List for Rent']as$purpose=>$label):$action=$property['listing_actions'][$purpose]??['eligible'=>false,'reason'=>'Not currently eligible','listing_id'=>null]; ?>
<?php if($action['listing_id']): ?><a class="btn btn-outline" href="<?= url_to('customer.resale.details',(string)$action['listing_id']) ?>">View <?= $purpose==='rent'?'Rent':'Sale' ?> Status</a>
<?php elseif($action['eligible']): ?><button type="button" class="btn <?= $purpose==='rent'?'btn-outline app-rent-action':'btn-primary' ?>" data-resale-ref="<?= esc($property['id'],'attr') ?>" data-listing-purpose="<?= $purpose ?>"><?= $label ?></button>
<?php else: ?><button class="btn btn-outline" disabled><?= $label ?></button><small class="text-muted"><?= esc($action['reason']) ?></small><?php endif ?>
<?php endforeach ?>
</div>
