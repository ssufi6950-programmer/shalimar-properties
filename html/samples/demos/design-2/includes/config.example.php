<?php
/** Site settings template. Copy to config.php and set private credentials locally. */

const ADMIN_USER      = 'admin';
const ADMIN_PASS_HASH = '';

/* File-based storage (no MySQL needed). Folder must be writable by PHP. */
const DATA_DIR    = __DIR__ . '/../data';
const UPLOAD_DIR  = __DIR__ . '/../uploads/listings';
const UPLOAD_URL  = 'uploads/listings';

/* Image upload rules */
const UPLOAD_MAX_FILES = 6;
const UPLOAD_MAX_BYTES = 4 * 1024 * 1024; // 4 MB per image

/* Projects (Shalimar Smart City, Gulberg City, Zalmi X ...) are data-driven:
 * they live in data/projects.json.php and are managed from the admin panel. */

/* Contact shown on the site */
const SITE_PHONE    = 'UAN +92-48-111-222-084';
const SITE_TEL      = '+9248111222084';
const SITE_PHONES   = [['UAN +92-48-111-222-084', '+9248111222084'], ['048-3722644', '+92483722644'], ['048-3723326', '+92483723326']];
const SITE_EMAIL    = 'info@shalimarco.com';
const SITE_WHATSAPP = '923272222627';
const SITE_ADDRESS  = '2km Mall Road, Sargodha';

/* Publishing modes a role can have */
const PUBLISH_MODES = [
  'live'        => 'Goes live immediately',
  'live_logged' => 'Goes live immediately + logged in records',
  'review'      => 'Pending — admin approval required',
  'blocked'     => 'Not allowed to post',
];
