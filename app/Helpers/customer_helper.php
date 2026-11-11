<?php

function customer_asset(string $path): string
{
    return base_url('assets/customer/' . ltrim($path, '/'));
}

/** Presentation copy only: never apply these replacements to identifiers or saved drafts. */
function customer_ui_copy(): array
{
    return [
        'Public resale is disabled for this ERP Head/Subhead.'=>'Listing is not available for this project.',
        'This ERP Head/Subhead has not been mapped to a portal project.'=>'Listing is not available for this project yet. Please contact support.',
        'The synchronized ERP catalog does not currently permit public resale.'=>'The project is not currently open for new listings.',
        'The project or mapping is not currently published/active.'=>'The project is not currently open for new listings.',
        'ERP booking approval status could not be confirmed.'=>'The booking approval status could not be confirmed. Please contact support.',
        'Current properties from this ERP could not be loaded. Please try again.'=>'Your property information could not be loaded. Please try again.',
        'An ERP source could not be fully loaded. Refresh to retry.'=>'Some property information could not be loaded. Refresh to try again.',
        'Verify another ERP identity'=>'Connect another portfolio',
        'verified ERP identities'=>'verified property accounts',
        'verified ERP identity'=>'verified property account',
        'ERP identities'=>'property accounts',
        'ERP identity'=>'property account',
        'ERP-registered'=>'registered',
        'ERP registered'=>'registered',
        'ERP records'=>'property records',
        'ERP office'=>'property office',
        'ERP sources'=>'portfolios',
        'ERP source'=>'portfolio',
        'ERP property'=>'property',
        'ERP booking'=>'booking',
        'ERP approval'=>'booking approval',
        'ERP Head'=>'Project',
    ];
}

function customer_ui_text(string $value): string
{
    $value=strtr($value,customer_ui_copy());
    $value=preg_replace('/\b(SGC|Zalmi)\s+ERP\b/i','$1',$value)??$value;
    return preg_replace('/\bERP\b/i','property service',$value)??$value;
}

/** Copies only display fields. Source IDs, status values, tokens and sealed drafts are intact. */
function customer_portal_view_data(array $data): array
{
    if(is_string($data['pageTitle']??null))$data['pageTitle']=customer_ui_text($data['pageTitle']);
    foreach(['sources','sourceWarnings','records']as$key){
        if(!is_array($data[$key]??null))continue;
        foreach($data[$key]as&$row)foreach(['name','source_name','message']as$field)
            if(is_string($row[$field]??null))$row[$field]=customer_ui_text($row[$field]);
        unset($row);
    }
    foreach(['property','selectedProperty','preview']as$key){
        if(!is_array($data[$key]??null))continue;
        foreach(['erp','project','head','subhead','reason','booking_processing_status']as$field)
            if(is_string($data[$key][$field]??null))$data[$key][$field]=customer_ui_text($data[$key][$field]);
        foreach($data[$key]['listing_actions']??[]as$purpose=>$action)
            if(is_string($action['reason']??null))$data[$key]['listing_actions'][$purpose]['reason']=customer_ui_text($action['reason']);
    }
    if(is_string($data['challenge']['local_test_otp_note']??null))
        $data['challenge']['local_test_otp_note']=customer_ui_text($data['challenge']['local_test_otp_note']);
    return $data;
}

/** Asset timestamps are optional cache metadata, including with the html/public bridge. */
function customer_asset_version(string $path): string
{
    $relative = 'assets/customer/' . ltrim($path, '/');
    foreach ([FCPATH . $relative, ROOTPATH . 'html/public/' . $relative] as $file) {
        // A missing/inaccessible optional timestamp must never prevent a page rendering.
        $timestamp = @filemtime($file);
        if ($timestamp !== false) return (string) $timestamp;
    }
    return '20261111';
}

function customer_badge(string $status): string
{
    $class = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $status), '-'));
    return '<span class="portal-badge badge-' . esc($class, 'attr') . '"><i></i>' . esc($status) . '</span>';
}

function customer_price(int $value): string
{
    return 'PKR ' . number_format($value);
}

function customer_public_url():string
{
 return \App\Libraries\PortalLinks::publicSite('index.php');
}

function customer_listing_status(array $record): array
{
    if(($record['status']??'')==='sold'||($record['deal']??'')==='sold')return ['label'=>'Sold','tone'=>'neutral','key'=>'sold'];
    if(!empty($record['customer_withdrawn']))return ['label'=>'Withdrawn','tone'=>'amber','key'=>'withdrawn'];
    if(\App\Services\ListingLifecycle::active($record))return ['label'=>'Active','tone'=>'green','key'=>'active'];
    $status=$record['status']??'';
    if(!in_array($status,['unlisted','cancelled','removed','rejected'],true)){
        $review=$record['review_state']??'under_review';
        if(in_array($review,['under_review','rejected','needs_changes'],true))return ['label'=>['under_review'=>'Under Review','rejected'=>'Rejected','needs_changes'=>'Needs Changes'][$review],'tone'=>$review==='rejected'?'red':'amber','key'=>$review];
    }
    return ['label'=>['unlisted'=>'Unpublished by admin','cancelled'=>'Cancelled','rejected'=>'Rejected','removed'=>'Removed'][$status]??ucfirst($status?:'Not recorded'),'tone'=>in_array($status,['rejected','unlisted'],true)?'red':'neutral','key'=>$status];
}

function customer_listing_purpose(array $record):string
{
    return ($record['listing_purpose']??'sale')==='rent'?'For Rent':'For Sale';
}

function customer_selling_method(array $record): string
{
    return ['self_sell'=>($record['listing_purpose']??'sale')==='rent'?'Direct Owner':'Self Sell','shalimar_agent'=>'Shalimar Agent','authorized_agency'=>'Authorized Agency'][$record['selling_method']??'']??'Not recorded';
}

function customer_date($value): string
{
    if(!is_string($value)||trim($value)==='')return 'Not recorded';
    try{return (new DateTimeImmutable($value,new DateTimeZone(config('App')->appTimezone)))->format('d M Y, g:i A');}
    catch(Throwable $e){return 'Not recorded';}
}
