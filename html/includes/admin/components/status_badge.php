<?php
/** Escape both the display label and generated CSS class for reusable PHP badges. */
function ad_status_badge(string $status): string
{
    $class = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $status));
    return '<span class="status-badge status-' . ad_e($class) . '"><i></i>' . ad_e($status) . '</span>';
}
