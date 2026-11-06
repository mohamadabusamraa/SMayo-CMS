<?php
declare(strict_types=1);

final class ContentValidator {
  private const TYPES=['hero','about','services','features','statistics','testimonials','gallery','cta','contact'];

  public static function sections(array $payload): void {
    if(!is_array($payload['sections']??null)||!array_is_list($payload['sections'])||count($payload['sections'])>40)throw new RuntimeException('Sections must be an ordered list of no more than 40 items.');
    $ids=[];
    foreach($payload['sections'] as $i=>$section){
      if(!is_array($section)||!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,59}$/',(string)($section['id']??''))||isset($ids[$section['id']]))throw new RuntimeException('Section identifiers must be unique and valid.');
      $ids[$section['id']]=true;
      if(!in_array($section['type']??null,self::TYPES,true)||!is_bool($section['visible']??null)||!is_array($section['content']??null))throw new RuntimeException('Section '.($i+1).' has invalid settings.');

      $content=$section['content'];
      $stringFields=match($section['type']){
        'hero'=>['eyebrow','title','description','button_label','button_href','image_alt'],
        'about'=>['eyebrow','title','body','image_alt'],
        'services'=>['eyebrow','title','description'],
        'features'=>['eyebrow','title'],
        'statistics'=>['title'],
        'testimonials'=>['eyebrow','title'],
        'gallery'=>['eyebrow','title'],
        'cta'=>['eyebrow','title','description','button_label','button_href'],
        'contact'=>['eyebrow','title','description','email','phone','location','form_title','button_label','button_href'],
        default=>[],
      };
      foreach($stringFields as $key)if(isset($content[$key])&&!is_string($content[$key]))throw new RuntimeException('Section text fields must be text.');
      foreach(['image_id'] as $key)if(array_key_exists($key,$content))self::imageId($content[$key]);

      $itemFields=match($section['type']){
        'services','features','gallery'=>['title','description','image_alt'],
        'statistics'=>['value','label'],
        'testimonials'=>['quote','name','role'],
        default=>[],
      };
      if(in_array($section['type'],['services','features','statistics','testimonials','gallery'],true)){
        if(!is_array($content['items']??null)||!array_is_list($content['items'])||count($content['items'])>100)throw new RuntimeException('Repeated items must be an ordered list.');
        foreach($content['items'] as $item){
          if(!is_array($item))throw new RuntimeException('Each repeated item must be an object.');
          foreach($item as $key=>$value){
            if($key==='image_id'){self::imageId($value);continue;}
            if($key==='translations')continue;
            if(!is_string($value))throw new RuntimeException('Repeated item fields must be text.');
          }
          self::translations($item['translations']??null,$itemFields,'Repeated item');
        }
      }
      self::sectionTranslations($section['translations']??null,$stringFields,$itemFields);
    }
  }

  private static function sectionTranslations(mixed $value,array $fields,array $itemFields): void {
    if($value===null)return;
    if(!is_array($value))throw new RuntimeException('Section translations must be an object.');
    foreach($value as $locale=>$translation){
      if($locale!=='ar')throw new RuntimeException('Only Arabic translations are supported here.');
      if(!is_array($translation))throw new RuntimeException('Arabic section translations must be an object.');
      $direct=$translation;unset($direct['items']);
      self::localizedFields($direct,$fields,'Arabic section');
      if(array_key_exists('items',$translation)){
        $items=$translation['items'];
        if(!is_array($items)||!array_is_list($items)||count($items)>100)throw new RuntimeException('Arabic repeated-item translations must be an ordered list.');
        foreach($items as $item)if(!is_array($item))throw new RuntimeException('Each Arabic item translation must be an object.');else self::localizedFields($item,$itemFields,'Arabic item');
      }
    }
  }

  private static function translations(mixed $value,array $fields,string $context): void {
    if($value===null)return;
    if(!is_array($value))throw new RuntimeException($context.' translations must be an object.');
    foreach($value as $locale=>$translation){
      if($locale!=='ar')throw new RuntimeException('Only Arabic translations are supported here.');
      if(!is_array($translation))throw new RuntimeException('Arabic '.$context.' translations must be an object.');
      self::localizedFields($translation,$fields,'Arabic '.$context);
    }
  }

  private static function localizedFields(array $values,array $allowed,string $context): void {
    foreach($values as $key=>$value){
      if(!in_array($key,$allowed,true)||!is_string($value))throw new RuntimeException($context.' translations contain an unsupported or non-text field.');
    }
  }

  private static function imageId(mixed $value): void {
    if($value===null||$value==='')return;
    if(!is_int($value)||!media_by_id($value))throw new RuntimeException('Choose an active image from the media library.');
  }

  public static function settings(array $payload): void {
    $settings=$payload['settings']??null;
    if(!is_array($settings))throw new RuntimeException('Website settings are invalid.');
    foreach(['brand','tagline','email','phone','location','footer'] as $key)if(!is_string($settings[$key]??null))throw new RuntimeException('Website setting '.$key.' must be text.');
    if(!is_array($settings['navigation']??null)||!array_is_list($settings['navigation']))throw new RuntimeException('Navigation must be a list.');
    foreach($settings['navigation'] as $link)if(!is_array($link)||!is_string($link['label']??null)||!is_string($link['href']??null))throw new RuntimeException('Navigation links need a label and destination.');
    if(!is_array($settings['social_links']??null)||!array_is_list($settings['social_links']))throw new RuntimeException('Social links must be a list.');
    foreach($settings['social_links'] as $link)if(!is_array($link)||!is_string($link['label']??null)||!is_string($link['href']??null))throw new RuntimeException('Social links need a label and destination.');
    if(array_key_exists('logo_media_id',$settings))self::imageId($settings['logo_media_id']);
    if(array_key_exists('favicon_media_id',$settings))self::imageId($settings['favicon_media_id']);
    self::settingsTranslations($settings['translations']??null);
  }

  private static function settingsTranslations(mixed $value): void {
    if($value===null)return;
    if(!is_array($value))throw new RuntimeException('Website translations must be an object.');
    $textFields=['brand','tagline','email','phone','location','footer'];
    foreach($value as $locale=>$translation){
      if($locale!=='ar')throw new RuntimeException('Only Arabic translations are supported here.');
      if(!is_array($translation))throw new RuntimeException('Arabic website translations must be an object.');
      foreach($translation as $key=>$translated){
        if(in_array($key,$textFields,true)){if(!is_string($translated))throw new RuntimeException('Arabic website settings must be text.');continue;}
        if(!in_array($key,['navigation','social_links'],true))throw new RuntimeException('Arabic website translations contain an unsupported field.');
        if(!is_array($translated)||!array_is_list($translated)||count($translated)>100)throw new RuntimeException('Arabic website links must be an ordered list.');
        foreach($translated as $link){
          if(!is_array($link))throw new RuntimeException('Each Arabic website link translation must be an object.');
          foreach($link as $linkKey=>$linkValue)if(!in_array($linkKey,['label','href'],true)||!is_string($linkValue))throw new RuntimeException('Arabic website link translations must contain text labels or destinations.');
        }
      }
    }
  }

  public static function design(array $payload): void {
    $design=$payload['design']??null;
    if(!is_array($design))throw new RuntimeException('Design settings are invalid.');
    foreach(['primary','secondary','accent','background','surface','text','muted'] as $key)if(!preg_match('/^#[0-9a-fA-F]{6}$/',(string)($design[$key]??'')))throw new RuntimeException($key.' must be a six-digit color.');
    foreach(['base_size','radius','spacing'] as $key)if(!preg_match('/^\d+(\.\d+)?(px|rem|em|%)$/',(string)($design[$key]??'')))throw new RuntimeException($key.' must be a size with units.');
    foreach(['font','heading_font'] as $key)if(!preg_match('/^[-\w ,]{1,120}$/',(string)($design[$key]??'')))throw new RuntimeException($key.' contains unsupported characters.');
  }
}
