<?php
declare(strict_types=1);
$privateRoot=dirname(__DIR__).'/smayo_private';
require $privateRoot.'/app/bootstrap.php';
$installed=is_file(SMAYO_ROOT.'/storage/installed.lock');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'&&!$installed){
  try{
    require_csrf();
    $host=trim((string)($_POST['host']??''));$database=trim((string)($_POST['database']??''));$username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
    $name=trim((string)($_POST['name']??''));$email=filter_var((string)($_POST['email']??''),FILTER_VALIDATE_EMAIL);$adminPassword=(string)($_POST['admin_password']??'');
    if(!$host||!$database||!$username||!$email||mb_strlen($name)<2||strlen($adminPassword)<12)throw new RuntimeException('Complete every field. The administrator password must contain at least 12 characters.');
    $pdo=new PDO('mysql:host='.$host.';dbname='.$database.';charset=utf8mb4',$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    if(!is_writable(SMAYO_ROOT.'/config')||!is_writable(SMAYO_ROOT.'/storage'))throw new RuntimeException('The config and storage folders must be writable during installation.');
    $schema=file_get_contents(SMAYO_ROOT.'/database/schema.sql');if($schema===false)throw new RuntimeException('Database schema file is missing.');
    foreach(array_filter(array_map('trim',explode(';',$schema))) as $statement)$pdo->exec($statement);
    $pdo->beginTransaction();
    try {
      $insert=$pdo->prepare('INSERT INTO cms_users(name,email,password_hash,role) VALUES(:name,:email,:hash,\'admin\')');$insert->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash($adminPassword,PASSWORD_DEFAULT)]);
      $seed=default_content();
      foreach($seed as $key=>$value){$documentKey=$key==='settings'?'site':$key;$document=match($key){'settings'=>['settings'=>$value],'design'=>['design'=>$value],'sections'=>['sections'=>$value],default=>$value};$json=json_encode($document,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$q=$pdo->prepare('INSERT INTO cms_content(document_key,published_json,revision) VALUES(:key,:payload,1)');$q->execute(['key'=>$documentKey,'payload'=>$json]);$q=$pdo->prepare("INSERT INTO cms_revisions(document_key,revision,state,payload_json,created_by) VALUES(:key,1,'published',:payload,1)");$q->execute(['key'=>$documentKey,'payload'=>$json]);}
      $config=['host'=>$host,'database'=>$database,'username'=>$username,'password'=>$password,'charset'=>'utf8mb4'];
      $configPath=SMAYO_ROOT.'/config/database.php';if(file_put_contents($configPath,"<?php\nreturn ".var_export($config,true).";\n",LOCK_EX)===false)throw new RuntimeException('Cannot write config/database.php. Check folder permissions.');
      @chmod($configPath,0600);$pdo->commit();
    }catch(Throwable $installError){if($pdo->inTransaction())$pdo->rollBack();throw $installError;}
    if(file_put_contents(SMAYO_ROOT.'/storage/installed.lock',date(DATE_ATOM),LOCK_EX)===false)throw new RuntimeException('Cannot write the installation lock.');
    flash('Installation finished. Sign in with the administrator account you created.');redirect('../admin/?page=login');
  }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install SMayo</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body class="install"><main class="card"><span class="brand">SMayo <small>INSTALLER</small></span><h1>Set up your website</h1><?php if($installed):?><p>SMayo is already installed. The installer is locked.</p><?php else:?><p>Enter your hosting database details and create the first administrator account.</p><?php if($error):?><p class="alert"><?=e($error)?></p><?php endif;?><form method="post" class="form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><h2>Database</h2><label>Database host<input name="host" value="localhost" required></label><label>Database name<input name="database" required></label><label>Database username<input name="username" required></label><label>Database password<input name="password" type="password"></label><h2>Administrator</h2><label>Your name<input name="name" required></label><label>Email<input name="email" type="email" required></label><label>Password (12 characters minimum)<input name="admin_password" type="password" minlength="12" required></label><button class="button">Install SMayo</button></form><?php endif;?></main></body></html>
