<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ __('ai-ocr::messages.title') }}</title>
<style>
:root{color-scheme:light dark;--bg:#f3f5f7;--card:white;--ink:#172329;--muted:#63727a;--line:#dce3e7;--accent:#087f76}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px system-ui}body.dark{--bg:#101b20;--card:#17272e;--ink:#e3eff2;--muted:#a1b6bf;--line:#30474f}header,main{max-width:1320px;margin:auto;padding:28px}header{display:flex;justify-content:space-between;align-items:center}h1{font-size:28px;letter-spacing:-1px;margin:0}.muted{color:var(--muted)}nav{display:flex;gap:12px;margin-bottom:22px}button,select,input{font:inherit;padding:10px;border:1px solid var(--line);border-radius:7px;background:var(--card);color:var(--ink)}button{cursor:pointer}button.primary{background:var(--accent);color:white;border:0}section,.panel{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:22px;margin-bottom:20px}.controls{display:flex;gap:12px;flex-wrap:wrap;align-items:end}label{display:grid;gap:6px;font-size:13px}.viewer{display:grid;grid-template-columns:1fr 1fr;gap:20px}pre{white-space:pre-wrap;overflow:auto;font:13px/1.6 ui-monospace,monospace;max-height:580px}iframe,img.preview{width:100%;height:520px;object-fit:contain;border:0}.drop{border:2px dashed var(--line);padding:28px;text-align:center;margin-bottom:20px}.badge{display:inline-block;background:#d6f3ea;color:#075e51;padding:3px 7px;border-radius:4px;margin-right:5px}table{border-collapse:collapse;width:100%}td,th{text-align:left;border-bottom:1px solid var(--line);padding:9px}details{margin:12px 0}#status{padding:12px;color:var(--muted)}[hidden]{display:none!important}@media(max-width:800px){.viewer{grid-template-columns:1fr}header,main{padding:16px}}
.provider-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:16px}.provider-card{border:1px solid var(--line);border-radius:12px;padding:20px;min-width:0}.provider-heading,.provider-intro{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.provider-heading{display:grid;gap:10px;min-height:66px;align-content:start}.provider-card h3{margin:0}.provider-card label{margin:14px 0}.provider-card input:not([type=checkbox]){width:100%;min-width:0}.provider-card .checks{display:flex;gap:16px;flex-wrap:wrap}.provider-card .checks label{display:flex;align-items:center;gap:7px}.provider-card .actions{display:flex;gap:8px;flex-wrap:wrap}.provider-status{justify-self:start;font-size:12px;padding:5px 8px;border-radius:20px;background:#fff0cd;color:#71520b}.provider-status.configured{background:#d6f3ea;color:#075e51}.provider-message{font-size:13px;min-height:20px;line-height:1.5}.provider-link{display:inline-block;padding:10px;border:1px solid var(--line);border-radius:7px;color:var(--accent);text-decoration:none}.provider-note{line-height:1.6}button:disabled{opacity:.55;cursor:not-allowed}nav{overflow:auto;white-space:nowrap}nav button[aria-current=page]{border-color:var(--accent);color:var(--accent)}
</style></head><body>
<header><div><h1>{{ __('ai-ocr::messages.title') }}</h1><p class="muted">{{ __('ai-ocr::messages.manual_first') }}</p></div><button id="theme">Light / dark</button></header>
<main><nav><button data-tab="playground">Playground</button><button data-tab="ai-providers">AI Providers</button><button data-tab="health">Doctor</button><button data-tab="history">History</button><button data-tab="schemas">Schema builder</button></nav>
<div id="playground"><section><form id="extract"><div class="drop" id="drop"><input type="file" name="file" id="file" required><p class="muted">Drop a document here, or choose a file</p></div><div class="controls"><label>Strategy<select name="strategy">@foreach(['auto','manual_only','ai_only','ai_verify','consensus'] as $strategy)<option>{{ $strategy }}</option>@endforeach</select></label><label>Document type<select name="type"><option value="">Text only</option>@foreach($types as $type)<option>{{ $type }}</option>@endforeach</select></label><label>Languages<input id="languages" value="eng" size="12"></label><label>Provider<input name="provider" placeholder="Hub default" size="12"></label><label>Model<input name="model" placeholder="Hub default" size="14"></label><label>DPI<input name="dpi" type="number" min="72" max="600" value="300" style="width:90px"></label><label>Queue<input type="checkbox" name="queued" value="1"></label><label>Parallel pages<input type="checkbox" name="parallel" value="1"></label><input type="hidden" name="schema" id="custom-schema"><button class="primary" type="submit">{{ __('ai-ocr::messages.extract') }}</button></div></form><button id="compare" style="margin-top:12px">Compare manual / AI / consensus (AI may incur cost)</button><div id="comparison" hidden></div><div id="status" role="status"></div></section>
<div class="viewer"><section><h3>Original document</h3><div id="preview" class="muted">Choose a document to preview.</div></section><section><h3>Extraction <span id="engines"></span></h3><div id="words"></div><pre id="output">Text and structured data will appear here.</pre><button id="download" hidden>Download result JSON</button></section></div><section><h3>Pipeline trace</h3><div id="trace" class="muted">Each extraction step, quality score and model is recorded here.</div></section></div>
<section id="ai-providers" hidden aria-labelledby="providers-title">
    <div class="provider-intro"><div><h2 id="providers-title">Connect your AI providers</h2><p class="muted">Add an API key, choose a model, and save. Your settings are shared with AI Hub.</p></div><div class="controls"><button type="button" id="refresh-providers">Refresh status</button>@if($aiPanel['hub_url'])<a class="provider-link" href="{{ $aiPanel['hub_url'] }}" target="_blank" rel="noopener">Open AI Hub ↗</a>@endif</div></div>
    <p class="provider-note" id="provider-summary">{{ $aiPanel['enabled'] ? 'AI fallback is enabled.' : 'AI fallback is off. Manual extraction still works.' }} Default provider: <strong>{{ $aiPanel['default'] }}</strong>. Configured status reflects settings, not a tested connection.</p>
    @if(!$aiPanel['save_url'])<p class="provider-note" role="status">Visual key management is unavailable. Enable AI_HUB_UI=true in your Laravel environment and configure AI Hub authorization. You can also configure provider keys in .env.</p>@endif
    <div class="provider-grid">
    @foreach($aiPanel['providers'] as $provider)
        <form class="provider-card" data-provider="{{ $provider['key'] }}" autocomplete="off">
            <div class="provider-heading"><h3>{{ $provider['label'] }} <span class="provider-default" @if(!$provider['is_default']) hidden @endif>· Default</span></h3><span class="provider-status {{ $provider['status'] === 'Configured' ? 'configured' : '' }}">{{ $provider['status'] }}</span></div>
            <label for="provider-key-{{ $provider['key'] }}">{{ $provider['key'] === 'ollama' ? 'API key (optional for local Ollama)' : 'API key' }}<input id="provider-key-{{ $provider['key'] }}" type="password" name="api_key" autocomplete="new-password" placeholder="{{ $provider['configured'] ? 'Leave blank to keep existing key' : 'Paste your API key' }}" @disabled(!$aiPanel['save_url'])></label>
            <label for="provider-model-{{ $provider['key'] }}">Model<input id="provider-model-{{ $provider['key'] }}" name="model" value="{{ $provider['model'] }}" maxlength="120" placeholder="Provider default model" @disabled(!$aiPanel['save_url'])></label>
            <div class="checks"><label><input type="checkbox" name="enabled" @checked($provider['enabled']) @disabled(!$aiPanel['save_url'])> Enabled</label><label><input type="checkbox" name="make_default" @checked($provider['is_default']) @disabled(!$aiPanel['save_url'])> Make default</label></div>
            <div class="actions"><button class="primary" type="submit" @disabled(!$aiPanel['save_url'])>Save {{ $provider['label'] }}</button><button type="button" class="use-provider" @disabled(!$provider['allowed'])>Use in Playground</button></div>
            <p class="provider-message muted" role="status" aria-live="polite">{{ !$provider['allowed'] ? 'This provider is blocked by the OCR allowlist.' : ($provider['key'] === 'ollama' ? 'Local inference needs a reachable Ollama endpoint.' : 'Existing keys are never displayed here.') }}</p>
        </form>
    @endforeach
    </div>
    <p class="muted provider-note">Saving uses AI Hub’s own protected settings endpoint and requires its settings permission. Keys are stored by your configured AI Hub backend; OCR does not keep a separate copy. Blank keys preserve existing credentials. Saving does not send an AI request. After changing settings, restart long-running queue workers. Configure Azure endpoints, model pricing and advanced provider options in AI Hub or Laravel configuration.</p>
</section>
<section id="health" hidden><h2>{{ __('ai-ocr::messages.doctor') }}</h2><pre>{{ json_encode($doctor, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></section>
<section id="history" hidden><h2>Recent extractions</h2><form method="get" class="controls"><label>Search ID<input name="search" value="{{ request('search') }}"></label><label>Type<input name="type" value="{{ request('type') }}"></label><button>Filter history</button></form>@if(!config('ai-ocr.store.enabled'))<p class="muted">Enable AI_OCR_STORE and run the published migrations to keep extraction history.</p>@else<table><tr><th>ID</th><th>Type</th><th>Confidence</th><th>USD</th><th>Review / rerun</th></tr>@foreach($history as $item)<tr><td><button class="history-result" data-id="{{ $item->id }}">{{ $item->id }}</button></td><td>{{ $item->type }}</td><td>{{ $item->confidence }}</td><td>{{ $item->cost }}</td><td><button class="history-action" data-id="{{ $item->id }}" data-action="rerun">Rerun (may incur cost)</button> <button class="history-action" data-id="{{ $item->id }}" data-action="correct">Mark correct</button> <button class="history-action" data-id="{{ $item->id }}" data-action="incorrect">Needs correction</button><span role="status"></span></td></tr>@endforeach</table>@endif</section>
<section id="schemas" hidden><h2>Schema builder</h2><p class="muted">Build fields and apply the schema to the playground. Money values use integer minor units.</p><div class="controls"><label>Schema name<input id="schema-name" value="custom_document"></label><label>Field name<input id="field-name" placeholder="invoice_number"></label><label>Type<select id="field-type"><option>string</option><option>integer</option><option>number</option><option>boolean</option></select></label><label>Required<input type="checkbox" id="field-required" checked></label><button id="add-field">Add field</button><button id="apply-schema">Use in playground</button><button id="save-schema">Save template</button><button id="load-schemas">Load templates</button></div><pre id="schema-output"></pre><div id="saved-schemas"></div><p id="schema-status" role="status"></p></section>
@if(config('ai-ocr.store.enabled'))<section><h3>Recent activity</h3><p>{{ $analytics['documents'] }} documents · ${{ $analytics['cost_usd'] }} total · {{ round(($analytics['average_confidence'] ?? 0)*100) }}% average confidence</p><p>AI fallback: {{ round(($analytics['fallback_rate'] ?? 0)*100, 1) }}% · Estimated savings: {{ $analytics['estimated_savings_usd'] === null ? 'insufficient data' : '$'.round($analytics['estimated_savings_usd'], 4) }}</p><pre>{{ json_encode($analytics['per_type'] ?? [], JSON_PRETTY_PRINT) }}</pre></section>@endif
<p class="muted">Developed by <a href="https://imrandev.bd">Imran Ahmed</a> · Keys are managed in AI Hub.</p></main>
<script>
(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    const endpoint = @json(route('ai-ocr.studio.extract'));
    const schemaEndpoint = @json(route('ai-ocr.schemas'));
    const headers = {'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content};
    let sourceUrl = null, result = null;
    const schema = {type:'object',properties:{},required:[],additionalProperties:false};
    const tab = name => { ['playground','ai-providers','health','history','schemas'].forEach(id => $(id).hidden = id !== name); document.querySelectorAll('[data-tab]').forEach(button=>button.setAttribute('aria-current',button.dataset.tab===name?'page':'false')); };
    tab('playground');
    document.querySelectorAll('[data-tab]').forEach(button => button.onclick = () => tab(button.dataset.tab));
    const providerEndpoint = @json(route('ai-ocr.studio.providers'));
    const providerSaveEndpoint = @json($aiPanel['save_url']);
    async function refreshProviders() {
        const response=await fetch(providerEndpoint,{headers,cache:'no-store'});
        if(!response.ok)throw new Error('Provider status could not be refreshed. Reload Studio and sign in again if needed.');
        const panel=await response.json();
        $('provider-summary').textContent=(panel.enabled?'AI fallback is enabled.':'AI fallback is off. Manual extraction still works.')+' Default provider: '+panel.default+'. Configured status reflects settings, not a tested connection.';
        panel.providers.forEach(provider=>{
            const form=Array.from(document.querySelectorAll('.provider-card')).find(card=>card.dataset.provider===provider.key);if(!form)return;
            const badge=form.querySelector('.provider-status');badge.textContent=provider.status;badge.classList.toggle('configured',provider.status==='Configured');
            form.querySelector('.provider-default').hidden=!provider.is_default;
            form.elements.namedItem('api_key').placeholder=provider.configured?'Leave blank to keep existing key':'Paste your API key';
            form.elements.namedItem('enabled').checked=provider.enabled;
            form.elements.namedItem('make_default').checked=provider.is_default;
        });
    }
    $('refresh-providers').onclick=async()=>{const button=$('refresh-providers');button.disabled=true;try{await refreshProviders();}catch(error){$('provider-summary').textContent=error.message;}finally{button.disabled=false;}};
    document.querySelectorAll('.provider-card').forEach(form=>{
        form.onsubmit=async event=>{
            event.preventDefault();if(!providerSaveEndpoint)return;
            const button=form.querySelector('[type=submit]'),message=form.querySelector('.provider-message'),key=form.elements.namedItem('api_key');button.disabled=true;message.textContent='Saving to AI Hub…';
            try {
                const payload={provider:form.dataset.provider,model:form.elements.namedItem('model').value.trim()||null,enabled:form.elements.namedItem('enabled').checked,make_default:form.elements.namedItem('make_default').checked};
                if(key.value.trim())payload.api_key=key.value.trim();
                const response=await fetch(providerSaveEndpoint,{method:'POST',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify(payload)});
                if(response.status===403||response.status===401)throw new Error('You need AI Hub settings permission to save providers. Open AI Hub to sign in or contact your administrator.');
                if(response.status===419)throw new Error('Your session expired. Reload Studio before saving.');
                if(!response.ok)throw new Error('Settings were not saved. Check your provider/model values and AI Hub setup.');
                const saved=await response.json();
                if(saved.success!==true)throw new Error('AI Hub did not confirm the settings change.');
                key.value='';await refreshProviders();message.textContent='Saved in AI Hub. Extraction readiness, privacy and budget checks still apply.';
            }catch(error){message.textContent=error.message;}finally{key.value='';button.disabled=false;}
        };
        form.querySelector('.use-provider').onclick=()=>{
            document.querySelector('#extract [name=provider]').value=form.dataset.provider;
            document.querySelector('#extract [name=model]').value=form.elements.namedItem('model').value;
            tab('playground');$('status').textContent='Selected '+form.dataset.provider+'. Provider readiness, privacy and budget rules still apply.';
        };
    });
    $('theme').onclick = () => { document.body.classList.toggle('dark'); localStorage.setItem('ai-ocr-dark',document.body.classList.contains('dark')?'1':'0'); };
    if (localStorage.getItem('ai-ocr-dark') === '1') document.body.classList.add('dark');
    function preview() {
        const file = $('file').files[0]; if (!file) return;
        if (sourceUrl) URL.revokeObjectURL(sourceUrl); sourceUrl = URL.createObjectURL(file);
        $('preview').replaceChildren();
        if (['image/png','image/jpeg','image/webp','image/gif'].includes(file.type)) {
            const image = document.createElement('img'); image.className='preview'; image.src=sourceUrl; image.alt='Original upload'; $('preview').append(image);
        } else if (file.type === 'application/pdf') {
            const frame=document.createElement('iframe'); frame.title='Original PDF'; frame.sandbox=''; frame.src=sourceUrl; $('preview').append(frame);
        } else $('preview').textContent=file.name;
    }
    $('file').onchange=preview; $('drop').ondragover=e=>e.preventDefault();
    $('drop').ondrop=e=>{e.preventDefault();$('file').files=e.dataTransfer.files;preview();};
    function formData(strategy = null) {
        const data=new FormData($('extract'));
        $('languages').value.split(',').map(v=>v.trim()).filter(Boolean).forEach(value=>data.append('languages[]',value));
        if (strategy) { data.set('strategy',strategy); data.delete('queued'); data.delete('parallel'); }
        return data;
    }
    async function request(data) {
        const response=await fetch(endpoint,{method:'POST',headers,body:data}); const value=await response.json();
        if (!response.ok) throw new Error(value.message || 'Extraction failed');
        if (response.status !== 202) return value;
        for (;;) {
            await new Promise(resolve=>setTimeout(resolve,1500));
            const poll=await fetch(value.poll_url,{headers}); const progress=await poll.json();
            if (!poll.ok) throw new Error(progress.message || 'Progress unavailable');
            if (progress.status==='failed') throw new Error('Queued extraction failed: '+progress.exception);
            if (progress.status==='completed') return progress.result;
            $('status').textContent='Queued extraction: '+progress.status+'; waiting for workers…';
        }
    }
    function render(value) {
        result=value; $('output').textContent=value.text+'\n\n'+JSON.stringify(value.data,null,2);
        $('engines').replaceChildren(); Object.entries(value.engines).forEach(([engine,count])=>{const badge=document.createElement('span');badge.className='badge';badge.textContent=engine+' '+count;$('engines').append(badge);});
        $('words').replaceChildren();
        value.pages.forEach(page=>(page.words || []).forEach(word=>{const span=document.createElement('span');span.textContent=word.text+' ';span.style.background=word.confidence<0.7?'#ffc8bb':'#d6f3ea';span.style.color='#172329';span.title='Page '+page.number+'; confidence '+Math.round(word.confidence*100)+'%; box '+JSON.stringify(word.bbox);$('words').append(span);}));
        $('trace').replaceChildren(); value.trace.forEach(step=>{const details=document.createElement('details');const summary=document.createElement('summary');summary.textContent=step.step+' · '+step.elapsed_ms+'ms';const pre=document.createElement('pre');pre.textContent=JSON.stringify(step,null,2);details.append(summary,pre);$('trace').append(details);});
        $('status').textContent='Confidence '+Math.round(value.confidence*100)+'% · $'+value.cost.usd+' · '+value.warnings.join(' | ');$('download').hidden=false;
    }
    $('extract').onsubmit=async event=>{event.preventDefault();const button=event.submitter;button.disabled=true;$('status').textContent='Extracting…';try{render(await request(formData()));}catch(error){$('status').textContent=error.message;}finally{button.disabled=false;}};
    $('compare').onclick=async()=>{if(!$('file').files.length){$('status').textContent='Choose a file first';return;}$('compare').disabled=true;$('comparison').hidden=false;$('comparison').replaceChildren();try{for(const strategy of ['manual_only','ai_only','consensus']){const value=await request(formData(strategy));const details=document.createElement('details');details.open=true;const title=document.createElement('summary');title.textContent=strategy+' · confidence '+Math.round(value.confidence*100)+'% · $'+value.cost.usd;const pre=document.createElement('pre');pre.textContent=value.text+'\n'+JSON.stringify(value.data,null,2);details.append(title,pre);$('comparison').append(details);}}catch(error){$('status').textContent=error.message;}finally{$('compare').disabled=false;}};
    $('download').onclick=()=>{const url=URL.createObjectURL(new Blob([JSON.stringify(result,null,2)],{type:'application/json'}));const link=document.createElement('a');link.href=url;link.download='extraction.json';link.click();URL.revokeObjectURL(url);};
    function refreshSchema(){$('schema-output').textContent=JSON.stringify(schema,null,2);}refreshSchema();
    $('add-field').onclick=()=>{const name=$('field-name').value.trim();if(!/^[A-Za-z][A-Za-z0-9_]*$/.test(name)){$('schema-status').textContent='Use a field name such as invoice_number';return;}schema.properties[name]={type:$('field-type').value};if($('field-required').checked&&!schema.required.includes(name))schema.required.push(name);refreshSchema();};
    $('apply-schema').onclick=()=>{$('custom-schema').value=JSON.stringify(schema);$('schema-status').textContent='Schema applied to playground';};
    $('save-schema').onclick=async()=>{try{const response=await fetch(schemaEndpoint,{method:'POST',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify({name:$('schema-name').value,schema})});if(!response.ok)throw new Error('Saving requires storage and an authenticated owner');$('schema-status').textContent='Template saved';}catch(error){$('schema-status').textContent=error.message;}};
    $('load-schemas').onclick=async()=>{try{const response=await fetch(schemaEndpoint,{headers});if(!response.ok)throw new Error('Loading requires storage and an authenticated owner');const templates=await response.json();$('saved-schemas').replaceChildren();templates.forEach(template=>{const button=document.createElement('button');button.textContent=template.name;button.onclick=()=>{$('custom-schema').value=JSON.stringify(template.schema);$('schema-status').textContent='Applied '+template.name;};$('saved-schemas').append(button);});}catch(error){$('schema-status').textContent=error.message;}};
    document.querySelectorAll('.history-action').forEach(button=>button.onclick=async()=>{
        const rerun=button.dataset.action==='rerun';const status=button.parentElement.querySelector('[role=status]');button.disabled=true;
        try { const response=await fetch(@json(url(config('ai-ocr.studio.path','ai-ocr')))+'/extractions/'+encodeURIComponent(button.dataset.id)+(rerun?'/rerun':'/review'),{method:'POST',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify(rerun?{strategy:document.querySelector('[name=strategy]').value}:{correct:button.dataset.action==='correct'})});const value=await response.json();if(!response.ok)throw new Error(value.message||'Action failed');if(rerun){render(value);tab('playground');}else status.textContent='Review saved'; } catch(error){status.textContent=error.message;}finally{button.disabled=false;}
    });
    document.querySelectorAll('.history-result').forEach(button=>button.onclick=async()=>{try{const response=await fetch(@json(url(config('ai-ocr.studio.path','ai-ocr')))+'/extractions/'+encodeURIComponent(button.dataset.id),{headers});if(!response.ok)throw new Error('History result unavailable');render(await response.json());tab('playground');}catch(error){$('status').textContent=error.message;}});
})();
</script></body></html>
