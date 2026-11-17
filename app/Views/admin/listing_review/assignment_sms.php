<?php if(!empty($assignmentSms)): $smsState=$assignmentSms['status']; ?>
<section class="card review-panel"><h2>Agent assignment SMS</h2>
  <p role="status"><?= esc(['pending'=>'Awaiting delivery.','sending'=>'Delivery is in progress or unconfirmed. Check gateway delivery before resending.','sent'=>'SMS request accepted by the gateway.','failed'=>'SMS delivery was not confirmed. Listing approval is preserved.','uncertain'=>'The gateway accepted the request, but its result could not be fully recorded. Check delivery before resending.','skipped'=>'The listing assignment changed; this SMS was not sent.'][$smsState]??'Delivery status unavailable.') ?></p>
  <?php if($assignmentSms['recipient_mask']): ?><p><?= (int)$assignmentSms['test_mode']?'Test recipient':'Agent recipient' ?>: <?= esc($assignmentSms['recipient_mask']) ?></p><?php endif ?>
  <p>Attempts: <?= (int)$assignmentSms['attempts'] ?><?php if($assignmentSms['sent_at']): ?> &middot; Sent <?= esc(customer_date($assignmentSms['sent_at'])) ?><?php endif ?></p>
  <?php if(in_array($smsState,['pending','failed'],true)&&\App\Services\ListingPolicy::active($record)&&can('resale_listings.approve')): ?><button class="btn btn-outline-secondary" type="button" data-assignment-sms-retry data-attempts="<?= (int)$assignmentSms['attempts'] ?>">Retry agent SMS</button><?php endif ?>
</section>
<?php endif ?>
