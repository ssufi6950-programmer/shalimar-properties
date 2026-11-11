<?= $this->extend('customer/layouts/main') ?><?= $this->section('content') ?>
<main class="portal-main portal-container app-property-choose" id="main-content" data-live-portfolio data-property-selection data-purpose="<?= esc($listingPurpose,'attr') ?>">
 <a class="app-back" href="<?= url_to('customer.properties') ?>" role="button" data-bs-toggle="offcanvas" data-bs-target="#customerListProperty" aria-controls="customerListProperty"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to listing options</a>
 <div class="app-choose-heading"><h1>Choose a Property</h1><p>Select a property you want to list for <?= $listingPurpose==='rent'?'rent':'resale' ?>.</p></div>
 <div data-source-status aria-live="polite"></div>
 <section class="app-data-panel app-property-selection-panel" aria-labelledby="propertyTableTitle">
  <div class="app-panel-heading"><div><h2 id="propertyTableTitle">Your properties</h2><p id="propertyResultCount" aria-live="polite">Loading your properties...</p></div><button class="btn btn-outline btn-sm" type="button" data-refresh-properties aria-label="Refresh properties"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button></div>
  <div class="app-table-toolbar"><div class="app-search"><i class="bi bi-search" aria-hidden="true"></i><label class="visually-hidden" for="propertySearch">Search property or project</label><input class="form-control" type="search" id="propertySearch" placeholder="Search property or project"></div></div>
  <p class="form-error m-3" id="portfolioActionError" role="alert" hidden></p>
  <p class="app-picker-availability" data-picker-availability role="status" hidden></p>
  <ul class="app-property-selection-list" data-live-property-rows aria-labelledby="propertyTableTitle"><li class="app-table-state" role="status">Loading your properties...</li></ul>
  <nav class="app-pagination app-picker-pagination" aria-label="Property pagination"><span data-property-page-info>Loading...</span><div><label class="visually-hidden" for="propertyPageSize">Rows per page</label><select class="form-select" id="propertyPageSize"><option value="10">10 / page</option><option value="20">20 / page</option><option value="50">50 / page</option></select><button class="btn btn-outline btn-sm" type="button" data-property-prev aria-label="Previous page" disabled>Prev</button><span class="app-page-number" data-property-page-label>1 / 1</span><button class="btn btn-outline btn-sm" type="button" data-property-next disabled>Next</button></div></nav>
 </section>
</main>
<?= $this->endSection() ?>
