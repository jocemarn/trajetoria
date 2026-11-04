<?php
declare(strict_types=1);
$root=dirname(__DIR__);$accounts=json_decode((string)file_get_contents($root.'/.signing/accounts-to-hash.json'),true);
foreach($accounts as &$a){$a['password_hash']=password_hash($a['password'],PASSWORD_DEFAULT);unset($a['password']);}unset($a);
file_put_contents($root.'/private/demo-accounts.json',json_encode($accounts,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
