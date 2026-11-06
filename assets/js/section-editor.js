(()=>{
 const host=document.getElementById('sectionEditor'),dataNode=document.getElementById('sectionData'),form=document.getElementById('sectionsForm'),payloadInput=document.getElementById('sectionPayload');
 if(!host||!dataNode||!form)return;
 const state=JSON.parse(dataNode.textContent),sections=Array.isArray(state.sections)?state.sections:[],media=Array.isArray(state.media)?state.media:[];
 let selected=sections[0]?.id||null,group='content',fieldLocale='en',pickerTarget=null;
 const types={hero:'Hero',about:'About',services:'Services',features:'Features',statistics:'Statistics',testimonials:'Testimonials',gallery:'Gallery',cta:'Call to action',contact:'Contact'};
 const schemas={
  hero:{content:['eyebrow','title','description','button_label','button_href'],images:[['image_id','Hero image']]},
  about:{content:['eyebrow','title','body'],images:[['image_id','About image']]},
  services:{content:['eyebrow','title','description'],items:['title','description'],images:[]},
  features:{content:['eyebrow','title'],items:['title','description'],images:[]},
  statistics:{content:['title'],items:['value','label'],images:[]},
  testimonials:{content:['eyebrow','title'],items:['quote','name','role'],images:[]},
  gallery:{content:['eyebrow','title'],items:['title','description'],images:[]},
  cta:{content:['eyebrow','title','description','button_label','button_href'],images:[]},
  contact:{content:['eyebrow','title','description','email','phone','location','form_title','button_label','button_href'],images:[]}
 };
 const labels={eyebrow:'Small heading',title:'Heading',description:'Description',body:'Section text',button_label:'Button label',button_href:'Button link',email:'Email',phone:'Phone',location:'Location',form_title:'Form heading',quote:'Testimonial',name:'Name',role:'Role',value:'Value',label:'Label',image_alt:'Alternative text (Alt Text)'};
 const clone=x=>JSON.parse(JSON.stringify(x)),el=(tag,cls='',text='')=>{const n=document.createElement(tag);if(cls)n.className=cls;if(text)n.textContent=text;return n;};
 const btn=(label,cls,fn)=>{const b=el('button',cls,label);b.type='button';b.addEventListener('click',fn);return b;};
 function setPayload(){payloadInput.value=JSON.stringify({sections});}
 function newId(prefix){let id;do{id=`${prefix}-${Math.random().toString(36).slice(2,9)}`;}while(sections.some(s=>s.id===id));return id;}
 function activeSection(){return sections.find(s=>s.id===selected);}
 function pathTarget(section,path){let obj=section.content;for(const key of path.slice(0,-1)){if(!obj[key]||typeof obj[key]!=='object')obj[key]={};obj=obj[key];}return obj;}
 function fieldScope(section,path){if(path[0]!=='items')return section;let obj=section.content;for(const key of path.slice(0,2))obj=obj[key];return obj;}
 function fieldValue(section,path,locale=fieldLocale){
  const target=pathTarget(section,path),key=path.at(-1);
  if(locale==='en')return target[key]??'';
  return fieldScope(section,path).translations?.ar?.[key]??'';
 }
 function setField(section,path,value,locale=fieldLocale){
  const key=path.at(-1);
  if(locale==='en'){pathTarget(section,path)[key]=value;return;}
  const scope=fieldScope(section,path);scope.translations||={};scope.translations.ar||={};scope.translations.ar[key]=value;
 }
 function addLanguageTabs(parent){
  const languages=el('div','tabs language-tabs');languages.setAttribute('aria-label','Content language');
  for(const [locale,label]of [['en','English (EN)'],['ar','Arabic (AR)']])languages.append(btn(label,fieldLocale===locale?'tab active':'tab',()=>{fieldLocale=locale;render();}));
  parent.append(languages);
 }
 function addField(parent,section,key,path,label){
  const value=fieldValue(section,path),wrap=el('label','field-label');
  wrap.append(el('span','',label||labels[key]||key.replaceAll('_',' ')));
  const input=['description','body','quote'].includes(key)?el('textarea',''):el('input','');
  input.dir='auto';if(input.tagName==='TEXTAREA')input.rows=3;input.value=String(value??'');
  input.addEventListener('input',()=>{setField(section,path,input.value);setPayload();});
  wrap.append(input);parent.append(wrap);
 }
 function renderImage(parent,section,item,path,label){
  const wrap=el('div','image-field');wrap.append(el('strong','',label));
  const id=item.image_id?Number(item.image_id):null,asset=media.find(m=>Number(m.id)===id);
  const altPath=path.length===1?['image_alt']:[...path.slice(0,-1),'image_alt'];
  if(asset){const img=el('img','media-preview');img.src=`../uploads/${encodeURIComponent(asset.filename)}`;img.alt=fieldValue(section,altPath)||asset.alt_text||label;wrap.append(img,el('small','',asset.original_name));}
  else wrap.append(el('div','image-placeholder','No image selected'));
  const actions=el('div','button-row');
  actions.append(btn(asset?'Replace image':'Choose image','button secondary',()=>openPicker(section,item,path,label,altPath)));
  if(asset)actions.append(btn('Remove image','text-button',()=>{item.image_id=null;setField(section,altPath,'','en');setField(section,altPath,'','ar');setPayload();render();}));
  wrap.append(actions);addField(wrap,section,'image_alt',altPath,'Alternative text (Alt Text)');parent.append(wrap);
 }
 function openPicker(section,item,path,label,altPath){pickerTarget={section,item,path,label,altPath};const picker=document.getElementById('mediaPicker');picker.hidden=false;renderMedia();document.getElementById('mediaSearch').focus();}
 function renderMedia(){
  const list=document.getElementById('mediaResults');list.replaceChildren();const q=document.getElementById('mediaSearch').value.trim().toLowerCase();
  const found=media.filter(m=>Number(m.is_active)===1&&String(m.mime_type).startsWith('image/')&&`${m.original_name} ${m.alt_text}`.toLowerCase().includes(q));
  if(!found.length){list.append(el('p','empty','No images match your search.'));return;}
  for(const asset of found){const card=el('article','picker-card'),img=el('img');img.src=`../uploads/${encodeURIComponent(asset.filename)}`;img.alt=asset.alt_text||asset.original_name;card.append(img,el('strong','',asset.original_name),btn('Select image','button',()=>selectMedia(asset)));list.append(card);}
 }
 function selectMedia(asset){
  if(!pickerTarget)return;
  pickerTarget.item.image_id=Number(asset.id);
  if(fieldLocale==='en')setField(pickerTarget.section,pickerTarget.altPath,asset.alt_text||'','en');
  setPayload();document.getElementById('mediaPicker').hidden=true;render();
 }
 function render(){
  host.replaceChildren();const layout=el('div','editor-layout'),nav=el('section','section-list card'),edit=el('section','section-edit card');
  nav.append(el('h2','','Page sections'),el('p','muted',`${sections.length} sections`));const add=el('select');
  for(const [type,name]of Object.entries(types)){const o=el('option','',name);o.value=type;add.append(o);}
  nav.append(add,btn('+ Add section','button secondary',()=>{
   const type=add.value,scheme=schemas[type];let content={};for(const key of scheme.content)content[key]='';
   if(scheme.items)content.items=[];for(const [imageKey]of scheme.images||[])content[imageKey]=null;
   if(['services','features','statistics','testimonials','gallery'].includes(type))content.items=[];
   const section={id:newId(type),type,visible:true,content};sections.push(section);selected=section.id;group='content';render();
  }));
  sections.forEach((s,i)=>{
   const row=el('article',`section-row${s.id===selected?' selected':''}${s.visible?'':' is-hidden'}`);
   row.append(btn(`${s.visible?'●':'○'} ${types[s.type]||s.type} · ${s.id}`,'section-select',()=>{selected=s.id;group='content';render();}));
   const ops=el('div','button-row');
   ops.append(btn('↑','mini',()=>{if(i){[sections[i-1],sections[i]]=[sections[i],sections[i-1]];render();}}),btn('↓','mini',()=>{if(i<sections.length-1){[sections[i+1],sections[i]]=[sections[i],sections[i+1]];render();}}),btn(s.visible?'Hide':'Show','mini',()=>{s.visible=!s.visible;setPayload();render();}),btn('Duplicate','mini',()=>{const c=clone(s);c.id=newId(s.type+'-copy');c.visible=true;sections.splice(i+1,0,c);selected=c.id;setPayload();render();}),btn('Delete','mini danger',()=>{if(confirm('Delete this section?')){sections.splice(i,1);selected=sections[Math.max(0,i-1)]?.id||sections[0]?.id||null;setPayload();render();}}));
   row.append(ops);nav.append(row);
  });
  const s=activeSection();
  if(!s)edit.append(el('p','empty','Add a section to begin.'));
  else{
   const schema=schemas[s.type]||schemas.hero,head=el('div','editor-heading');
   const heading=fieldValue(s,['title'],'ar')||fieldValue(s,['title'],'en')||types[s.type]||s.type;
   head.append(el('span','pill',types[s.type]||s.type),el('h2','',heading));
   const visible=el('label','visible'),checkbox=el('input');checkbox.type='checkbox';checkbox.checked=s.visible;checkbox.addEventListener('change',()=>{s.visible=checkbox.checked;setPayload();render();});visible.append(checkbox,document.createTextNode('Visible'));head.append(visible);edit.append(head);
   const tabs=el('div','tabs');for(const [key,name]of [['content','Content'],['images','Images'],['layout','Layout']])tabs.append(btn(name,group===key?'tab active':'tab',()=>{group=key;render();}));edit.append(tabs);
   if(group==='content'||group==='images')addLanguageTabs(edit);
   const fields=el('div','field-grid');
   if(group==='content'){
    for(const key of schema.content)addField(fields,s,key,[key]);
    if(schema.items){
     const box=el('section','repeat-list');box.append(el('h3','',`Items (${s.content.items?.length||0})`),btn('+ Add item','button secondary',()=>{s.content.items||=[];s.content.items.push(Object.fromEntries(schema.items.map(k=>[k,''])));setPayload();render();}));
     (s.content.items||[]).forEach((item,i)=>{
      const card=el('div','repeat-item');card.append(el('strong','',`Item ${i+1}`));
      for(const key of schema.items)addField(card,s,key,['items',i,key]);
      const itemOps=el('div','button-row');
      itemOps.append(btn('Move up','mini',()=>{if(i>0){[s.content.items[i-1],s.content.items[i]]=[s.content.items[i],s.content.items[i-1]];setPayload();render();}}),btn('Move down','mini',()=>{if(i<s.content.items.length-1){[s.content.items[i+1],s.content.items[i]]=[s.content.items[i],s.content.items[i+1]];setPayload();render();}}),btn('Remove item','text-button',()=>{s.content.items.splice(i,1);setPayload();render();}));
      card.append(itemOps);box.append(card);
     });fields.append(box);
    }
   }else if(group==='images'){
    if(!schema.images?.length&&s.type!=='services'&&s.type!=='gallery')fields.append(el('p','empty','This section has no image fields.'));
    for(const [key,label]of schema.images||[])renderImage(fields,s,s.content,[key],label);
    if(s.type==='services'||s.type==='gallery')(s.content.items||[]).forEach((item,i)=>renderImage(fields,s,item,['items',i,'image_id'],`Item ${i+1} image`));
   }else fields.append(el('p','empty','This section uses its predefined responsive layout.'));
   edit.append(fields);
  }
  layout.append(nav,edit);host.append(layout);setPayload();
 }
 const picker=el('div','modal-backdrop');picker.id='mediaPicker';picker.hidden=true;
 const dialog=el('section','picker-dialog');dialog.setAttribute('role','dialog');dialog.setAttribute('aria-modal','true');
 const bar=el('div','row');bar.append(el('h2','','Choose an image'),btn('Close','button secondary',()=>picker.hidden=true));
 const search=el('input');search.id='mediaSearch';search.placeholder='Search images';search.addEventListener('input',renderMedia);
 const upload=el('input');upload.type='file';upload.accept='image/jpeg,image/png,image/webp,image/gif';
 const uploadButton=el('button','button secondary','Upload and select');uploadButton.type='button';
 uploadButton.addEventListener('click',async()=>{
  if(!upload.files[0]||!pickerTarget)return;const body=new FormData();body.append('csrf',form.querySelector('[name=csrf]').value);body.append('action','upload-media-picker');body.append('image',upload.files[0]);
  try{const response=await fetch('./',{method:'POST',credentials:'same-origin',body,headers:{Accept:'application/json'}});const result=await response.json();if(!response.ok||!result.asset)throw new Error(result.error||'Upload failed.');media.unshift(result.asset);selectMedia(result.asset);}catch(e){alert(e.message);}
 });
 const results=el('div','media-results');results.id='mediaResults';dialog.append(bar,search,upload,uploadButton,results);picker.append(dialog);
 picker.addEventListener('click',e=>{if(e.target===picker)picker.hidden=true;});document.body.append(picker);
 document.addEventListener('keydown',e=>{if(e.key==='Escape')picker.hidden=true;});form.addEventListener('submit',setPayload);render();
})();
