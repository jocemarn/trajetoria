<?php declare(strict_types=1);

function normalizeInstallationOrigin(string $url):string{
 $url=rtrim(trim($url),'/');$parts=parse_url($url);
 if(!$parts||!filter_var($url,FILTER_VALIDATE_URL)||!in_array($parts['scheme']??'',['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||!empty($parts['path']))throw new Fault('Informe o endereço completo do sistema, sem caminho.');
 $scheme=$parts['scheme'];$host=strtolower($parts['host']);$port=$parts['port']??null;
 return $scheme.'://'.$host.($port!==null&&$port!==($scheme==='https'?443:80)?':'.$port:'');
}

function temporaryInstallationHttpOrigin(string $file):?string{
 if(!is_file($file))return null;
 try{$data=json_decode((string)file_get_contents($file),true,16,JSON_THROW_ON_ERROR);$url=normalizeInstallationOrigin((string)($data['allowedOrigin']??''));$p=parse_url($url);if($p['scheme']!=='http'||!filter_var($p['host'],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))return null;return $url;}catch(Throwable){return null;}
}

function installationRequestOrigin(array $server):string{
 $https=!empty($server['HTTPS'])&&$server['HTTPS']!=='off';
 return normalizeInstallationOrigin(($https?'https':'http').'://'.($server['HTTP_HOST']??''));
}

function validateInstallationOrigin(string $url,array $server,string $bootstrapFile):array{
 $url=normalizeInstallationOrigin($url);$request=installationRequestOrigin($server);
 if(!hash_equals($request,$url))throw new Fault('Abra o instalador no mesmo endereço e protocolo informado.');
 $p=parse_url($url);$loopback=in_array($p['host'],['localhost','127.0.0.1','[::1]'],true)&&in_array($server['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
 $allowed=temporaryInstallationHttpOrigin($bootstrapFile);$temporary=$p['scheme']==='http'&&!$loopback&&$allowed!==null&&hash_equals($allowed,$url);
 if($p['scheme']!=='https'&&!$loopback&&!$temporary)throw new Fault('Informe a URL HTTPS do domínio. O acesso HTTP por IP exige habilitação local no servidor.');
 return ['url'=>$url,'require_https'=>$p['scheme']==='https','temporary_http_origin'=>$temporary];
}

function defaultInstallationOrigin(array $server,string $bootstrapFile):string{
 try{$request=installationRequestOrigin($server);$allowed=temporaryInstallationHttpOrigin($bootstrapFile);if($allowed!==null&&hash_equals($allowed,$request))return $request;}catch(Throwable){}
 return 'https://trajetoria.net.br';
}
