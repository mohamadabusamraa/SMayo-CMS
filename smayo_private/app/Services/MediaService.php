<?php
declare(strict_types=1);
final class MediaService {
  public static function upload(array $file,int $userId): int {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($file['tmp_name']??'')))throw new RuntimeException('Choose a valid image to upload.');
    $size=(int)$file['size'];if($size<1||$size>SMAYO_MAX_UPLOAD)throw new RuntimeException('Images must be smaller than 15 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if(!isset($types[$mime])||!getimagesize($file['tmp_name']))throw new RuntimeException('Only valid JPG, PNG, WebP, and GIF images are supported.');
    if(!is_dir(SMAYO_UPLOADS)&&!mkdir(SMAYO_UPLOADS,0755,true)&&!is_dir(SMAYO_UPLOADS))throw new RuntimeException('Upload folder is not writable.');
    $name=bin2hex(random_bytes(18)).'.'.$types[$mime];$destination=SMAYO_UPLOADS.'/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$destination))throw new RuntimeException('The image could not be stored.');
    try{$q=db()->prepare('INSERT INTO cms_media(filename,original_name,mime_type,file_size,uploaded_by) VALUES(:filename,:original,:mime,:size,:user)');$q->execute(['filename'=>$name,'original'=>mb_substr(basename((string)$file['name']),0,255),'mime'=>$mime,'size'=>$size,'user'=>$userId]);return(int)db()->lastInsertId();}
    catch(Throwable $e){@unlink($destination);throw $e;}
  }
}
