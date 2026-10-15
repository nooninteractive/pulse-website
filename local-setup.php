<?php
declare(strict_types=1);
$directory = getenv('PULSE_DATA_DIR');
if (!$directory || !is_dir($directory)) throw new RuntimeException('Missing local data directory');
$db = new PDO('sqlite:'.$directory.'/pulse.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON; CREATE TABLE IF NOT EXISTS pulse_users(id INTEGER PRIMARY KEY AUTOINCREMENT,display_name TEXT UNIQUE NOT NULL,email TEXT UNIQUE NOT NULL,password_hash TEXT NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP); CREATE TABLE IF NOT EXISTS pulse_rate_limits(rate_key TEXT,bucket INTEGER,hits INTEGER,PRIMARY KEY(rate_key,bucket)); CREATE TABLE IF NOT EXISTS pulse_content(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,title TEXT,description TEXT,genre TEXT,bpm INTEGER,audio_file TEXT,audio_mime TEXT,cover_file TEXT,cover_mime TEXT,bytes INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP); CREATE TABLE IF NOT EXISTS pulse_maps(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,title TEXT NOT NULL,artist TEXT NOT NULL,creator TEXT NOT NULL,difficulty TEXT NOT NULL,bpm INTEGER NOT NULL,objects INTEGER NOT NULL,difficulties INTEGER NOT NULL,package_file TEXT NOT NULL,bytes INTEGER NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES pulse_users(id));');
$port = (int)getenv('PULSE_LOCAL_PORT');
$config=['origin'=>'http://127.0.0.1:'.$port,'dsn'=>'sqlite:'.$directory.'/pulse.sqlite','app_key'=>bin2hex(random_bytes(32)),'storage_path'=>$directory.'/storage'];
if(!is_dir($config['storage_path']))mkdir($config['storage_path'],0700,true);
if(!is_dir($directory.'/sessions'))mkdir($directory.'/sessions',0700,true);
if(!is_file($directory.'/pulse-config.php'))file_put_contents($directory.'/pulse-config.php','<?php return '.var_export($config,true).';');
// One explicitly labelled, original synthesized example for the local catalog.
if(is_file(__DIR__.'/examples/example.pls') && !is_file($directory.'/example-seeded')) {
    $db->beginTransaction();
    try {
        $q=$db->prepare('INSERT INTO pulse_users(display_name,email,password_hash) VALUES(?,?,?)');
        $q->execute(['Pulse Demo','demo@pulse.invalid',password_hash(bin2hex(random_bytes(32)),PASSWORD_BCRYPT)]); $user=(int)$db->lastInsertId();
        $package=bin2hex(random_bytes(24)).'.pls'; copy(__DIR__.'/examples/example.pls',$config['storage_path'].'/'.$package);
        $q=$db->prepare('INSERT INTO pulse_maps(user_id,title,artist,creator,difficulty,bpm,objects,difficulties,package_file,bytes) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$user,'Primer mapa de Pulse','Pulse Demo','Noon Interactive','Normal',120,3,1,$package,filesize(__DIR__.'/examples/example.pls')]);
        $db->commit(); file_put_contents($directory.'/example-seeded','1');
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
}
echo "Local database ready.\n";
