<?php
declare(strict_types=1);
$private=dirname(__DIR__).'/private';
if(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/instalar'){require __DIR__.'/install.php';exit;}
if(!is_file($private.'/installed.lock')){header('Location: /instalar');exit;}
$active=json_decode((string)file_get_contents($private.'/active.json'),true);
if(!preg_match('/^\d+\.\d+\.\d+$/',$active['version']??'')){http_response_code(503);exit('Versão indisponível.');}
define('TR_PRIVATE',$private);define('TR_RELEASE',$private.'/releases/'.$active['version']);
require TR_RELEASE.'/app/bootstrap.php';
