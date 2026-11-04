<?php
declare(strict_types=1);
define('TR_PRIVATE',dirname(__DIR__).'/private');
if(is_file(TR_PRIVATE.'/installed.lock')){http_response_code(404);exit;}
$a=json_decode((string)file_get_contents(TR_PRIVATE.'/active.json'),true);
if(!preg_match('/^\d+\.\d+\.\d+$/',$a['version']??'')){http_response_code(503);exit;}
define('TR_RELEASE',TR_PRIVATE.'/releases/'.$a['version']);
require TR_RELEASE.'/app/installer.php';
