<div class="modal fade" id="dealerAssignmentModal" tabindex="-1" aria-labelledby="dealerAssignmentTitle" aria-hidden="true" data-base="<?= esc(site_url('agency/listings'),'attr') ?>" data-csrf-name="<?= esc(csrf_token(),'attr') ?>" data-csrf-hash="<?= esc(csrf_hash(),'attr') ?>">
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><h2 class="modal-title fs-5" id="dealerAssignmentTitle">Assign Dealer</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body"><p data-assignment-current class="small text-muted"></p>
<div data-picker><form data-dealer-search class="d-flex gap-2 mb-3"><label class="visually-hidden" for="assignmentSearch">Dealer name or mobile</label><input id="assignmentSearch" name="q" maxlength="100" class="form-control" placeholder="Search name or mobile"><button class="btn btn-outline-primary">Search</button></form>
<label for="assignmentMember" class="form-label">Matching dealers</label><select id="assignmentMember" class="form-select" size="5" aria-describedby="assignmentHint"></select>
<p id="assignmentHint" class="small text-muted mt-2">Only current dealers registered under this listing's agency and source are shown. Locked/staff flags are informational.</p>
<div class="d-flex justify-content-between my-2"><button type="button" class="btn btn-sm btn-outline-secondary" data-dealer-prev>Previous</button><span class="small" data-dealer-page></span><button type="button" class="btn btn-sm btn-outline-secondary" data-dealer-next>Next</button></div></div>
<div data-dealer-message class="small mt-3" role="status" aria-live="polite"></div>
<div data-confirmation hidden class="alert alert-warning mt-3"><p class="mb-2" data-confirmation-text></p><button type="button" class="btn btn-primary" data-confirm-save>Confirm</button> <button type="button" class="btn btn-outline-secondary" data-confirm-cancel>Cancel</button></div>
</div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" data-dealer-save disabled>Assign Dealer</button></div>
</div></div></div>
