@extends('clients.layout')
@section('title','Configuración')
@section('content')
<div class="mb-6"><h1 class="text-[28px] font-semibold">Configuración del sistema</h1><p class="mt-1 text-sm text-slate-500">Administra los parámetros institucionales, financieros y operativos desde un solo lugar.</p></div>
@include('settings._tabs')
@php $sections=[
['brand','building-2','Marca del sistema','Nombre, descripción y logotipo institucional.'],
['modules','layout-dashboard','Módulos','Activa, oculta y ordena las áreas funcionales.'],
['permissions','users','Permisos','Define accesos de consulta y administración por usuario.'],
['appearance','pencil','Apariencia','Colores, tipografía, densidad y estilo visual.'],
['general','building-2','Institución','Identidad, contacto, zona horaria y formato de fechas.'],
['financial','sliders-horizontal','Políticas financieras','Productos, tasas, mora, plazos y prioridad de pagos.'],
['accounting','book-open','Integración contable','Cuentas puente para desembolsos, cartera e ingresos.'],
['sequences','file-check-2','Consecutivos','Prefijos y longitud de documentos del sistema.'],
]; @endphp
<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">@foreach($sections as [$key,$icon,$title,$description])<a href="{{route('settings.'.$key)}}" class="card group p-5 transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg"><div class="flex items-start justify-between"><span class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-50 text-indigo-600"><i data-lucide="{{$icon}}" class="icon"></i></span><span class="rounded-full px-2 py-1 text-[10px] font-semibold {{$checks[$key]?'bg-emerald-50 text-emerald-600':'bg-amber-50 text-amber-700'}}">{{$checks[$key]?'Configurado':'Requiere atención'}}</span></div><h2 class="mt-5 text-sm font-semibold text-slate-800">{{$title}}</h2><p class="mt-2 text-xs leading-5 text-slate-500">{{$description}}</p><p class="mt-5 text-xs font-semibold text-indigo-500">Abrir configuración →</p></a>@endforeach</div>
<section class="card mt-6 p-5"><h2 class="text-sm font-semibold">Protecciones activas</h2><div class="mt-4 grid gap-3 md:grid-cols-3"><p class="rounded-xl bg-slate-50 p-4 text-xs text-slate-600"><strong class="block text-slate-800">Moneda base: NIO</strong>No se modifica desde la interfaz.</p><p class="rounded-xl bg-slate-50 p-4 text-xs text-slate-600"><strong class="block text-slate-800">Reglas sensibles</strong>No se asignan tasas ni cuentas automáticamente.</p><p class="rounded-xl bg-slate-50 p-4 text-xs text-slate-600"><strong class="block text-slate-800">Trazabilidad</strong>Los cambios guardan fecha y usuario cuando existe sesión.</p></div></section>
@endsection
