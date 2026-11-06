<?php
declare(strict_types=1);
final class BackupService {
  private const TABLES=['cms_users','cms_content','cms_revisions','cms_media','cms_settings'];
  public static function create(): string {
    if(!class_exists(ZipArchive::class))throw new RuntimeException('PHP ZipArchive is required for backup.');
    $dir=SMAYO_ROOT.'/storage/backups';if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Backup folder is not writable.');
    $tables=[];foreach(self::TABLES as $table)$tables[$table]=db()->query('SELECT * FROM '.$table)->fetchAll();
    $name='smayo-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.zip';$zip=new ZipArchive();if($zip->open($dir.'/'.$name,ZipArchive::CREATE)!==true)throw new RuntimeException('Could not create backup archive.');
    try{$zip->addFromString('backup.json',json_encode(['format'=>1,'created_at'=>date(DATE_ATOM),'tables'=>$tables],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));foreach($tables['cms_media'] as $asset){$f=SMAYO_UPLOADS.'/'.$asset['filename'];if(!is_file($f))throw new RuntimeException('Media file is missing: '.$asset['filename']);$zip->addFile($f,'uploads/'.$asset['filename']);}}catch(Throwable $e){$zip->close();@unlink($dir.'/'.$name);throw $e;}$zip->close();
    return $name;
  }
  public static function restore(array $upload): void {
    if(!class_exists(ZipArchive::class))throw new RuntimeException('PHP ZipArchive is required for restore.');if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??''))||(int)($upload['size']??0)>100000000)throw new RuntimeException('Choose a valid backup archive smaller than 100 MB.');
    $zip=new ZipArchive();if($zip->open($upload['tmp_name'])!==true)throw new RuntimeException('Backup archive is invalid.');
    try{$meta=$zip->statName('backup.json');if(!$meta||$meta['size']>80000000)throw new RuntimeException('Backup content is missing or too large.');$raw=$zip->getFromName('backup.json');if(!is_string($raw))throw new RuntimeException('Backup content is missing.');$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(($data['format']??null)!==1||!is_array($data['tables']??null))throw new RuntimeException('Backup format is unsupported.');foreach(self::TABLES as $t)if(!is_array($data['tables'][$t]??null))throw new RuntimeException('Backup is missing '.$t.'.');
      $total=0;foreach($data['tables']['cms_media'] as $asset){$filename=(string)($asset['filename']??'');if(!preg_match('/^[a-f0-9]{36}\.(jpg|png|webp|gif)$/',$filename))throw new RuntimeException('Backup contains an unsafe media filename.');$entry=$zip->statName('uploads/'.$filename);if(!$entry||$entry['size']>SMAYO_MAX_UPLOAD)throw new RuntimeException('Backup is missing or contains an oversized media file.');$total+=(int)$entry['size'];if($total>500000000)throw new RuntimeException('Backup media exceeds the restore size limit.');}
      self::create();$pdo=db();$pdo->beginTransaction();try{foreach(array_reverse(self::TABLES) as $t)$pdo->exec('DELETE FROM '.$t);foreach(self::TABLES as $table){foreach($data['tables'][$table] as $row){if(!is_array($row))throw new RuntimeException('Backup row is invalid.');$cols=array_keys($row);$safeCols=array_filter($cols,static fn($c)=>preg_match('/^[a-z_]+$/',(string)$c));if(count($safeCols)!==count($cols)||!$cols)throw new RuntimeException('Backup columns are invalid.');$sql='INSERT INTO '.$table.'('.implode(',',$cols).') VALUES('.implode(',',array_map(static fn($c)=>':'.$c,$cols)).')';$pdo->prepare($sql)->execute($row);}}$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
      if(!is_dir(SMAYO_UPLOADS))mkdir(SMAYO_UPLOADS,0755,true);foreach($data['tables']['cms_media'] as $asset){$bytes=$zip->getFromName('uploads/'.$asset['filename']);if(!is_string($bytes)||file_put_contents(SMAYO_UPLOADS.'/'.$asset['filename'],$bytes,LOCK_EX)===false)throw new RuntimeException('Could not restore uploaded image files.');}
    }finally{$zip->close();}
  }
}
