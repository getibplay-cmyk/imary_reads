const labels = JSON.parse(document.body.dataset.labels || '{}');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
let toastTimer;
export function announce(message) { const el=document.querySelector('.live-toast'); if (!el) return; el.textContent=message;el.hidden=false;clearTimeout(toastTimer);toastTimer=setTimeout(()=>{el.hidden=true;},5000); }
export async function api(url, data, options={}) {
    const response = await fetch(url,{ method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(data),...options });
    if (!response.ok) { const err=new Error(response.status===409?labels.conflict:labels.save_error);err.status=response.status;throw err; }
    return response.json();
}
try { const theme=localStorage.getItem('imary.theme'); if(['dark','light'].includes(theme))document.documentElement.dataset.theme=theme; } catch {}
document.querySelector('[data-theme-toggle]')?.addEventListener('click',()=>{const theme=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=theme;try{localStorage.setItem('imary.theme',theme);}catch{}});
document.querySelector('[data-menu-toggle]')?.addEventListener('click',e=>{const button=e.currentTarget;const open=button.getAttribute('aria-expanded')!=='true';button.setAttribute('aria-expanded',String(open));document.getElementById('main-nav').classList.toggle('is-open',open);});
document.querySelectorAll('[data-autosubmit]').forEach(el=>el.addEventListener('change',()=>el.form.requestSubmit()));
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault();}));
document.querySelectorAll('form:not([data-editor-form])').forEach(form=>form.addEventListener('submit',e=>{if(e.defaultPrevented)return;const b=e.submitter;if(b){setTimeout(()=>{b.disabled=true;b.dataset.loading='1';},0);}}));
window.addEventListener('pageshow',()=>document.querySelectorAll('[data-loading]').forEach(b=>{b.disabled=false;delete b.dataset.loading;}));
// Live search: debounce, cancel obsolete requests, and ignore stale responses.
const search=document.querySelector('[data-search-url]');
if(search){
 const list=document.getElementById('search-results'),status=document.querySelector('[data-search-status]');let timer,controller,sequence=0,index=-1;
 const close=()=>{list.hidden=true;search.setAttribute('aria-expanded','false');search.removeAttribute('aria-activedescendant');index=-1;};
 search.addEventListener('input',()=>{clearTimeout(timer);controller?.abort();const current=++sequence;const query=search.value.trim();if(query.length<2){close();return;}
 timer=setTimeout(async()=>{controller=new AbortController();try{const url=new URL(search.dataset.searchUrl);url.searchParams.set('q',query);const response=await fetch(url,{signal:controller.signal,headers:{Accept:'application/json'}});if(!response.ok)throw new Error();const data=await response.json();if(current!==sequence)return;list.replaceChildren();index=-1;
  for(const [i,item] of data.results.entries()){const a=document.createElement('a');a.href=item.url;a.className='search-result';a.id=`search-option-${i}`;a.setAttribute('role','option');a.setAttribute('aria-selected','false');const img=document.createElement('img');img.src=item.cover;img.alt='';const div=document.createElement('div'),title=document.createElement('b'),author=document.createElement('small');title.textContent=item.title;author.textContent=item.author;div.append(title,author);a.append(img,div);list.append(a);}
  if(!data.results.length){const p=document.createElement('div');p.className='search-message';p.textContent=labels.search_empty;list.append(p);}list.hidden=false;search.setAttribute('aria-expanded','true');status.textContent=data.results.length?String(data.results.length):labels.search_empty;
 }catch(error){if(error.name!=='AbortError'&&current===sequence){status.textContent=labels.search_error;close();}}},220);});
 search.addEventListener('keydown',e=>{const results=[...list.querySelectorAll('[role=option]')];if(e.key==='Escape'){close();return;}if(['ArrowDown','ArrowUp'].includes(e.key)&&results.length){e.preventDefault();index=(index+(e.key==='ArrowDown'?1:-1)+results.length)%results.length;results.forEach((r,i)=>r.setAttribute('aria-selected',String(i===index)));search.setAttribute('aria-activedescendant',results[index].id);results[index].scrollIntoView({block:'nearest'});}if(e.key==='Enter'&&index>=0&&!list.hidden){e.preventDefault();location.href=results[index].href;}});
 document.addEventListener('click',e=>{if(!search.form.contains(e.target))close();});
}
document.querySelectorAll('[data-move]').forEach(button=>button.addEventListener('click',()=>{const item=button.closest('[data-sortable-item]');if(button.dataset.move==='up'&&item.previousElementSibling)item.parentNode.insertBefore(item,item.previousElementSibling);else if(button.dataset.move==='down'&&item.nextElementSibling)item.parentNode.insertBefore(item.nextElementSibling,item);}));
const reader=document.querySelector('[data-reader]');
if(reader)import('./reader/book').then(({setupReader})=>setupReader(reader,api,announce)).catch(()=>announce(labels.error));
const editorForm=document.querySelector('[data-editor-form]');
if(editorForm)import('./writer').then(({setupEditor})=>setupEditor(editorForm,labels,csrf,announce)).catch(()=>announce(labels.error));
