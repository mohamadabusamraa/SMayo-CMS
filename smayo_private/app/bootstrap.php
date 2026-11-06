<?php
declare(strict_types=1);

const SMAYO_ROOT = __DIR__ . '/..';
$documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
define('SMAYO_UPLOADS', ($documentRoot !== '' ? $documentRoot : SMAYO_ROOT . '/..') . '/uploads');
const SMAYO_MAX_UPLOAD = 15728640;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('smayo_session');
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax','path'=>'/']);
    session_start();
}

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: '.$path, true, 303); exit; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function require_csrf(): void {
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('Your session expired. Reload the page and try again.');
    }
}
function flash(string $message, string $type='success'): void { $_SESSION['flash']=['message'=>$message,'type'=>$type]; }
function take_flash(): ?array { $value=$_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $value; }
function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $file=SMAYO_ROOT.'/config/database.php';
    if (!is_file($file) || !is_file(SMAYO_ROOT.'/storage/installed.lock')) throw new RuntimeException('SMayo is not installed.');
    $cfg=require $file;
    $dsn=sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$cfg['host'],$cfg['database']);
    $pdo=new PDO($dsn,(string)$cfg['username'],(string)$cfg['password'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    return $pdo;
}
function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    $query=db()->prepare('SELECT id,name,email,role,is_active FROM cms_users WHERE id=:id LIMIT 1');
    $query->execute(['id'=>(int)$_SESSION['user_id']]); $user=$query->fetch();
    if (!$user || !(int)$user['is_active']) { unset($_SESSION['user_id']); return null; }
    return $user;
}
function require_admin(): array { $user=current_user(); if (!$user) redirect('./?page=login'); return $user; }
function json_decode_array(string $raw): array {
    $value=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('Content must be a JSON object.');
    return $value;
}
function requested_locale(): string {
    $path=(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)??'/');
    if(preg_match('#(?:^|/)(en|ar)/?$#i',$path,$match))return strtolower($match[1]);
    $locale=strtolower(trim((string)($_GET['__locale']??$_GET['lang']??'en')));
    return in_array($locale,['en','ar'],true)?$locale:'en';
}
function configured_site_url(): ?array {
    $value=trim((string)(getenv('SMAYO_SITE_URL')?:''));
    $file=SMAYO_ROOT.'/config/site.php';
    if($value===''&&is_file($file)){try{$config=require $file;$value=is_array($config)?trim((string)($config['site_url']??'')):'';}catch(Throwable){$value='';}}
    if($value==='')return null;
    $parts=parse_url($value);
    if(!is_array($parts)||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))return null;
    $host=(string)$parts['host'];
    if(!preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[0-9a-f:.]+\])$/iD',$host))return null;
    $port=isset($parts['port'])?(int)$parts['port']:null;
    if($port!==null&&($port<1||$port>65535))return null;
    $origin=strtolower((string)$parts['scheme']).'://'.$host.($port!==null?':'.$port:'');
    $path=trim((string)($parts['path']??''));
    if(preg_match('/[\x00-\x20?#]/',$path))return null;
    $path=$path===''?'':'/'.trim($path,'/');
    return ['origin'=>$origin,'path'=>$path,'base'=>$origin.$path];
}
function site_base_path(): string {
    $configured=configured_site_url();
    if($configured!==null)return $configured['path'];
    $path=(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)??'/');
    if(preg_match('#^(.*?)/(?:en|ar)/?$#i',$path,$match))return rtrim($match[1],'/');
    $script=(string)($_SERVER['SCRIPT_NAME']??'/index.php');
    $directory=str_replace('\\','/',dirname($script));
    return $directory==='/'||$directory==='.'?'':rtrim($directory,'/');
}
function site_public_url(string $path=''): string {
    return rtrim(site_base_path(),'/').'/'.ltrim($path,'/');
}
function request_origin(): string {
    $scheme=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||((int)($_SERVER['SERVER_PORT']??0)===443)?'https':'http';
    $configured=configured_site_url();
    if($configured!==null)return $configured['origin'];
    $request=parse_url($scheme.'://'.(string)($_SERVER['HTTP_HOST']??''));
    if(!is_array($request)||empty($request['host']))return '';
    $host=(string)$request['host'];
    if(!preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[0-9a-f:.]+\])$/iD',$host))return '';
    $port=isset($request['port'])?(int)$request['port']:null;
    if($port!==null&&($port<1||$port>65535))return '';
    return $scheme.'://'.$host.($port!==null?':'.$port:'');
}
function canonical_site_base(): string {
    $configured=configured_site_url();
    return $configured!==null?$configured['base']:request_origin().site_base_path();
}
function canonical_locale_url(string $locale): string {
    if(!in_array($locale,['en','ar'],true))$locale='en';
    return rtrim(canonical_site_base(),'/').'/'.$locale.'/';
}
function localized_page_url(string $locale): string {
    if(!in_array($locale,['en','ar'],true))$locale='en';
    $query=$_GET;unset($query['lang'],$query['__locale']);
    $queryString=http_build_query($query);
    return site_public_url($locale.'/').($queryString!==''?'?'.$queryString:'');
}
function localized_content(mixed $content,string $locale,mixed $translation=null): mixed {
    if($locale!=='ar')$translation=null;
    if(!is_array($content))return $locale==='ar'&&is_string($content)&&is_string($translation)&&trim($translation)!==''?$translation:$content;
    $embedded=$locale==='ar'?($content['translations']['ar']??null):null;
    unset($content['translations']);
    $overrides=is_array($translation)?$translation:[];
    if($locale==='ar'&&is_array($embedded))$overrides=array_replace_recursive($embedded,$overrides);
    foreach($content as $key=>$value)$content[$key]=localized_content($value,$locale,$overrides[$key]??null);
    return $content;
}
function default_content(): array {
    return [
      'settings'=>['brand'=>'SMayo','logo_media_id'=>null,'favicon_media_id'=>null,'tagline'=>'Intelligent IT services and software solutions for businesses ready to move forward.','email'=>'','phone'=>'','location'=>'','navigation'=>[['label'=>'Home','href'=>'#top'],['label'=>'About','href'=>'#about'],['label'=>'Services','href'=>'#services'],['label'=>'Solutions','href'=>'#solutions'],['label'=>'Contact','href'=>'#contact']],'social_links'=>[],'footer'=>'IT solutions, website development, technology consulting, and digital services designed around your business needs.'],
      'design'=>['primary'=>'#2563eb','secondary'=>'#0d1424','accent'=>'#38bdf8','background'=>'#080b14','surface'=>'#111b2e','text'=>'#f8fafc','muted'=>'#94a3b8','font'=>'Inter, Manrope, Segoe UI, Arial, sans-serif','heading_font'=>'Georgia, Times New Roman, serif','base_size'=>'15px','radius'=>'12px','spacing'=>'96px'],
      'sections'=>[
        ['id'=>'hero','type'=>'hero','visible'=>true,'content'=>['eyebrow'=>'SMAYO / SOFTWARE & TECHNOLOGY','title'=>'Practical Technology. Stronger Digital Presence.','description'=>'SMayo helps businesses move forward through practical IT solutions, professional website development, technology consulting, social media management, and strategic marketing planning.','button_label'=>"Let's Talk About Your Project",'button_href'=>'#contact','image_id'=>null,'image_alt'=>'']],
        ['id'=>'about','type'=>'about','visible'=>true,'content'=>['eyebrow'=>'A clearer way forward / 01','title'=>'Technology and Digital Solutions, Built Around Your Business.','body'=>"At SMayo, we believe technology should make business simpler, stronger, and more effective.\n\nWe bring together IT solutions, website development, technology consulting, social media management, and marketing planning to help businesses navigate their digital needs with clarity and confidence.\n\nOur approach starts with understanding each client's goals and challenges. From there, we focus on practical solutions, clear communication, and work designed around real business needs.\n\nYour goals guide our work. Our role is to help you move forward.",'image_id'=>null,'image_alt'=>'']],
        ['id'=>'services','type'=>'services','visible'=>true,'content'=>['eyebrow'=>'','title'=>'Our Services','description'=>'From technology solutions to digital growth, we provide the expertise and support businesses need to build, manage, and strengthen their digital presence.','items'=>[['title'=>'IT Solutions','description'=>'Reliable technology solutions designed to support your business operations, improve efficiency, and address your everyday IT needs.','image_id'=>null,'image_alt'=>''],['title'=>'Website Design & Development','description'=>'Professional, responsive websites that present your business clearly, deliver a smooth user experience, and help you reach your audience online.','image_id'=>null,'image_alt'=>''],['title'=>'Technology Consulting','description'=>'Practical technology advice to help you evaluate your options, make informed decisions, and choose solutions that fit your business goals.','image_id'=>null,'image_alt'=>''],['title'=>'Social Media Management','description'=>'Strategic management of your social media presence through content planning, page management, audience engagement, and consistent communication.','image_id'=>null,'image_alt'=>''],['title'=>'Marketing Strategy & Planning','description'=>'Structured marketing plans that define your objectives, identify your target audience, and guide your digital marketing activities.','image_id'=>null,'image_alt'=>'']]]],
        ['id'=>'solutions','type'=>'features','visible'=>true,'content'=>['eyebrow'=>'Software solutions','title'=>'Built Around Your Business.','items'=>[['title'=>'Custom business applications','description'=>'Operations flow'],['title'=>'Enterprise software','description'=>'Process clarity'],['title'=>'Workflow automation','description'=>'Connected performance'],['title'=>'Scalable digital platforms','description'=>'A platform that grows with you'],['title'=>'Intelligent system integration','description'=>'']]]],
        ['id'=>'why','type'=>'features','visible'=>true,'content'=>['eyebrow'=>'Why SMayo','title'=>'Why Businesses Choose SMayo.','items'=>[['title'=>'Strategic Technology Expertise','description'=>'We connect business priorities with sound technical decisions, so every step has a reason.'],['title'=>'Tailored Solutions','description'=>'Your context shapes the solution. We design for your processes, teams, and customers.'],['title'=>'Scalable Architecture','description'=>'Flexible foundations support today’s needs and the growth you are working toward.'],['title'=>'Reliable Execution','description'=>'Thoughtful planning and transparent communication keep delivery focused and dependable.'],['title'=>'Client-Centered Approach','description'=>'We work closely with your team, making collaboration part of the solution.'],['title'=>'Innovation-Driven Thinking','description'=>'We look for smarter paths forward and apply new ideas where they create real value.']]]],
        ['id'=>'technology','type'=>'features','visible'=>true,'content'=>['eyebrow'=>'Technology & expertise','title'=>'Powered by Modern Technology.','items'=>[['title'=>'Web technologies','description'=>'Interfaces / experiences'],['title'=>'Application engineering','description'=>'Software / systems'],['title'=>'Cloud infrastructure','description'=>'Flexible / resilient'],['title'=>'Software architecture','description'=>'Connected / considered'],['title'=>'Data systems','description'=>'Insight / integrity'],['title'=>'Automation tools','description'=>'Flow / efficiency']]]],
        ['id'=>'cta','type'=>'cta','visible'=>true,'content'=>['eyebrow'=>'','title'=>'Let’s Build Something Extraordinary.','description'=>'Have an idea, a challenge, or a vision for your business? Let’s turn it into a powerful digital solution.','button_label'=>'Start a Conversation','button_href'=>'#contact']],
        ['id'=>'contact','type'=>'contact','visible'=>true,'content'=>['eyebrow'=>'Contact','title'=>"Let's Start a Conversation.",'description'=>"Have a project in mind or need support with your business's technology and digital presence? Get in touch with SMayo. Let's discuss your goals and find the right way forward.",'email'=>'','phone'=>'','location'=>'','form_title'=>'','button_label'=>'Contact SMayo','button_href'=>'']],
      ],
    ];
}
function legacy_default_content(): array {
    return [
      'settings'=>['brand'=>'SMayo','logo_media_id'=>null,'tagline'=>'Technology shaped around your business.','email'=>'','phone'=>'','location'=>'','navigation'=>[['label'=>'About','href'=>'#about'],['label'=>'Services','href'=>'#services'],['label'=>'Contact','href'=>'#contact']],'social_links'=>[],'footer'=>'Built for what comes next.'],
      'design'=>['primary'=>'#4f7cff','secondary'=>'#10192b','accent'=>'#61d6e8','background'=>'#08111f','surface'=>'#101b2c','text'=>'#f4f7fb','muted'=>'#9aa9bf','font'=>'Arial, sans-serif','heading_font'=>'Arial, sans-serif','base_size'=>'16px','radius'=>'14px','spacing'=>'88px'],
      'sections'=>[
        ['id'=>'hero','type'=>'hero','visible'=>true,'content'=>['eyebrow'=>'SMayo','title'=>'Technology shaped around your business','description'=>'Add a clear introduction to your services and the value you bring.','button_label'=>'Explore services','button_href'=>'#services','image_id'=>null,'image_alt'=>'']],
        ['id'=>'about','type'=>'about','visible'=>true,'content'=>['eyebrow'=>'About','title'=>'A thoughtful partner for your next step','body'=>'Describe your approach, experience, and the way you work with clients.','image_id'=>null,'image_alt'=>'']],
        ['id'=>'services','type'=>'services','visible'=>true,'content'=>['eyebrow'=>'Services','title'=>'What we can help you build','items'=>[['title'=>'Technology strategy','description'=>'Add a concise description of this service.','image_id'=>null,'image_alt'=>''],['title'=>'Software development','description'=>'Add a concise description of this service.','image_id'=>null,'image_alt'=>''],['title'=>'Digital systems','description'=>'Add a concise description of this service.','image_id'=>null,'image_alt'=>'']]]],
        ['id'=>'features','type'=>'features','visible'=>true,'content'=>['eyebrow'=>'Why SMayo','title'=>'Practical thinking. Considered delivery.','items'=>[['title'=>'Clear direction','description'=>'Describe a benefit.'],['title'=>'Built to adapt','description'=>'Describe a benefit.'],['title'=>'Focused collaboration','description'=>'Describe a benefit.']]]],
        ['id'=>'statistics','type'=>'statistics','visible'=>true,'content'=>['title'=>'Make the value measurable','items'=>[['value'=>'—','label'=>'Add a statistic'],['value'=>'—','label'=>'Add a statistic'],['value'=>'—','label'=>'Add a statistic']]]],
        ['id'=>'testimonials','type'=>'testimonials','visible'=>true,'content'=>['eyebrow'=>'Testimonials','title'=>'What partners say','items'=>[['quote'=>'Add an approved client testimonial.','name'=>'','role'=>'']]]],
        ['id'=>'gallery','type'=>'gallery','visible'=>true,'content'=>['eyebrow'=>'Gallery','title'=>'Selected work','items'=>[]]],
        ['id'=>'cta','type'=>'cta','visible'=>true,'content'=>['eyebrow'=>'Next step','title'=>'Have a project in mind?','description'=>'Add a short invitation to start a conversation.','button_label'=>'Contact us','button_href'=>'#contact']],
        ['id'=>'contact','type'=>'contact','visible'=>true,'content'=>['eyebrow'=>'Contact','title'=>'Start a conversation','description'=>'Add your contact details in the Settings area.','email'=>'','phone'=>'','location'=>'','form_title'=>'Tell us about your project']],
      ],
    ];
}
function apply_seeded_marketing_update(array $document,string $key): array {
    $current=default_content();
    if($key==='site'){
        $settings=&$document['settings'];
        if(is_array($settings)&&($settings['footer']??null)==='Intelligent IT services and software solutions for businesses ready to move forward.')$settings['footer']=$current['settings']['footer'];
        return $document;
    }
    if($key!=='sections'||!is_array($document['sections']??null))return $document;
    $old=[
      'hero'=>['title'=>'Architecting the Digital Future','description'=>'We engineer bespoke software systems, cloud-native infrastructures, and transformative digital platforms for forward-thinking global enterprises.','button_label'=>'Start Your Project'],
      'about'=>['title'=>'Technology That Moves Business Forward.','body'=>'At SMayo, we combine technical expertise, strategic thinking, and creative innovation to deliver digital solutions that help businesses operate smarter and grow faster.\n\nWe make technology useful, understandable, and ready for the next stage of your business. Every engagement starts with your goals and takes shape around the people and systems that make your work possible.'],
      'services'=>['title'=>'Intelligent Solutions. Exceptional Execution.'],
      'contact'=>['title'=>'Let’s make your next move count.','description'=>'Tell us where you want to go and explore how technology can move your business forward.'],
    ];
    $oldServices=[['title'=>'IT Consulting & Strategy','description'=>'Turn complex technology choices into a clear, practical roadmap for your business.','image_id'=>null,'image_alt'=>''],['title'=>'Custom Software Development','description'=>'Purpose-built applications that fit your workflows and make room to grow.','image_id'=>null,'image_alt'=>''],['title'=>'Web Development','description'=>'Fast, accessible digital experiences designed to represent your business at its best.','image_id'=>null,'image_alt'=>''],['title'=>'Digital Transformation','description'=>'Modernize processes and bring the right tools together to move with confidence.','image_id'=>null,'image_alt'=>''],['title'=>'Business Automation','description'=>'Reduce repetitive work with thoughtful automation that gives teams time back.','image_id'=>null,'image_alt'=>''],['title'=>'Technology Infrastructure','description'=>'Dependable foundations, integrations, and cloud-ready systems built for continuity.','image_id'=>null,'image_alt'=>'']];
    foreach($document['sections'] as &$section){if(!is_array($section)||!isset($old[$section['id']??'']))continue;$id=(string)$section['id'];$content=is_array($section['content']??null)?$section['content']:[];foreach($old[$id] as $field=>$oldValue)if(($content[$field]??null)===$oldValue)$content[$field]=$current['sections'][array_search($id,array_column($current['sections'],'id'),true)]['content'][$field];if($id==='services'){if(($content['items']??null)===$oldServices)$content['items']=$current['sections'][2]['content']['items'];if(!array_key_exists('description',$content))$content['description']=$current['sections'][2]['content']['description'];}if($id==='contact'){if(!array_key_exists('button_label',$content))$content['button_label']='Contact SMayo';if(!array_key_exists('button_href',$content))$content['button_href']='';} $section['content']=$content;}
    unset($section);
    return $document;
}
function content_doc(string $key, bool $preview=false): array {
    $q=db()->prepare('SELECT published_json,draft_json,revision FROM cms_content WHERE document_key=:key'); $q->execute(['key'=>$key]); $row=$q->fetch();
    if (!$row) return default_content();
    $usingDraft=$preview && $row['draft_json']!==null;
    $json=$usingDraft ? $row['draft_json'] : $row['published_json'];
    $document=json_decode_array((string)$json);
    if ($key==='site' && !array_key_exists('settings',$document)) $document=['settings'=>$document];
    if ($key==='design' && !array_key_exists('design',$document)) $document=['design'=>$document];
    if ($key==='sections' && array_is_list($document)) $document=['sections'=>$document];
    if (!$usingDraft) {
        $legacy=legacy_default_content();
        $previous=match($key){'site'=>['settings'=>$legacy['settings']],'design'=>['design'=>$legacy['design']],'sections'=>['sections'=>$legacy['sections']],default=>null};
        $emptySource=((int)$row['revision']===1 || $row['draft_json']!==null) && (match($key){'site'=>empty($document['settings']??null),'design'=>empty($document['design']??null),'sections'=>empty($document['sections']??null),default=>false});
        if ($previous!==null && ($document===$previous || $emptySource)) {
            $current=default_content();
            return match($key){'site'=>['settings'=>$current['settings']],'design'=>['design'=>$current['design']],'sections'=>['sections'=>$current['sections']],default=>$document};
        }
    }
    if($preview&&!$usingDraft)$document=apply_seeded_marketing_update($document,$key);
    return $document;
}
function save_draft(string $key,array $payload,int $userId): void {
    $pdo=db();$pdo->beginTransaction();
    try {
      $q=$pdo->prepare('SELECT published_json,draft_json,revision FROM cms_content WHERE document_key=:key FOR UPDATE');$q->execute(['key'=>$key]);$row=$q->fetch();
      if(!$row)throw new RuntimeException('Content document is missing.');
      if($row['draft_json']!==null)$pdo->prepare("UPDATE cms_revisions SET state='archived' WHERE document_key=:key AND revision=:revision AND state='draft'")->execute(['key'=>$key,'revision'=>(int)$row['revision']]);
      $revision=(int)$row['revision']+1;
      $pdo->prepare('INSERT INTO cms_revisions(document_key,revision,state,payload_json,created_by) VALUES(:key,:revision,\'draft\',:payload,:user)')->execute(['key'=>$key,'revision'=>$revision,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'user'=>$userId]);
      $pdo->prepare('UPDATE cms_content SET draft_json=:payload,revision=:revision WHERE document_key=:key')->execute(['payload'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'revision'=>$revision,'key'=>$key]);
      $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function publish_draft(string $key,int $userId): void {
    $pdo=db();$pdo->beginTransaction();
    try{$q=$pdo->prepare('SELECT draft_json,revision FROM cms_content WHERE document_key=:key FOR UPDATE');$q->execute(['key'=>$key]);$row=$q->fetch();if(!$row||$row['draft_json']===null)throw new RuntimeException('There is no draft to publish.');
      $pdo->prepare('UPDATE cms_content SET published_json=draft_json,draft_json=NULL WHERE document_key=:key')->execute(['key'=>$key]);
      $pdo->prepare("UPDATE cms_revisions SET state='archived' WHERE document_key=:key AND state='published'")->execute(['key'=>$key]);
      $pdo->prepare("UPDATE cms_revisions SET state='published' WHERE document_key=:key AND revision=:revision AND state='draft'")->execute(['key'=>$key,'revision'=>$row['revision']]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function document_has_draft(string $key): bool { $q=db()->prepare('SELECT draft_json IS NOT NULL FROM cms_content WHERE document_key=:key');$q->execute(['key'=>$key]);return(bool)$q->fetchColumn(); }
function media_rows(bool $activeOnly=true): array { $sql='SELECT * FROM cms_media'.($activeOnly?' WHERE is_active=1':'').' ORDER BY created_at DESC';return db()->query($sql)->fetchAll(); }
function media_by_id(?int $id): ?array { if(!$id)return null;$q=db()->prepare('SELECT * FROM cms_media WHERE id=:id AND is_active=1');$q->execute(['id'=>$id]);$row=$q->fetch();return $row?:null; }
function media_in_use(int $id): bool {
    $q=db()->query('SELECT published_json,draft_json FROM cms_content');
    foreach($q as $row)foreach(['published_json','draft_json'] as $column){if($row[$column]!==null&&preg_match('/"(?:image_id|logo_media_id|favicon_media_id)"\s*:\s*'.preg_quote((string)$id,'/').'(?:\s|,|})/',(string)$row[$column]))return true;}
    return false;
}
