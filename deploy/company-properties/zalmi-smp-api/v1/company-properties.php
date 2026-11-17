<?php
declare(strict_types=1);
define('SMP_API_MOUNT_PATH',dirname($_SERVER['SCRIPT_NAME']??'',2));
require dirname(__DIR__).'/index.php';
