import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
export function setupEditor(form,labels,csrf,announce){
 const source=form.querySelector('[data-editor-source]'),surface=form.querySelector('[data-editor-surface]'),status=form.querySelector('[data-save-status]');
 let dirty=false,saving=false,timer,generation=0,conflicted=false;
 const changed=()=>{dirty=true;generation++;status.textContent=labels.unsaved;clearTimeout(timer);if(form.dataset.autosave==='true')timer=setTimeout(save,1600);};
 const editor=new Editor({element:surface,extensions:[StarterKit.configure({heading:{levels:[2,3]},link:false})],content:source.value,editorProps:{attributes:{role:'textbox','aria-multiline':'true','aria-label':source.labels?.[0]?.textContent||labels.content}},onUpdate:()=>{source.value=editor.getHTML();changed();}});
 source.hidden=true;source.required=false;
 form.querySelectorAll('[data-editor-command]').forEach(button=>button.addEventListener('click',()=>{const chain=editor.chain().focus();switch(button.dataset.editorCommand){case'bold':chain.toggleBold().run();break;case'italic':chain.toggleItalic().run();break;case'heading':chain.toggleHeading({level:2}).run();break;case'quote':chain.toggleBlockquote().run();break;case'list':chain.toggleBulletList().run();break;case'undo':chain.undo().run();break;case'redo':chain.redo().run();break;}}));
 form.querySelectorAll('input[name=title],select, input[name=published_at]').forEach(input=>input.addEventListener('input',changed));
 async function save(){
  if(!dirty||saving||conflicted||!form.reportValidity())return;
  saving=true;const submitted=generation;status.textContent=labels.saving;
  const data=new FormData(form);data.set('content',editor.getHTML());
  try{const response=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});if(!response.ok){const e=new Error();e.status=response.status;throw e;}
   const result=await response.json();form.querySelector('[name=revision]').value=result.revision;form.querySelector('[data-revision]').textContent=result.revision;
   // Restores on this page must also carry the latest optimistic-lock revision.
   document.querySelectorAll('form input[name=revision]').forEach(input=>input.value=result.revision);
   if(form.dataset.autosave!=='true'){dirty=false;location.href=result.url;return;}
   dirty=generation!==submitted;status.textContent=dirty?labels.unsaved:labels.saved;
  }catch(e){if(e.status===409)conflicted=true;status.textContent=e.status===409?labels.conflict:labels.save_error;announce(status.textContent);}
  finally{saving=false;if(dirty&&!conflicted&&generation!==submitted)timer=setTimeout(save,1600);}
 }
 form.addEventListener('submit',e=>{e.preventDefault();source.value=editor.getHTML();dirty=true;save();});
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
}
