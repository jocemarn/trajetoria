<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/index.php'||$path==='/install.php'||$path==='/instalar'||!is_file($_SERVER['DOCUMENT_ROOT'].$path)){require $_SERVER['DOCUMENT_ROOT'].'/index.php';return true;}
return false;
