const {test}=require('node:test');
const assert=require('node:assert/strict');
const {JSDOM}=require('jsdom');
const fs=require('node:fs');
const jquery=require('jquery');
test('light scheme is scoped to owned dropdowns and survives replacement/reopening',()=>{
 const dom=new JSDOM('<body class="scheme_default"><div class="ishi-theme-account"><select id="profile"></select></div><div class="ishi-theme-account"><select id="address"></select></div><select id="outside"></select></body>',{runScripts:'outside-only'});
 const w=dom.window,$=jquery(w); w.jQuery=$;w.QWERY_STORAGE={};
 w.eval(fs.readFileSync(require('node:path').join(__dirname,'../assets/dropdown-scheme.js'),'utf8'));
 function attach(id){
  const select=$('#'+id),dropdown=$('<span class="select2-container"><span class="select2-dropdown scheme_dark"><span class="select2-results">Options</span></span></span>').appendTo('body');
  select.data('select2',{$dropdown:dropdown});return {select,dropdown,popup:dropdown.find('.select2-dropdown')};
 }
 for(const id of ['profile','address','outside']){
  const x=attach(id);x.select.trigger('select2:open');
  assert.equal(x.popup.hasClass('scheme_light'),id!=='outside');
  assert.equal(x.select.hasClass('scheme_light'),false);
  assert.equal(x.dropdown.hasClass('scheme_light'),false);
  x.select.trigger('select2:close');assert.equal(x.popup.hasClass('scheme_dark'),true);
  assert.equal(x.popup.hasClass('scheme_light'),false);
  x.select.trigger('select2:open');assert.equal(x.popup.hasClass('scheme_light'),id!=='outside');
 }
 $('#address').replaceWith('<select id="replacement"></select>');
 const replacement=attach('replacement');replacement.select.trigger('select2:open');assert.ok(replacement.popup.hasClass('scheme_light'));
 assert.equal(w.document.body.className,'scheme_default');
 dom.window.close();
});
