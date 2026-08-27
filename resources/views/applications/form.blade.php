@extends('clients.layout')
@section('title',$application->exists?'Editar solicitud':'Nueva solicitud')
@section('content')
@php
    $input = 'control';
    $label = 'field-label';
    $productDefaults = $products->mapWithKeys(function ($item) {
        return [(string) $item->id => [
            'currency' => $item->currency,
            'rate' => $item->default_interest_rate,
            'method' => $item->default_interest_method,
        ]];
    });
@endphp
<div class="mb-4"><a href="{{route('applications.index')}}" class="flex items-center gap-2 text-xs font-semibold text-indigo-600"><i data-lucide="arrow-left" class="icon"></i>Solicitudes</a><h1 class="page-title mt-2">{{$application->exists?'Editar solicitud':'Nueva solicitud'}}</h1><p class="page-description">Las condiciones permanecen editables hasta el desembolso.</p></div>
@if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
<form method="POST" enctype="multipart/form-data" action="{{$application->exists?route('applications.update',$application):route('applications.store')}}" class="space-y-5">@csrf @if($application->exists)@method('PUT')@endif
<section class="card p-6"><h2 class="mb-5 text-sm font-semibold">Cliente y responsabilidad</h2><div class="grid gap-5 md:grid-cols-3"><label class="{{$label}}">Cliente *<select name="client_id" class="{{$input}}" required><option value="">Seleccionar</option>@foreach($clients as $client)@php $blocked=$client->open_loans_count>0 && $client->id!==$application->client_id; @endphp<option value="{{$client->id}}" @selected(old('client_id',$application->client_id)==$client->id) @disabled($blocked)>{{$client->full_name}} · {{$client->code}}{{$blocked?' · crédito vigente':''}}</option>@endforeach</select><span class="mt-1 font-normal text-[10px] text-slate-400">No se puede elegir un cliente con un crédito sin cancelar.</span></label><label class="{{$label}}">Vendedor *<select name="seller_id" class="{{$input}}" required><option value="">Seleccionar</option>@foreach($sellers as $seller)<option value="{{$seller->id}}" @selected(old('seller_id',$application->seller_id)==$seller->id)>{{$seller->user->name}}</option>@endforeach</select></label><div class="{{$label}}"><span>Producto *</span><div class="flex items-end gap-2"><select id="product" name="credit_product_id" class="{{$input}} min-w-0 flex-1" required><option value="">Seleccionar</option>@foreach($products as $product)<option value="{{$product->id}}" data-currency="{{$product->currency}}" data-rate="{{$product->default_interest_rate}}" data-method="{{$product->default_interest_method}}" @selected(old('credit_product_id',$application->credit_product_id)==$product->id)>{{$product->name}}</option>@endforeach</select><div id="vue-product-create" data-endpoint="{{route('products.store')}}" data-csrf="{{csrf_token()}}"></div></div></div></div></section>
<section class="card p-6">
    <h2 class="mb-5 text-sm font-semibold">Condiciones solicitadas</h2>
    @php
        $installmentValue = old('installment_amount');
        if ($installmentValue === null && $application->term && $application->requested_amount) {
            $installmentValue = bcdiv((string) $application->requested_amount, (string) $application->term, 2);
        } elseif ($installmentValue === null) {
            $installmentValue = $application->installment_amount;
        }
    @endphp
    <div class="space-y-5">
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            <label class="{{$label}}">Monto solicitado *<input type="number" step="0.01" min="0.01" name="requested_amount" value="{{old('requested_amount',$application->requested_amount)}}" class="{{$input}}" required></label>
            <label class="{{$label}}">Moneda *<select name="currency" class="{{$input}}"><option value="NIO" @selected(old('currency',$application->currency ?? 'NIO')==='NIO')>Córdobas</option><option value="USD" @selected(old('currency',$application->currency)==='USD')>Dólares</option></select></label>
            <label class="{{$label}}">Fecha de solicitud *<input type="date" name="applied_on" value="{{old('applied_on',$application->applied_on?->format('Y-m-d')??$application->created_at?->format('Y-m-d')??today()->format('Y-m-d'))}}" max="{{today()->format('Y-m-d')}}" class="{{$input}}" required></label>
            <label class="{{$label}}">Tasa anual (%)<input id="rate" type="number" step="0.000001" min="0" name="interest_rate" value="{{old('interest_rate',$application->interest_rate)}}" class="{{$input}}"></label>
        </div>
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            <label class="{{$label}}">Frecuencia *<select name="payment_frequency" class="{{$input}}" required><option value="">Seleccionar</option><option value="daily" @selected(old('payment_frequency',$application->payment_frequency)==='daily')>Diaria</option><option value="weekly" @selected(old('payment_frequency',$application->payment_frequency)==='weekly')>Semanal</option><option value="biweekly" @selected(old('payment_frequency',$application->payment_frequency)==='biweekly')>Quincenal</option><option value="monthly" @selected(old('payment_frequency',$application->payment_frequency)==='monthly')>Mensual</option></select></label>
            <label class="{{$label}}">Monto de cada cuota *<input type="number" step="0.01" min="0.01" name="installment_amount" value="{{$installmentValue}}" class="{{$input}}" required><span class="mt-1 font-normal text-[10px] text-slate-400">Se usa para calcular cuántos pagos serán.</span></label>
            <input type="hidden" name="term" value="{{old('term',$application->term)}}">
            <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 md:col-span-2">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-500">Pagos proyectados</p>
                <p id="payment-count" class="mt-1 text-sm font-semibold text-indigo-800">Indica la frecuencia, la tasa y el monto de cada cuota para ver el total a pagar.</p>
                <p id="payment-detail" class="mt-1 text-[11px] text-indigo-600/80"></p>
            </div>
        </div>
        <label class="{{$label}}">Propósito *<textarea name="purpose" rows="3" class="{{$input}}" required>{{old('purpose',$application->purpose)}}</textarea></label>
    </div>
</section>
@php
    $guaranteeRows=old('guarantors',$application->guarantees->map(fn($g)=>array_merge($g->toArray(),$g->latestEvaluation?->toArray()??[]))->values()->all());
    $guarantorOptions=$guarantors->map(function($g){
        $e=$g->exposureSummary();
        return ['id'=>$g->id,'name'=>$g->full_name,'identity'=>$g->identity_number,'active'=>$e['active_credits'],'total'=>$e['guaranteed_balance'],'clients'=>$e['clients'],'income'=>$e['income'],'expenses'=>$e['expenses'],'available'=>$e['available'],'overdue'=>$e['overdue']?'Sí':'No','evaluated'=>$e['evaluated_at']?->format('d/m/Y')??'Sin evaluación'];
    })->values();
@endphp
<section class="card overflow-hidden"><div class="flex flex-col gap-4 border-b border-slate-100 p-6 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-sm font-semibold">Garantías y fiadores</h2><p class="mt-1 text-[11px] text-slate-400">Cada solicitud conserva su evaluación y documentos de forma independiente.</p></div><label class="flex items-center gap-3 text-xs font-semibold text-slate-600"><input type="hidden" name="requires_guarantor" value="0"><input id="requires-guarantor" type="checkbox" name="requires_guarantor" value="1" @checked(old('requires_guarantor',$application->requires_guarantor)) class="rounded border-slate-300 text-indigo-500">Esta solicitud requiere fiador</label></div><div id="guarantee-rows" class="divide-y divide-slate-100">@foreach($guaranteeRows as $i=>$row)
<div class="guarantee-row grid gap-4 p-6 md:grid-cols-2 lg:grid-cols-4"><label class="{{$label}} lg:col-span-2">Fiador registrado<select name="guarantors[{{$i}}][guarantor_id]" class="guarantor-select {{$input}}"><option value="">Registrar uno nuevo</option>@foreach($guarantors as $candidate)@php $exposure=$candidate->exposureSummary(); @endphp<option value="{{$candidate->id}}" data-active="{{$exposure['active_credits']}}" data-total="{{$exposure['guaranteed_balance']}}" data-clients="{{$exposure['clients']}}" data-income="{{$exposure['income']}}" data-expenses="{{$exposure['expenses']}}" data-available="{{$exposure['available']}}" data-overdue="{{$exposure['overdue']?'Sí':'No'}}" data-evaluated="{{$exposure['evaluated_at']?->format('d/m/Y')??'Sin evaluación'}}" @selected(($row['guarantor_id']??null)==$candidate->id)>{{$candidate->full_name}} · {{$candidate->identity_number}}</option>@endforeach</select></label><div class="exposure rounded-xl bg-amber-50 p-3 text-[11px] text-amber-800 lg:col-span-2">Selecciona un fiador para revisar su exposición.</div>
<label class="{{$label}}">Nombre nuevo<input name="guarantors[{{$i}}][full_name]" value="{{$row['full_name']??''}}" class="{{$input}}"></label><label class="{{$label}}">Cédula<input name="guarantors[{{$i}}][identity_number]" value="{{$row['identity_number']??''}}" class="{{$input}}"></label><label class="{{$label}}">Teléfono<input name="guarantors[{{$i}}][phone]" value="{{$row['phone']??''}}" class="{{$input}}"></label><label class="{{$label}}">Relación *<input name="guarantors[{{$i}}][relationship]" value="{{$row['relationship']??''}}" class="{{$input}}"></label>
<label class="{{$label}}">Monto garantizado *<input type="number" min="0.01" step="0.01" name="guarantors[{{$i}}][guaranteed_amount]" value="{{$row['guaranteed_amount']??''}}" class="{{$input}}"></label><label class="{{$label}}">Tipo<select name="guarantors[{{$i}}][guarantee_type]" class="{{$input}}"><option value="personal">Personal</option><option value="solidary">Solidaria</option><option value="limited">Limitada</option></select></label><label class="{{$label}}">Trabajo<input name="guarantors[{{$i}}][workplace]" value="{{$row['workplace']??''}}" class="{{$input}}"></label><label class="{{$label}}">Ocupación<input name="guarantors[{{$i}}][occupation]" value="{{$row['occupation']??''}}" class="{{$input}}"></label>
<label class="{{$label}}">Ingresos *<input type="number" min="0" step="0.01" name="guarantors[{{$i}}][monthly_income]" value="{{$row['monthly_income']??0}}" class="{{$input}}"></label><label class="{{$label}}">Otros ingresos<input type="number" min="0" step="0.01" name="guarantors[{{$i}}][other_income]" value="{{$row['other_income']??0}}" class="{{$input}}"></label><label class="{{$label}}">Gastos *<input type="number" min="0" step="0.01" name="guarantors[{{$i}}][monthly_expenses]" value="{{$row['monthly_expenses']??0}}" class="{{$input}}"></label><label class="{{$label}}">Aceptación<input type="datetime-local" name="guarantors[{{$i}}][accepted_at]" value="{{isset($row['accepted_at'])?date('Y-m-d\TH:i',strtotime($row['accepted_at'])):''}}" class="{{$input}}"></label>
<label class="{{$label}} lg:col-span-2">Bienes<textarea name="guarantors[{{$i}}][assets]" rows="2" class="{{$input}}">{{data_get($row,'assets_snapshot.description','')}}</textarea></label><label class="{{$label}}">Cédula adjunta<input type="file" name="guarantors[{{$i}}][identity_document]" accept=".pdf,.jpg,.jpeg,.png" class="{{$input}}"></label><label class="{{$label}}">Garantía firmada<input type="file" name="guarantors[{{$i}}][signed_document]" accept=".pdf,.jpg,.jpeg,.png" class="{{$input}}"></label><label class="flex items-center gap-2 text-xs text-rose-600"><input type="checkbox" name="guarantors[{{$i}}][has_overdue_obligations]" value="1">Tiene obligaciones vencidas</label><label class="{{$label}} lg:col-span-2">Notas de evaluación<textarea name="guarantors[{{$i}}][evaluation_notes]" rows="2" class="{{$input}}">{{$row['notes']??''}}</textarea></label><div class="flex items-end justify-end"><button type="button" class="remove-guarantee text-xs font-semibold text-rose-500">Quitar de esta solicitud</button></div></div>@endforeach</div><div class="p-5"><button id="add-guarantee" type="button" class="rounded-xl bg-[#eeebff] px-4 py-2.5 text-xs font-semibold text-indigo-600">+ Agregar fiador</button></div></section>
<section class="card p-6"><div class="grid gap-5 md:grid-cols-2"><label class="{{$label}}">Observaciones del vendedor<textarea name="seller_notes" rows="3" class="{{$input}}">{{old('seller_notes',$application->seller_notes)}}</textarea></label><label class="{{$label}}">Observaciones del analista<textarea name="analyst_notes" rows="3" class="{{$input}}">{{old('analyst_notes',$application->analyst_notes)}}</textarea></label><label class="{{$label}}">Estado<select name="status" class="{{$input}}"><option value="draft">Borrador</option><option value="submitted">Enviada</option><option value="review">En revisión</option></select></label></div></section><div class="flex justify-end gap-2"><a href="{{route('applications.index')}}" class="rounded-xl border border-slate-200 px-5 py-2.5 text-xs font-semibold text-slate-500">Cancelar</a><button class="rounded-xl bg-indigo-500 px-5 py-2.5 text-xs font-semibold text-white">Guardar solicitud</button></div></form>

@if(false)
<div id="product-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="product-modal-title">
<section class="modal-panel w-full max-w-2xl overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-2xl"><header class="flex items-start justify-between border-b bg-gradient-to-r from-indigo-50 to-white px-4 py-3"><div><p class="text-[9px] font-bold uppercase tracking-wider text-indigo-600">Nuevo producto crediticio</p><h2 id="product-modal-title" class="mt-0.5 text-base font-bold">Configurar producto</h2><p class="mt-0.5 text-[10px] text-slate-500">Completa identidad, condiciones y orden de aplicación.</p></div><button id="close-product-modal" type="button" class="grid h-8 w-8 place-items-center rounded-full bg-white text-lg text-slate-500 shadow-sm transition hover:bg-rose-50 hover:text-rose-600">×</button></header>
<div class="product-modal-body max-h-[72vh] overflow-y-auto p-4"><div id="product-errors" class="mb-3 hidden rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700"></div><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
<label class="field-label">Código *<input id="new-product-code" class="control" maxlength="30"></label><label class="field-label sm:col-span-2">Nombre *<input id="new-product-name" class="control" maxlength="150"></label><label class="field-label">Moneda *<select id="new-product-currency" class="control"><option value="NIO">NIO</option><option value="USD">USD</option></select></label><label class="field-label">Plazo mínimo *<input id="new-product-min-term" type="number" min="1" class="control"></label><label class="field-label">Plazo máximo *<input id="new-product-max-term" type="number" min="1" class="control"></label>
<fieldset class="rounded-xl border p-3 sm:col-span-2"><legend class="px-1 text-[10px] font-bold text-slate-500">Frecuencias permitidas *</legend><div class="grid grid-cols-2 gap-2 text-xs"><label><input type="checkbox" class="product-frequency" value="daily"> Diaria</label><label><input type="checkbox" class="product-frequency" value="weekly"> Semanal</label><label><input type="checkbox" class="product-frequency" value="biweekly"> Quincenal</label><label><input type="checkbox" class="product-frequency" value="monthly"> Mensual</label></div></fieldset>
<fieldset class="rounded-xl border p-3"><legend class="px-1 text-[10px] font-bold text-slate-500">Métodos permitidos *</legend><div class="space-y-2 text-xs"><label class="block"><input type="checkbox" class="product-method" value="flat"> Plano</label><label class="block"><input type="checkbox" class="product-method" value="declining_balance"> Saldo decreciente</label><label class="block"><input type="checkbox" class="product-method" value="french"> Cuota nivelada</label></div></fieldset>
<label class="field-label">Tasa predeterminada (%)<input id="new-product-rate" type="number" min="0" step="0.000001" class="control"></label><label class="field-label">Método predeterminado<select id="new-product-default-method" class="control"><option value="">Sin definir</option><option value="flat">Plano</option><option value="declining_balance">Saldo decreciente</option><option value="french">Cuota nivelada</option></select></label><label class="field-label">Gasto administrativo *<input id="new-product-fee" type="number" min="0" step="0.01" class="control"></label><label class="field-label">Método de mora<select id="new-product-delinquency-method" class="control"><option value="">Sin definir</option><option value="none">Sin mora</option><option value="daily_percentage">Porcentaje diario</option><option value="fixed">Monto fijo</option></select></label><label class="field-label">Tasa / monto de mora<input id="new-product-delinquency-rate" type="number" min="0" step="0.000001" class="control"></label>
<fieldset class="rounded-xl border p-3 sm:col-span-2 lg:col-span-3"><legend class="px-1 text-[10px] font-bold text-slate-500">Prioridad de aplicación de pagos *</legend><div class="grid gap-2 sm:grid-cols-4">@for($i=0;$i<4;$i++)<label class="text-[10px] text-slate-500">Prioridad {{$i+1}}<select class="product-priority control"><option value="">Seleccionar</option><option value="delinquency">Mora</option><option value="fees">Cargos</option><option value="interest">Interés</option><option value="principal">Principal</option></select></label>@endfor</div></fieldset>
</div></div><footer class="flex items-center justify-between gap-2 border-t bg-slate-50 px-4 py-3"><p class="hidden text-[9px] text-slate-400 sm:block">Los campos con * son obligatorios.</p><div class="flex gap-2"><button id="cancel-product-modal" type="button" class="btn-secondary">Cancelar</button><button id="save-product" type="button" class="btn-primary">Guardar y seleccionar</button></div></footer></section></div>
<div id="product-toast" class="fixed bottom-5 right-5 z-[120] hidden items-center gap-3 rounded-xl border border-emerald-200 bg-white px-4 py-3 text-xs font-semibold text-emerald-700 shadow-xl"><span class="grid h-6 w-6 place-items-center rounded-full bg-emerald-100">✓</span><span>Producto creado y seleccionado.</span></div>
@endif
<script>
const productDefaults=@json($productDefaults);
const lockInput=(input,value,locked)=>{input.value=value??'';input.readOnly=locked;input.classList.toggle('product-field-locked',locked);input.setAttribute('aria-readonly',locked?'true':'false')};
const lockSelect=(select,value,locked)=>{let hidden=document.getElementById(`locked-${select.name}`);if(!hidden){hidden=document.createElement('input');hidden.type='hidden';hidden.id=`locked-${select.name}`;hidden.name=select.name;select.after(hidden)}select.value=value??'';select.disabled=locked;hidden.disabled=!locked;hidden.value=select.value;select.classList.toggle('product-field-locked',locked);select.setAttribute('aria-disabled',locked?'true':'false')};
const applyProductDefaults=event=>{const option=event.target.selectedOptions[0],defaults=productDefaults[event.target.value]||{};const selected=Boolean(event.target.value);if(!option)return;const currency=option.dataset.currency||defaults.currency||'',rate=option.dataset.rate||defaults.rate||'';lockSelect(document.querySelector('[name="currency"]'),currency,selected&&currency!=='');lockInput(document.getElementById('rate'),rate,selected&&rate!=='')};
document.getElementById('product').addEventListener('change',applyProductDefaults);
if(false){
const productModal=document.getElementById('product-modal'),productErrors=document.getElementById('product-errors');
const showProductModal=()=>{productModal.classList.remove('hidden');productModal.classList.add('flex');document.body.style.overflow='hidden';document.getElementById('new-product-code').focus()};
const hideProductModal=()=>{productModal.classList.add('hidden');productModal.classList.remove('flex');productErrors.classList.add('hidden');document.body.style.overflow=''};
const resetProductModal=()=>{productModal.querySelectorAll('input:not([type="checkbox"])').forEach(input=>input.value='');productModal.querySelectorAll('input[type="checkbox"]').forEach(input=>input.checked=false);productModal.querySelectorAll('select').forEach(select=>select.selectedIndex=0)};
const showProductToast=()=>{const toast=document.getElementById('product-toast');toast.classList.remove('hidden');toast.classList.add('flex');setTimeout(()=>{toast.classList.add('hidden');toast.classList.remove('flex')},2800)};
document.getElementById('open-product-modal').addEventListener('click',showProductModal);document.getElementById('close-product-modal').addEventListener('click',hideProductModal);document.getElementById('cancel-product-modal').addEventListener('click',hideProductModal);productModal.addEventListener('click',event=>{if(event.target===productModal)hideProductModal()});
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!productModal.classList.contains('hidden'))hideProductModal()});
document.getElementById('save-product').addEventListener('click',async event=>{const button=event.currentTarget;button.disabled=true;button.textContent='Guardando…';productErrors.classList.add('hidden');const value=id=>document.getElementById(id).value;const payload={code:value('new-product-code'),name:value('new-product-name'),currency:value('new-product-currency'),minimum_term:value('new-product-min-term'),maximum_term:value('new-product-max-term'),allowed_frequencies:[...document.querySelectorAll('.product-frequency:checked')].map(input=>input.value),allowed_interest_methods:[...document.querySelectorAll('.product-method:checked')].map(input=>input.value),default_interest_rate:value('new-product-rate')||null,default_interest_method:value('new-product-default-method')||null,default_administrative_fee:value('new-product-fee'),delinquency_method:value('new-product-delinquency-method')||null,delinquency_rate:value('new-product-delinquency-rate')||null,payment_allocation_order:[...document.querySelectorAll('.product-priority')].map(select=>select.value),is_active:true};try{const response=await fetch(@json(route('products.store')),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(payload)});const data=await response.json();if(!response.ok)throw data;hideProductModal();const product=data.product,option=new Option(product.name,product.id,true,true);option.dataset.rate=product.default_interest_rate||'';option.dataset.method=product.default_interest_method||'';option.dataset.fee=product.default_administrative_fee||'0';document.getElementById('product').add(option);document.getElementById('product').dispatchEvent(new Event('change'));resetProductModal();showProductToast()}catch(error){const messages=Object.values(error.errors||{error:[error.message||'No fue posible guardar el producto.']}).flat();productErrors.innerHTML='<strong>Revisa la configuración:</strong><ul class="mt-1 list-disc pl-5">'+messages.map(message=>`<li>${message}</li>`).join('')+'</ul>';productErrors.classList.remove('hidden');productErrors.scrollIntoView({behavior:'smooth',block:'nearest'})}finally{button.disabled=false;button.textContent='Guardar y seleccionar'}});
}
const rows=document.getElementById('guarantee-rows');let guaranteeIndex={{count($guaranteeRows)}};
const options=@json($guarantorOptions);
const bindRow=row=>{const select=row.querySelector('.guarantor-select'),box=row.querySelector('.exposure');select?.addEventListener('change',()=>{const o=select.selectedOptions[0];box.textContent=o?.value?`Créditos activos: ${o.dataset.active} · Saldo garantizado: C$ ${o.dataset.total} · Clientes: ${o.dataset.clients} · Ingresos/Gastos: C$ ${o.dataset.income} / C$ ${o.dataset.expenses} · Disponible: C$ ${o.dataset.available} · Vencidas: ${o.dataset.overdue} · Última evaluación: ${o.dataset.evaluated}`:'Se registrará un fiador nuevo y una evaluación independiente.';});select?.dispatchEvent(new Event('change'));row.querySelector('.remove-guarantee')?.addEventListener('click',()=>row.remove())};
rows.querySelectorAll('.guarantee-row').forEach(bindRow);
document.getElementById('add-guarantee').addEventListener('click',()=>{const i=guaranteeIndex++;const optionHtml=options.map(o=>`<option value="${o.id}" data-active="${o.active}" data-total="${o.total}" data-clients="${o.clients}" data-income="${o.income}" data-expenses="${o.expenses}" data-available="${o.available}" data-overdue="${o.overdue}" data-evaluated="${o.evaluated}">${o.name} · ${o.identity||''}</option>`).join('');rows.insertAdjacentHTML('beforeend',`<div class="guarantee-row grid gap-4 p-6 md:grid-cols-2 lg:grid-cols-4"><label class="{{$label}} lg:col-span-2">Fiador registrado<select name="guarantors[${i}][guarantor_id]" class="guarantor-select {{$input}}"><option value="">Registrar uno nuevo</option>${optionHtml}</select></label><div class="exposure rounded-xl bg-amber-50 p-3 text-[11px] text-amber-800 lg:col-span-2"></div><label class="{{$label}}">Nombre nuevo<input name="guarantors[${i}][full_name]" class="{{$input}}"></label><label class="{{$label}}">Cédula<input name="guarantors[${i}][identity_number]" class="{{$input}}"></label><label class="{{$label}}">Relación *<input name="guarantors[${i}][relationship]" class="{{$input}}"></label><label class="{{$label}}">Monto garantizado *<input type="number" step="0.01" name="guarantors[${i}][guaranteed_amount]" class="{{$input}}"></label><input type="hidden" name="guarantors[${i}][guarantee_type]" value="personal"><label class="{{$label}}">Ingresos *<input type="number" step="0.01" name="guarantors[${i}][monthly_income]" value="0" class="{{$input}}"></label><label class="{{$label}}">Gastos *<input type="number" step="0.01" name="guarantors[${i}][monthly_expenses]" value="0" class="{{$input}}"></label><label class="{{$label}}">Aceptación<input type="datetime-local" name="guarantors[${i}][accepted_at]" class="{{$input}}"></label><label class="{{$label}}">Garantía firmada<input type="file" name="guarantors[${i}][signed_document]" class="{{$input}}"></label><button type="button" class="remove-guarantee text-xs font-semibold text-rose-500">Quitar</button></div>`);bindRow(rows.lastElementChild)});
const amountInput=document.querySelector('[name="requested_amount"]');
const installmentInput=document.querySelector('[name="installment_amount"]');
const frequencyInput=document.querySelector('[name="payment_frequency"]');
const rateInput=document.getElementById('rate');
const productInput=document.getElementById('product');
const currencyInput=document.querySelector('[name="currency"]');
const termInput=document.querySelector('[name="term"]');
const paymentCount=document.getElementById('payment-count');
const paymentDetail=document.getElementById('payment-detail');
const frequencyWords={daily:'diarios',weekly:'semanales',biweekly:'quincenales',monthly:'mensuales'};
const frequencySingular={daily:'diario',weekly:'semanal',biweekly:'quincenal',monthly:'mensual'};
const periodsPerYear=@json(collect(\App\Services\AmortizationCalculator::FREQUENCIES)->map(fn ($item) => $item['periods_per_year']));
const productMethodMap=@json(\App\Services\AmortizationCalculator::PRODUCT_METHODS);
const round2=value=>Math.round(value*100)/100;
const money=(value,currency)=>new Intl.NumberFormat('es-NI',{style:'currency',currency:currency||'NIO'}).format(value);
const resolveMethod=()=>{const option=productInput.selectedOptions[0],defaults=productDefaults[productInput.value]||{};return productMethodMap[option?.dataset.method||defaults.method||'']||'level_payment'};
const projectFromInstallment=()=>{
    const principal=round2(Number(amountInput.value));
    const quoted=round2(Number(installmentInput.value));
    const frequency=frequencyInput.value;
    const annualRate=Number(rateInput.value||0);
    if(!(principal>0&&quoted>0&&frequency&&periodsPerYear[frequency]))return null;
    const periods=Math.ceil(principal/quoted);
    if(periods>365)return {error:'Aumenta el monto de cada cuota. El crédito no puede superar 365 pagos.'};
    const method=resolveMethod();
    const periodicRate=(annualRate||0)/100/Number(periodsPerYear[frequency]);
    const levelPayment=method==='level_payment'?(periodicRate===0?principal/periods:principal*periodicRate/(1-Math.pow(1+periodicRate,-periods))):0;
    const constantPrincipal=principal/periods;
    const flatInterest=principal*(annualRate||0)/100/Number(periodsPerYear[frequency]);
    let balance=principal,totalInterest=0,totalPayment=0,regularPayment=0,lastPayment=0;
    for(let number=1;number<=periods;number++){
        let interest=method==='flat_interest'?flatInterest:balance*periodicRate;
        let principalPayment=method==='level_payment'?levelPayment-interest:constantPrincipal;
        interest=round2(interest);
        principalPayment=number===periods?balance:Math.min(balance,round2(principalPayment));
        const payment=round2(principalPayment+interest);
        balance=round2(Math.max(0,balance-principalPayment));
        totalInterest=round2(totalInterest+interest);
        totalPayment=round2(totalPayment+payment);
        if(number===1)regularPayment=payment;
        lastPayment=payment;
    }
    return {periods,regularPayment,lastPayment,totalInterest,totalPayment};
};
const refreshPaymentCount=()=>{
    paymentDetail.textContent='';
    const projection=projectFromInstallment();
    if(!projection){
        paymentCount.textContent='Indica la frecuencia, la tasa y el monto de cada cuota para ver el total a pagar.';
        return;
    }
    if(projection.error){
        paymentCount.textContent=projection.error;
        termInput.value='';
        return;
    }
    const frequency=frequencyInput.value;
    const currency=currencyInput.value||'NIO';
    termInput.value=projection.periods;
    const countLabel=projection.periods===1?`1 pago ${frequencySingular[frequency]}`:`${projection.periods} pagos ${frequencyWords[frequency]}`;
    paymentCount.textContent=`Total a pagar cada cuota con interés: ${money(projection.regularPayment,currency)}`;
    const parts=[countLabel,`Interés ${money(projection.totalInterest,currency)}`,`Total ${money(projection.totalPayment,currency)}`];
    if(round2(projection.lastPayment)!==round2(projection.regularPayment)){
        parts.push(`Última cuota ${money(projection.lastPayment,currency)}`);
    }
    paymentDetail.textContent=parts.join(' · ');
};
[amountInput,installmentInput,frequencyInput,rateInput,productInput,currencyInput].forEach(input=>input.addEventListener('input',refreshPaymentCount));
[frequencyInput,productInput,currencyInput].forEach(input=>input.addEventListener('change',refreshPaymentCount));
productInput.dispatchEvent(new Event('change'));
refreshPaymentCount();
</script>
@endsection
