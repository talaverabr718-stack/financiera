@extends('clients.layout')
@php
    $modules = [
        'caja' => ['Caja y liquidaciones', 'Control de efectivo, arqueos, cierres y liquidaciones.', 'wallet-cards'],
        'contabilidad' => ['Contabilidad', 'Asientos, conciliaciones y validaciones contables.', 'book-open'],
        'reportes' => ['Reportes', 'Indicadores operativos y financieros exportables.', 'chart-no-axes-combined'],
    ];
    [$title, $description, $icon] = $modules[$section];
@endphp
@section('title',$title)
@section('content')
<x-page-header :title="$title" :description="$description" :eyebrow="$brand['system_name'] ?? 'Centro Financiero 360'" />
<section class="card grid min-h-[480px] place-items-center p-8 text-center"><div class="max-w-md"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-indigo-50 text-indigo-600"><i data-lucide="{{$icon}}" class="h-6 w-6"></i></span><h2 class="mt-5 text-base font-semibold">Módulo disponible para integración</h2><p class="mt-2 text-sm leading-6 text-slate-500">La navegación está habilitada, pero esta área necesita sus modelos y flujos operativos antes de mostrar información financiera real.</p><a href="{{route('dashboard')}}" class="btn-soft mt-5">Volver a {{$brand['system_name'] ?? 'Centro Financiero'}}</a></div></section>
@endsection
