<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Your Shalimar Properties customer portal. View your current properties securely.">
    <meta name="robots" content="noindex,nofollow">
    <title><?= esc($pageTitle) ?> Â· Shalimar Properties</title>
    <link rel="icon" href="<?= customer_asset('images/sp-mark.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= customer_asset('vendor/inter.css') ?>">
    <link rel="stylesheet" href="<?= customer_asset('vendor/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= customer_asset('vendor/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= customer_asset('vendor/select2.min.css') ?>">
    <link rel="stylesheet" href="<?= customer_asset('css/customer.css') ?>">
    <link rel="stylesheet" href="<?= customer_asset('css/responsive.css') ?>">
    <link rel="stylesheet" href="<?= customer_asset('css/customer-app.css') ?>?v=<?= customer_asset_version('css/customer-app.css') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body class="customer-app <?= !empty($authPage)?'auth-body app-auth':'portal-body app-portal' ?>" data-auth-page="<?= !empty($authPage)?'true':'false' ?>">
<a href="#main-content" class="skip-link">Skip to content</a>
<?php if (empty($authPage)): ?>
    <?= $this->include('customer/layouts/header') ?>

<?php endif; ?>
<?= $this->renderSection('content') ?>
<?php if (empty($authPage)): ?><?= $this->include('customer/layouts/footer') ?><?php endif; ?>
<?php if (empty($authPage)): ?><?= $this->include('customer/components/confirm_dialog') ?><?php endif; ?>
<div id="portalToast" class="portal-toast-region" aria-live="polite"></div>
<script>
window.CustomerPortal=<?= json_encode(['uiCopy'=>customer_ui_copy(),'customerId'=>$customer['id'],'assets'=>customer_asset(''),'sources'=>$sources,'sourceWarnings'=>$sourceWarnings,'csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()],'routes'=>['login'=>url_to('customer.login'),'request'=>site_url('customer/otp/request'),'verifyOtp'=>site_url('customer/otp/verify'),'resend'=>site_url('customer/otp/resend'),'propertiesApi'=>site_url('customer/api/properties'),'property'=>site_url('customer/properties'),'resaleBase'=>site_url('customer/resale'),'prepare'=>site_url('customer/resale/prepare'),'resaleOtpVerify'=>site_url('customer/resale/otp/verify'),'resaleOtpResend'=>site_url('customer/resale/otp/resend'),'resaleOtpInvalidate'=>site_url('customer/resale/otp/invalidate')]],JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= customer_asset('vendor/jquery.min.js') ?>"></script>
<script src="<?= customer_asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= customer_asset('vendor/sweetalert2.all.min.js') ?>"></script>
<script src="<?= customer_asset('vendor/select2.min.js') ?>"></script>
<script src="<?= customer_asset('js/customer-app.js') ?>?v=<?= customer_asset_version('js/customer-app.js') ?>"></script>
<script src="<?= customer_asset('js/customer-live.js') ?>?v=<?= customer_asset_version('js/customer-live.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
