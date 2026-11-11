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
const SITE_PHONE    = '(+92) 304-111-2627';
const SITE_WHATSAPP = '923272222627';
const SITE_ADDRESS  = 'BBT 02 First Floor, Shalimar Smart City, Mall Road, Sargodha';

/* Publishing modes a role can have */
const PUBLISH_MODES = [
  'live'        => 'Goes live immediately',
  'live_logged' => 'Goes live immediately + logged in records',
  'review'      => 'Pending — admin approval required',
  'blocked'     => 'Not allowed to post',
];
