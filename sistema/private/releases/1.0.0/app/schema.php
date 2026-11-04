<?php
declare(strict_types=1);
function createSchema(PDO $p):void{$mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
$ddl=[
'tr_meta (id VARCHAR(64) PRIMARY KEY,value TEXT NOT NULL)',
'tr_tenants (id VARCHAR(64) PRIMARY KEY,name VARCHAR(120) NOT NULL,state VARCHAR(8) NOT NULL,is_demo INTEGER NOT NULL DEFAULT 0)',
'tr_users (id VARCHAR(64) PRIMARY KEY,tenant VARCHAR(64),email VARCHAR(190) NOT NULL UNIQUE,name VARCHAR(120) NOT NULL,role VARCHAR(24) NOT NULL,person VARCHAR(64),unit VARCHAR(64),power VARCHAR(24),password_hash VARCHAR(255) NOT NULL,cpf_hash VARCHAR(64) UNIQUE,active INTEGER NOT NULL,is_demo INTEGER NOT NULL,demo_until VARCHAR(10),must_change INTEGER NOT NULL,auth_epoch INTEGER NOT NULL,FOREIGN KEY(tenant) REFERENCES tr_tenants(id))',
'tr_records (tenant VARCHAR(64) NOT NULL,kind VARCHAR(24) NOT NULL,id VARCHAR(64) NOT NULL,version INTEGER NOT NULL,payload LONGTEXT NOT NULL,PRIMARY KEY(tenant,kind,id),FOREIGN KEY(tenant) REFERENCES tr_tenants(id))',
'tr_contracts (id VARCHAR(64) PRIMARY KEY,tenant VARCHAR(64) NOT NULL,payload TEXT NOT NULL,FOREIGN KEY(tenant) REFERENCES tr_tenants(id))',
'tr_files (id VARCHAR(64) PRIMARY KEY,tenant VARCHAR(64) NOT NULL,link_id VARCHAR(64) NOT NULL,name VARCHAR(190) NOT NULL,size INTEGER NOT NULL,hash VARCHAR(64) NOT NULL,created VARCHAR(40) NOT NULL,FOREIGN KEY(tenant) REFERENCES tr_tenants(id))',
'tr_audit (id VARCHAR(64) PRIMARY KEY,tenant VARCHAR(64) NOT NULL,actor VARCHAR(64) NOT NULL,action VARCHAR(100) NOT NULL,target VARCHAR(100) NOT NULL,created VARCHAR(40) NOT NULL)',
'tr_login (id VARCHAR(64) PRIMARY KEY,count INTEGER NOT NULL,until_at INTEGER NOT NULL)',
'tr_updates (id VARCHAR(64) PRIMARY KEY,from_version VARCHAR(24) NOT NULL,to_version VARCHAR(24) NOT NULL,status VARCHAR(24) NOT NULL,created VARCHAR(40) NOT NULL,actor VARCHAR(64) NOT NULL,backup VARCHAR(120))'];
foreach($ddl as $d)$p->exec('CREATE TABLE IF NOT EXISTS '.$d.$tail);
$s=$p->prepare('SELECT value FROM tr_meta WHERE id=?');$s->execute(['schema']);if(!$s->fetch()){$s=$p->prepare('INSERT INTO tr_meta(id,value) VALUES(?,?)');$s->execute(['schema','1']);}}
