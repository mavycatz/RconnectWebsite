const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const assert = require('node:assert/strict');
class Element {
    constructor() { this.style = {}; this.events = {}; this.value = ''; this.classList = { toggle() {} }; this.innerHTML = ''; }
    addEventListener(name, fn) { this.events[name] = fn; }
}
const nodes = new Map();
const el = id => { if (!nodes.has(id)) nodes.set(id, new Element()); return nodes.get(id); };
const modals = new Map();
const modal = node => { if (!modals.has(node)) modals.set(node, { shown:false,show() { this.shown=true; },hide() { this.shown=false; } }); return modals.get(node); };
const requests = [];
const row = { source:'live',ref:42,baggage_id:'BAG-042',description:'Existing luggage',passenger_id:9,
    spaces:2,base_fare:15,fare:30,payment_status:'pending',boarding_point:'Stop A',destination:'Stop B' };
const context = { document: { readyState:'complete',getElementById:el }, console, URLSearchParams,setTimeout,clearTimeout,
    bootstrap:{Modal:{getOrCreateInstance:modal}}, window:{},
    fetch: async url => { requests.push(String(url)); return { json:async()=>({success:true,data:[row],total:1,pages:1,page:1,per_page:15,categories:['Luggage']}) }; } };
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/js/passenger-baggage.js'),'utf8'),context);
(async()=>{
    for(let i=0;i<5;i++) await new Promise(resolve=>setImmediate(resolve));
    assert.ok(el('bagBody').innerHTML.includes('data-label="Route"'));
    assert.ok(el('bagBody').innerHTML.includes('rmg-icon-btn is-view') && el('bagBody').innerHTML.includes('rmg-icon-btn is-edit'));
    assert.ok(el('bagBody').innerHTML.includes('₱30.00'));
    assert.ok(el('bagPag').innerHTML.includes('rmg-pg-btn'));
    el('bagBody').events.click({target:{closest:()=>({getAttribute:key=>key==='data-action'?'view':'0'})}});
    assert.equal(modal(el('bagDetailModal')).shown,true);
    assert.ok(el('bagDetailBody').innerHTML.includes('Existing luggage'));
    el('bagPayment').value='pending';el('bagPayment').events.change();
    for(let i=0;i<5;i++) await new Promise(resolve=>setImmediate(resolve));
    assert.ok(requests.some(url=>url.includes('payment_status=pending')));
    console.log('Baggage shared row/actions, responsive labels, original fare, pagination, detail modal and filter checks passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
