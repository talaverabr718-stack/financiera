@extends('clients.layout') @section('title',$client->exists?'Editar cliente':'Nuevo cliente') @section('content')
<header class="client-form-hero mb-5 overflow-hidden rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-600 via-violet-600 to-cyan-500 p-5 text-white shadow-xl shadow-indigo-900/15 sm:p-6"><div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"><div><a href="{{route('clients.index')}}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur hover:bg-white/20"><i data-lucide="arrow-left" class="icon"></i>Volver a clientes</a><p class="mt-5 text-[10px] font-bold uppercase tracking-[.18em] text-indigo-100">Expediente digital</p><h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">{{$client->exists?'Actualizar cliente':'Registrar nuevo cliente'}}</h1><p class="mt-2 max-w-2xl text-xs leading-5 text-indigo-100 sm:text-sm">Completa la información personal, ubicación, capacidad económica y bienes declarados en cuatro pasos.</p></div><div class="grid shrink-0 grid-cols-2 gap-2 text-center"><div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 backdrop-blur"><strong class="block text-lg">4</strong><span class="text-[10px] text-indigo-100">Pasos guiados</span></div><div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 backdrop-blur"><strong class="block text-lg">360°</strong><span class="text-[10px] text-indigo-100">Perfil integral</span></div></div></div></header>
<form id="client-form" method="POST" action="{{$client->exists?route('clients.update',$client):route('clients.store')}}" class="space-y-5">@csrf @if($client->exists)@method('PUT')@endif
@if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"><p class="font-semibold">Revisa la información:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@php $input='control'; $label='field-label'; $locations=config('nicaragua.locations'); $selectedDepartment=old('department',$client->department?:'Estelí'); $selectedMunicipality=old('municipality',$client->municipality?:'Estelí'); $selectedNeighborhood=old('neighborhood',$client->neighborhood); @endphp
<div class="client-stepper card overflow-hidden p-3 sm:p-4">
    <div class="grid grid-cols-4 gap-1" aria-label="Progreso del formulario">
        @foreach([1=>['user-round','Datos básicos'],2=>['briefcase-business','Economía'],3=>['gem','Bienes'],4=>['notebook-pen','Observaciones']] as $step=>$item)
            <button type="button" data-step-button="{{$step}}" class="group flex min-w-0 flex-col items-center gap-1.5 rounded-xl px-2 py-2.5 text-slate-400 transition hover:bg-indigo-50 sm:flex-row sm:justify-center sm:gap-2">
                <span class="step-icon grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-slate-100 transition"><i data-lucide="{{$item[0]}}" class="h-3.5 w-3.5"></i></span>
                <span class="truncate text-[10px] font-semibold sm:text-xs">{{$item[1]}}</span>
            </button>
        @endforeach
    </div>
    <div class="mx-2 mt-2 h-1 overflow-hidden rounded-full bg-slate-100"><div id="form-progress" class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-400 transition-all duration-300"></div></div>
</div>
<section class="card p-5 lg:p-7"><div class="mb-5 flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-xl bg-[#eeebff] text-indigo-600"><i data-lucide="user-round" class="icon"></i></span><div><h2 class="text-sm font-semibold">Información personal</h2><p class="text-[11px] text-slate-400">Identificación y datos de contacto</p></div></div><div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3"><label class="{{$label}} lg:col-span-2">Nombre completo *<input name="full_name" value="{{old('full_name',$client->full_name)}}" class="{{$input}}" required></label><label class="{{$label}}">Cédula *<input id="identity-number" name="identity_number" value="{{old('identity_number',$client->identity_number)}}" class="{{$input}}" placeholder="000-000000-0000A" maxlength="16" pattern="[0-9]{3}-[0-9]{6}-[0-9]{4}[A-Za-z]" required><span class="mt-1 block text-[10px] text-slate-400">Formato nicaragüense; se valida la letra de control.</span></label><label class="{{$label}}">Fecha de nacimiento *<input id="birth-date" type="date" name="birth_date" value="{{old('birth_date',$client->birth_date?->format('Y-m-d'))}}" max="{{now()->subDay()->format('Y-m-d')}}" class="{{$input}}" required><span class="mt-1 block text-[10px] text-slate-400">Se completa con los seis dígitos centrales de la cédula y puedes corregirla manualmente.</span></label><label class="{{$label}}">Teléfono<input name="phone" value="{{old('phone',$client->phone)}}" class="{{$input}}"></label><label class="{{$label}}">Correo electrónico<input type="email" name="email" value="{{old('email',$client->email)}}" class="{{$input}}"></label></div></section>
<section class="card p-5 lg:p-7"><div class="mb-5 flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-xl bg-[#eeebff] text-indigo-600"><i data-lucide="map-pin" class="icon"></i></span><div><h2 class="text-sm font-semibold">Ubicación</h2><p class="text-[11px] text-slate-400">Dirección para visitas y cobranza</p></div></div><div class="grid gap-5 md:grid-cols-3"><label class="{{$label}}">Departamento *<select id="department" name="department" class="{{$input}}" required>@foreach($locations as $department=>$municipalities)<option value="{{$department}}" @selected($selectedDepartment===$department)>{{$department}}</option>@endforeach</select></label><label class="{{$label}}">Municipio *<select id="municipality" name="municipality" class="{{$input}}" data-selected="{{$selectedMunicipality}}" required></select></label><label class="{{$label}}">Barrio o comunidad *<select id="neighborhood" name="neighborhood" class="{{$input}}" data-selected="{{$selectedNeighborhood}}" required></select></label><label class="{{$label}} md:col-span-3">Dirección detallada *<textarea name="address" rows="3" class="{{$input}}" required>{{old('address',$client->address)}}</textarea></label></div></section>
<section class="card p-5 lg:p-7"><div class="mb-5 flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-xl bg-[#eeebff] text-indigo-600"><i data-lucide="briefcase-business" class="icon"></i></span><div><h2 class="text-sm font-semibold">Trabajo y capacidad económica</h2><p class="text-[11px] text-slate-400">Ingresos mensuales, gastos y estabilidad laboral</p></div></div><div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3"><label class="{{$label}}">Actividad económica<input name="economic_activity" value="{{old('economic_activity',$client->economic_activity)}}" class="{{$input}}"></label><label class="{{$label}}">Empresa, trabajo o negocio<input name="workplace" value="{{old('workplace',$client->workplace)}}" class="{{$input}}"></label><label class="{{$label}}">Cargo u ocupación<input name="job_position" value="{{old('job_position',$client->job_position)}}" class="{{$input}}"></label><label class="{{$label}} lg:col-span-2">Dirección del trabajo o negocio<input name="workplace_address" value="{{old('workplace_address',$client->workplace_address)}}" class="{{$input}}"></label><label class="{{$label}}">Antigüedad laboral (meses)<input type="number" min="0" name="employment_duration_months" value="{{old('employment_duration_months',$client->employment_duration_months)}}" class="{{$input}}"></label><label class="{{$label}}">Ingresos mensuales *<input type="number" step="0.01" min="0" name="estimated_income" value="{{old('estimated_income',$client->estimated_income)}}" class="{{$input}}" required></label><label class="{{$label}}">Otros ingresos<input type="number" step="0.01" min="0" name="other_income" value="{{old('other_income',$client->other_income?:0)}}" class="{{$input}}"></label><label class="{{$label}}">Gastos mensuales *<input type="number" step="0.01" min="0" name="estimated_expenses" value="{{old('estimated_expenses',$client->estimated_expenses)}}" class="{{$input}}" required></label><label class="{{$label}}">Vivienda<select name="housing_status" class="{{$input}}"><option value="">Seleccionar</option>@foreach(['owned'=>'Propia','rented'=>'Alquilada','family'=>'Familiar','financed'=>'Financiada','other'=>'Otra'] as $v=>$l)<option value="{{$v}}" @selected(old('housing_status',$client->housing_status)===$v)>{{$l}}</option>@endforeach</select></label><label class="{{$label}}">Personas dependientes<input type="number" min="0" name="dependents" value="{{old('dependents',$client->dependents?:0)}}" class="{{$input}}"></label><label class="{{$label}}">Estado<select name="status" class="{{$input}}">@foreach(['active'=>'Activo','inactive'=>'Inactivo','blocked'=>'Bloqueado'] as $v=>$l)<option value="{{$v}}" @selected(old('status',$client->status?:'active')===$v)>{{$l}}</option>@endforeach</select></label>@unless($client->exists)<label class="{{$label}}">Vendedor responsable *<select name="seller_id" class="{{$input}}" required><option value="">Seleccionar</option>@foreach($sellers as $seller)<option value="{{$seller->id}}" @selected(old('seller_id')==$seller->id)>{{$seller->user->name}} · {{$seller->code}}</option>@endforeach</select></label>@endunless</div></section>
@php $assetRows=old('assets',$client->assets->toArray()); @endphp
<section class="card overflow-hidden"><div class="flex items-center justify-between gap-3 border-b border-slate-100 p-5 lg:px-7"><div class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#eeebff] text-indigo-600"><i data-lucide="gem" class="icon"></i></span><div><h2 class="text-sm font-semibold">Pertenencias y bienes</h2><p class="mt-1 text-[11px] text-slate-400">Prendas, vehículos, propiedades, inventario u otros bienes declarados.</p></div></div><button type="button" onclick="addAsset()" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-[11px] font-semibold text-indigo-600 transition hover:bg-indigo-100"><i data-lucide="plus" class="h-3.5 w-3.5"></i><span class="hidden sm:inline">Agregar bien</span></button></div><div id="assets" class="divide-y divide-slate-100">@foreach($assetRows as $i=>$asset)<div class="asset-row grid gap-4 p-5 md:grid-cols-5 lg:px-7"><label class="{{$label}}">Tipo<select name="assets[{{$i}}][type]" class="{{$input}}">@foreach(['jewelry'=>'Prenda / joya','vehicle'=>'Vehículo','property'=>'Propiedad','appliance'=>'Electrodoméstico','machinery'=>'Maquinaria','livestock'=>'Ganado','inventory'=>'Inventario','other'=>'Otro'] as $v=>$l)<option value="{{$v}}" @selected(($asset['type']??'')===$v)>{{$l}}</option>@endforeach</select></label><label class="{{$label}} md:col-span-2">Descripción<input name="assets[{{$i}}][description]" value="{{$asset['description']??''}}" class="{{$input}}"></label><label class="{{$label}}">Valor estimado<input type="number" min="0" step="0.01" name="assets[{{$i}}][estimated_value]" value="{{$asset['estimated_value']??''}}" class="{{$input}}"></label><div class="flex items-end"><button type="button" onclick="this.closest('.asset-row').remove()" class="asset-remove inline-flex items-center gap-1 text-[11px] font-semibold text-rose-500"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i>Eliminar</button></div></div>@endforeach</div></section>
<section class="card p-5 lg:p-7"><label class="{{$label}}">Observaciones generales<textarea name="notes" rows="3" class="{{$input}}">{{old('notes',$client->notes)}}</textarea></label><label class="mt-4 flex items-start gap-2 text-xs text-slate-500"><input type="checkbox" name="confirm_duplicate" value="1" class="mt-0.5 rounded border-slate-300 text-indigo-500">Confirmo que revisé posibles coincidencias de teléfono y deseo continuar.</label></section>
<div class="client-form-actions sticky bottom-3 z-20 flex items-center justify-between rounded-2xl border border-indigo-200 bg-white/95 p-3 shadow-xl shadow-indigo-900/10 backdrop-blur">
    <a href="{{route('clients.index')}}" class="client-cancel-action inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-100 hover:text-rose-700"><i data-lucide="x" class="h-3.5 w-3.5"></i>Cancelar</a>
    <div class="flex gap-2">
        <button id="step-back" type="button" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600"><i data-lucide="arrow-left" class="icon"></i>Anterior</button>
        <button id="step-next" type="button" class="flex items-center gap-2 rounded-lg bg-indigo-500 px-5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-indigo-500/20">Continuar<i data-lucide="arrow-right" class="icon"></i></button>
        <button id="client-submit" type="button" class="hidden items-center gap-2 rounded-lg bg-indigo-500 px-5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-indigo-500/20"><i data-lucide="save" class="icon"></i>{{$client->exists?'Revisar cambios':'Revisar y registrar'}}</button><button id="confirmed-submit" type="submit" class="hidden" aria-hidden="true" tabindex="-1"></button>
    </div>
</div></form>
<div id="client-review-modal" class="fixed inset-0 z-[80] hidden" role="dialog" aria-modal="true" aria-labelledby="review-title"><div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-review-close></div><div class="relative mx-auto flex min-h-full max-w-2xl items-center p-4"><article class="w-full overflow-hidden rounded-2xl bg-white shadow-2xl"><header class="bg-gradient-to-br from-slate-800 to-[#47666d] p-6 text-white"><button type="button" class="float-right grid h-9 w-9 place-items-center rounded-full bg-white/10 hover:bg-white/20" data-review-close><i data-lucide="x" class="icon"></i></button><span class="grid h-11 w-11 place-items-center rounded-xl bg-white/10"><i data-lucide="file-check-2" class="h-5 w-5"></i></span><h2 id="review-title" class="mt-4 text-xl font-semibold">Revisar expediente</h2><p class="mt-1 text-xs text-slate-300">Confirma los datos principales antes de guardar.</p></header><div class="p-6"><div class="grid gap-3 sm:grid-cols-2">@foreach(['review-name'=>'Nombre completo','review-identity'=>'Cédula','review-phone'=>'Teléfono','review-location'=>'Ubicación','review-income'=>'Ingresos mensuales','review-expenses'=>'Gastos mensuales'] as $id=>$reviewLabel)<div class="rounded-xl border border-slate-100 bg-slate-50 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">{{$reviewLabel}}</p><p id="{{$id}}" class="mt-1 text-xs font-semibold text-slate-700">—</p></div>@endforeach</div><div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-[11px] leading-5 text-amber-800">Al confirmar se guardará el expediente y su asignación inicial de cartera. Podrás editar los datos posteriormente conservando el historial.</div><div class="mt-5 flex justify-end gap-2"><button type="button" class="btn-secondary" data-review-close>Volver a editar</button><button id="confirm-client-save" type="button" class="btn-primary"><i data-lucide="circle-check" class="icon"></i>Confirmar y guardar</button></div></div></article></div></div>
<script>
const locations = @json($locations);
const identityNumber=document.getElementById('identity-number');
const birthDate=document.getElementById('birth-date');
const formatIdentityNumber=()=>{
    const compact=identityNumber.value.toUpperCase().replace(/[^0-9A-Z]/g,'');
    const digits=compact.replace(/\D/g,'').slice(0,13);
    const letter=digits.length===13?(compact.match(/[A-Z]/)?.[0]??''):'';
    let formatted=digits.slice(0,3);
    if(digits.length>=3)formatted+=`-${digits.slice(3,9)}`;
    if(digits.length>=9)formatted+=`-${digits.slice(9,13)}`;
    identityNumber.value=formatted+letter;
};
const fillBirthDateFromIdentity=()=>{
    const digits=identityNumber.value.replace(/\D/g,'');
    if(digits.length<9)return;
    const encodedDate=digits.slice(3,9);
    const day=Number(encodedDate.slice(0,2));
    const month=Number(encodedDate.slice(2,4));
    const shortYear=Number(encodedDate.slice(4,6));
    const now=new Date();
    let year=Math.floor(now.getFullYear()/100)*100+shortYear;
    let candidate=new Date(year,month-1,day);
    if(candidate>now){year-=100;candidate=new Date(year,month-1,day);}
    if(candidate.getFullYear()!==year||candidate.getMonth()!==month-1||candidate.getDate()!==day)return;
    birthDate.value=`${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    birthDate.dispatchEvent(new Event('change',{bubbles:true}));
};
const syncIdentityFields=()=>{formatIdentityNumber();fillBirthDateFromIdentity();};
identityNumber.addEventListener('input',syncIdentityFields);
identityNumber.addEventListener('blur',syncIdentityFields);
identityNumber.addEventListener('keydown',event=>{
    const start=identityNumber.selectionStart??0;
    const end=identityNumber.selectionEnd??start;
    if(start!==end)return;
    if(event.key==='Backspace'&&identityNumber.value[start-1]==='-'){
        event.preventDefault();
        identityNumber.setRangeText('',Math.max(0,start-2),start,'end');
        syncIdentityFields();
        identityNumber.setSelectionRange(Math.max(0,start-2),Math.max(0,start-2));
    }else if(event.key==='Delete'&&identityNumber.value[start]==='-'){
        event.preventDefault();
        identityNumber.setRangeText('',start,Math.min(identityNumber.value.length,start+2),'start');
        syncIdentityFields();
        identityNumber.setSelectionRange(start,start);
    }
});
formatIdentityNumber();
const department = document.getElementById('department');
const municipality = document.getElementById('municipality');
const neighborhood = document.getElementById('neighborhood');
const fill = (select, values, selected) => {
    select.innerHTML = Object.keys(values).map(value => `<option value="${value}"${value === selected ? ' selected' : ''}>${value}</option>`).join('');
};
const fillNeighborhoods = selected => fill(neighborhood, Object.fromEntries((locations[department.value]?.[municipality.value] ?? []).map(value => [value, value])), selected);
const fillMunicipalities = (selectedMunicipality, selectedNeighborhood) => { fill(municipality, locations[department.value] ?? {}, selectedMunicipality); fillNeighborhoods(selectedNeighborhood); };
department.addEventListener('change', () => fillMunicipalities('', ''));
municipality.addEventListener('change', () => fillNeighborhoods(''));
fillMunicipalities(municipality.dataset.selected, neighborhood.dataset.selected);
let assetIndex={{count($assetRows)}};
window.addAsset=()=>{document.getElementById('assets').insertAdjacentHTML('beforeend',`<div class="asset-row grid gap-4 p-5 md:grid-cols-5 lg:px-7"><label class="field-label">Tipo<select name="assets[${assetIndex}][type]" class="control"><option value="jewelry">Prenda / joya</option><option value="vehicle">Vehículo</option><option value="property">Propiedad</option><option value="appliance">Electrodoméstico</option><option value="machinery">Maquinaria</option><option value="livestock">Ganado</option><option value="inventory">Inventario</option><option value="other">Otro</option></select></label><label class="field-label md:col-span-2">Descripción<input name="assets[${assetIndex}][description]" class="control"></label><label class="field-label">Valor estimado<input type="number" min="0" step="0.01" name="assets[${assetIndex}][estimated_value]" class="control"></label><div class="flex items-end"><button type="button" onclick="this.closest('.asset-row').remove()" class="asset-remove inline-flex items-center gap-1 text-[11px] font-semibold text-rose-500">Eliminar</button></div></div>`);assetIndex++;window.lucide?.createIcons?.()};
const clientForm = document.getElementById('client-form');
const formSections = [...clientForm.querySelectorAll(':scope > section')];
const stepGroups = [[formSections[0], formSections[1]], [formSections[2]], [formSections[3]], [formSections[4]]];
const stepButtons = [...document.querySelectorAll('[data-step-button]')];
const backButton = document.getElementById('step-back');
const nextButton = document.getElementById('step-next');
const submitButton = document.getElementById('client-submit');
const progress = document.getElementById('form-progress');
const reviewModal = document.getElementById('client-review-modal');
let currentStep = 1;

const validateStep = () => {
    const fields = stepGroups[currentStep - 1].flatMap(section => [...section.querySelectorAll('input, select, textarea')]);
    const invalid = fields.find(field => !field.checkValidity());
    if (invalid) { invalid.reportValidity(); invalid.focus(); return false; }
    return true;
};

const showStep = (step, scroll = false) => {
    currentStep = step;
    formSections.forEach(section => section.classList.add('hidden'));
    stepGroups[step - 1].forEach(section => section.classList.remove('hidden'));
    stepButtons.forEach(button => {
        const active = Number(button.dataset.stepButton) === step;
        const complete = Number(button.dataset.stepButton) < step;
        button.classList.toggle('bg-indigo-50', active);
        button.classList.toggle('text-indigo-600', active || complete);
        button.classList.toggle('text-slate-400', !active && !complete);
        button.querySelector('.step-icon').classList.toggle('bg-indigo-100', active || complete);
    });
    progress.style.width = `${step * 25}%`;
    backButton.classList.toggle('invisible', step === 1);
    nextButton.classList.toggle('hidden', step === 4);
    submitButton.classList.toggle('hidden', step !== 4);
    submitButton.classList.toggle('flex', step === 4);
    if (scroll) clientForm.scrollIntoView({behavior: 'smooth', block: 'start'});
};

backButton.addEventListener('click', () => showStep(currentStep - 1, true));
nextButton.addEventListener('click', () => { if (validateStep()) showStep(currentStep + 1, true); });
stepButtons.forEach(button => button.addEventListener('click', () => {
    const target = Number(button.dataset.stepButton);
    if (target < currentStep || (target === currentStep + 1 && validateStep())) showStep(target, true);
}));
submitButton.addEventListener('click',()=>{if(!validateStep())return;const value=name=>clientForm.elements[name]?.value||'Sin especificar';document.getElementById('review-name').textContent=value('full_name');document.getElementById('review-identity').textContent=value('identity_number');document.getElementById('review-phone').textContent=value('phone');document.getElementById('review-location').textContent=[value('neighborhood'),value('municipality')].filter(v=>v!=='Sin especificar').join(', ')||'Sin especificar';document.getElementById('review-income').textContent=`C$ ${Number(value('estimated_income')||0).toLocaleString('es-NI',{minimumFractionDigits:2})}`;document.getElementById('review-expenses').textContent=`C$ ${Number(value('estimated_expenses')||0).toLocaleString('es-NI',{minimumFractionDigits:2})}`;reviewModal.classList.remove('hidden');document.body.style.overflow='hidden'});
document.querySelectorAll('[data-review-close]').forEach(button=>button.addEventListener('click',()=>{reviewModal.classList.add('hidden');document.body.style.overflow=''}));document.getElementById('confirm-client-save').addEventListener('click',()=>document.getElementById('confirmed-submit').click());document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!reviewModal.classList.contains('hidden')){reviewModal.classList.add('hidden');document.body.style.overflow=''}});
showStep(1);
</script>
@endsection
