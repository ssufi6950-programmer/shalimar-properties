<?php if(!empty($assignmentSms)): $smsState=$assignmentSms['status']; ?>
<section class="card review-panel"><h2>Agent assignment SMS</h2>
  <p role="status"><?= esc(['pending'=>'Awaiting delivery.','sending'=>'Delivery is in progress or unconfirmed. Check gateway delivery before resending.','sent'=>'SMS request accepted by the gateway.','failed'=>'SMS delivery was not confirmed. Listing approval is preserved.','uncertain'=>'The gateway accepted the request, but its result could not be fully recorded. Check delivery before resending.','skipped'=>'The listing assignment changed; this SMS was not sent.'][$smsState]??'Delivery status unavailable.') ?></p>
  <?php if($assignmentSms['recipient_mask']): ?><p><?= (int)$assignmentSms['test_mode']?'Test recipient':'Agent recipient' ?>: <?= esc($assignmentSms['recipient_mask']) ?></p><?php endif ?>
  <p>Attempts: <?= (int)$assignmentSms['attempts'] ?><?php if($assignmentSms['sent_at']): ?> &middot; Sent <?= esc(customer_date($assignmentSms['sent_at'])) ?><?php endif ?></p>
  <?php if(in_array($smsState,['pending','failed'],true)&&\App\Services\ListingPolicy::active($record)&&can('resale_listings.approve')): ?><button class="btn btn-outline-secondary" type="button" data-assignment-sms-retry data-attempts="<?= (int)$assignmentSms['attempts'] ?>">Retry agent SMS</button><?php endif ?>
</section>
<?php endif ?>
<?php if(!empty($assignmentWhatsapp)): $waState=$assignmentWhatsapp['status']; ?>
<section class="card review-panel"><h2>Agent assignment WhatsApp</h2>
  <p role="status"><?= esc(['pending'=>'WhatsApp notification is queued.','sending'=>'WhatsApp request is in progress.','accepted'=>'Meta accepted the template request; delivery is not confirmed until a webhook reports it.','failed'=>'WhatsApp request failed. Listing approval is preserved.','uncertain'=>'The request result is uncertain. Check WhatsApp delivery before retrying.','skipped'=>'The listing assignment changed; WhatsApp was not sent.'][$waState]??'WhatsApp status unavailable.') ?></p>
  <?php if(!empty($assignmentWhatsapp['recipient_mask'])): ?><p><?= (int)$assignmentWhatsapp['test_mode']?'Test recipient':'Agent recipient' ?>: <?= esc($assignmentWhatsapp['recipient_mask']) ?></p><?php endif ?>
  <p>Attempts: <?= (int)$assignmentWhatsapp['attempts'] ?><?php if(!empty($assignmentWhatsapp['accepted_at'])): ?> &middot; Accepted <?= esc(customer_date($assignmentWhatsapp['accepted_at'])) ?><?php endif ?></p>
  <?php if(in_array($waState,['pending','failed'],true)&&\App\Services\ListingPolicy::active($record)&&can('resale_listings.approve')): ?><button class="btn btn-outline-secondary" type="button" data-assignment-whatsapp-retry data-attempts="<?= (int)$assignmentWhatsapp['attempts'] ?>">Retry agent WhatsApp</button><?php endif ?>
</section>
<?php endif ?>
