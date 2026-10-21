import test from 'node:test';
import assert from 'node:assert/strict';
import { paginateText, pageForOffset, physicalIndex, logicalIndex } from '../../resources/js/reader/pagination.js';
test('Arabic, diacritics, emoji and paragraphs survive pagination without loss', () => {
 const text='في مدينة مَرّاكش 📚\n\nحكاية طويلة لا تنتهي. '.repeat(100);
 const pages=paginateText(text,s=>Array.from(s).length<=140);
 assert.equal(pages.map(p=>p.text).join(''),text);
 let offset=0;
 for(const p of pages){assert.equal(p.start,offset);assert.equal(p.end-p.start,Array.from(p.text).length);assert.ok(p.end-p.start<=140);offset=p.end;}
 assert.equal(offset,Array.from(text).length);
});
test('reading position survives a new page size',()=>{
 const text='One small step. '.repeat(300);const old=paginateText(text,s=>s.length<=100);const offset=old[20].start;
 const resized=paginateText(text,s=>s.length<=70);const index=pageForOffset(resized,offset);
 assert.ok(resized[index].start<=offset);assert.ok(resized[index].end>offset);
});
test('RTL and LTR map the first and last logical pages correctly',()=>{
 for(const rtl of [true,false])for(let logical=0;logical<10;logical++)assert.equal(logicalIndex(physicalIndex(logical,10,rtl),10,rtl),logical);
 assert.equal(physicalIndex(0,10,true),9);assert.equal(physicalIndex(9,10,true),0);assert.equal(physicalIndex(0,10,false),0);
});
test('empty pages, oversized words and a tiny viewport terminate safely',()=>{
 assert.deepEqual(paginateText('',()=>true),[{start:0,end:0,text:''}]);
 const pages=paginateText('abc',()=>false);assert.equal(pages.length,3);assert.equal(pages.map(p=>p.text).join(''),'abc');
 const long=paginateText('x'.repeat(20000),s=>s.length<=450);assert.equal(long.map(p=>p.text).join(''),'x'.repeat(20000));
 assert.equal(pageForOffset(pages,-2),0);assert.equal(pageForOffset(pages,10000),2);assert.equal(pageForOffset(pages,1),1);
});
