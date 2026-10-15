<?php
// Included after session, CSRF and database initialization in pulse.php.
function mapPackage(string $file): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('Enable the PHP zip extension to publish maps.');
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) throw new RuntimeException('The file is not a valid .pls package.');
    try {
        if ($zip->numFiles > 256) throw new RuntimeException('The package contains too many files.');
        $total = 0; $files = []; $charts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i); $name = str_replace('\\', '/', $stat['name']);
            if ($name === '' || str_starts_with($name, '/') || str_contains($name, ':') || preg_match('~(?:^|/)\.\.(?:/|$)~', $name) || str_contains($name, "\0")) throw new RuntimeException('The package contains invalid paths.');
            $total += $stat['size'];
            if ($total > 150 * 1024 * 1024 || $stat['size'] > 35 * 1024 * 1024) throw new RuntimeException('The uncompressed package exceeds the size limits.');
            if (str_ends_with($name, '/')) continue;
            $key = strtolower($name); if (isset($files[$key])) throw new RuntimeException('The package contains duplicate filenames.');
            $files[$key] = true;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['osu','mp3','ogg','wav','jpg','jpeg','png','webp'], true)) throw new RuntimeException('Include only maps, audio, and cover art in the package.');
            if ($ext === 'osu') {
                if ($stat['size'] > 2 * 1024 * 1024) throw new RuntimeException('A map exceeds 2 MB.');
                $charts[] = [$name, $zip->getFromIndex($i)];
            }
        }
        if (!$charts) throw new RuntimeException('The package does not contain any .osu maps.');
        $first = null;
        foreach ($charts as [$name, $text]) {
            if (!is_string($text) || !str_starts_with(ltrim($text, "\xEF\xBB\xBF \r\n"), 'osu file format')) throw new RuntimeException('Invalid .osu file.');
            $section = ''; $values = []; $objects = 0; $bpm = 120;
            foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
                $line = trim($line); if ($line === '' || str_starts_with($line, '//')) continue;
                if (str_starts_with($line, '[')) { $section = $line; continue; }
                if (in_array($section, ['[General]', '[Metadata]'], true) && str_contains($line, ':')) { [$k,$v] = explode(':', $line, 2); $values[$k] = trim($v); }
                if ($section === '[HitObjects]') { $p = explode(',', $line); if(count($p) < 5 || !is_numeric($p[0]) || !is_numeric($p[1]) || !is_numeric($p[2]) || (float)$p[0] < 0 || (float)$p[0] > 512 || (float)$p[1] < 0 || (float)$p[1] > 384 || (float)$p[2] < 0 || (float)$p[2] > 3600000) throw new RuntimeException('The map contains invalid objects.'); $objects++; }
                if ($section === '[TimingPoints]') { $p = explode(',', $line); if(isset($p[1]) && is_numeric($p[1]) && (float)$p[1] > 0) $bpm = (int)round(60000 / (float)$p[1]); }
            }
            if (($values['Mode'] ?? '0') !== '0' || $objects < 1 || $objects > 10000) throw new RuntimeException('Use standard Pulse maps with 1–10000 objects.');
            $audio = str_replace('\\', '/', $values['AudioFilename'] ?? '');
            if ($audio === '' || str_starts_with($audio,'/') || str_contains($audio,':') || preg_match('~(?:^|/)\.\.(?:/|$)~',$audio) || !in_array(strtolower(pathinfo($audio,PATHINFO_EXTENSION)),['mp3','ogg','wav'],true)) throw new RuntimeException('The map requires valid audio inside the package.');
            $prefix = dirname($name); $audioKey = strtolower(($prefix === '.' ? '' : $prefix.'/').$audio);
            if (!isset($files[$audioKey])) throw new RuntimeException('The package is missing the map audio.');
            foreach (['Title'=>120,'Artist'=>120,'Creator'=>80,'Version'=>40] as $key=>$limit) if (empty($values[$key]) || mb_strlen($values[$key]) > $limit) throw new RuntimeException('Fill in the title, artist, creator, and difficulty in Map Editor.');
            $first ??= ['title'=>$values['Title'],'artist'=>$values['Artist'],'creator'=>$values['Creator'],'difficulty'=>$values['Version'],'bpm'=>$bpm,'objects'=>$objects];
        }
        return $first + ['difficulties'=>count($charts)];
    } finally { $zip->close(); }
}
if ($action === 'maps' && $method === 'GET') {
    $before = filter_var($_GET['before'] ?? '0', FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
    if ($before === false) fail(422,'Invalid page.');
    $query = trim((string)($_GET['q'] ?? '')); if(mb_strlen($query)>120) fail(422,'The search query is too long.');
    $where = []; $params = [];
    if($before) { $where[]='m.id < ?'; $params[]=$before; }
    if($query !== '') { $where[]='(m.title LIKE ? OR m.artist LIKE ? OR m.creator LIKE ?)'; array_push($params,'%'.$query.'%','%'.$query.'%','%'.$query.'%'); }
    $rows = statement('SELECT m.id,m.user_id,m.title,m.artist,m.creator,m.difficulty,m.bpm,m.objects,m.difficulties,m.bytes,m.created_at,u.display_name FROM pulse_maps m JOIN pulse_users u ON u.id=m.user_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY m.id DESC LIMIT 13',$params)->fetchAll();
    $u=user(); $more=count($rows)>12; $rows=array_slice($rows,0,12);
    foreach($rows as &$row) { $row['canDelete']=$u && (int)$row['user_id']===$u['id']; unset($row['user_id']); } unset($row);
    reply(200,['maps'=>$rows,'nextCursor'=>$more?(int)end($rows)['id']:null]);
}
if ($action === 'download_map' && $method === 'GET') {
    $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if(!$id) fail(404,'Map not found.');
    $row=statement('SELECT package_file FROM pulse_maps WHERE id=?',[$id])->fetch();
    if(!$row || !preg_match('/^[a-f0-9]{48}\.(?:pls|osz)$/D',$row['package_file'])) fail(404,'Map not found.');
    $file=storage().DIRECTORY_SEPARATOR.$row['package_file']; if(!is_file($file)) fail(404,'File not found.');
    session_write_close(); header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="pulse-map-'.$id.'.pls"'); header('Content-Length: '.filesize($file)); readfile($file); exit;
}
if ($action === 'upload_map' && $method === 'POST') {
    $u=signedIn(); throttle('upload_user',6,3600,(string)$u['id']); throttle('upload_ip',12,3600);
    if(($input['rights']??'')!=='yes') fail(422,'Confirm that you may distribute the map, its audio, and its cover art.');
    $f=$_FILES['package']??null;
    if(!$f || ($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_string($f['tmp_name']??null) || !is_uploaded_file($f['tmp_name'])) fail(422,'Select the .pls package exported from Map Editor.');
    $size=filesize($f['tmp_name']); if($size<1 || $size>50*1024*1024) fail(422,'The package must be no larger than 50 MB.');
    try { $meta=mapPackage($f['tmp_name']); } catch(RuntimeException $e) { fail(422,$e->getMessage()); }
    $dir=storage(); $name=bin2hex(random_bytes(24)).'.pls'; $path=$dir.DIRECTORY_SEPARATOR.$name;
    try {
        $db->beginTransaction(); statement('SELECT id FROM pulse_users WHERE id=?'.($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$u['id']]);
        $used=(int)statement('SELECT COALESCE(SUM(bytes),0) FROM pulse_maps WHERE user_id=?',[$u['id']])->fetchColumn()+(int)statement('SELECT COALESCE(SUM(bytes),0) FROM pulse_content WHERE user_id=?',[$u['id']])->fetchColumn();
        if($used+$size>250*1024*1024) throw new RuntimeException('Your account has reached its 250 MB limit.');
        if(!move_uploaded_file($f['tmp_name'],$path)) throw new RuntimeException('Could not save the package.'); chmod($path,0600);
        statement('INSERT INTO pulse_maps(user_id,title,artist,creator,difficulty,bpm,objects,difficulties,package_file,bytes) VALUES(?,?,?,?,?,?,?,?,?,?)',[$u['id'],$meta['title'],$meta['artist'],$meta['creator'],$meta['difficulty'],$meta['bpm'],$meta['objects'],$meta['difficulties'],$name,$size]);
        $id=(int)$db->lastInsertId(); $db->commit();
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); if(is_file($path))unlink($path); if($e instanceof RuntimeException && !($e instanceof PDOException))fail(422,$e->getMessage()); throw $e; }
    reply(201,['id'=>$id,'message'=>'Map published. It can now be downloaded and imported into Unity.']);
}
if ($action === 'delete_map' && $method === 'POST') {
    $u=signedIn(); $id=filter_var($input['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if(!$id)fail(422,'Invalid map.');
    $row=statement('SELECT package_file FROM pulse_maps WHERE id=? AND user_id=?',[$id,$u['id']])->fetch(); if(!$row)fail(404,'No matching map owned by you was found.');
    $dir=storage(); statement('DELETE FROM pulse_maps WHERE id=? AND user_id=?',[$id,$u['id']]); if(preg_match('/^[a-f0-9]{48}\.(?:pls|osz)$/D',$row['package_file']))@unlink($dir.DIRECTORY_SEPARATOR.$row['package_file']); reply(200,['deleted'=>true]);
}
