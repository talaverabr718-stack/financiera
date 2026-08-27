<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php $brand = \Illuminate\Support\Facades\Schema::hasTable('system_settings') ? \App\Models\SystemSetting::where('group','brand')->pluck('value','key') : collect(); @endphp
    <title>@yield('title') · {{$brand['system_name'] ?? 'Centro Financiero 360'}}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
@php
    $appearance = \Illuminate\Support\Facades\Schema::hasTable('system_settings') ? \App\Models\SystemSetting::where('group','appearance')->pluck('value','key') : collect();
    $primary = $appearance['primary_color'] ?? '#6366f1'; $sidebarColor = $appearance['sidebar_color'] ?? '#10172a'; $accent = $appearance['accent_color'] ?? '#22d3ee'; $background = $appearance['background_color'] ?? '#f5f7fb';
    $fonts = ['sans'=>'Inter,ui-sans-serif,system-ui,sans-serif','instrument'=>'Instrument Sans,ui-sans-serif,system-ui,sans-serif','inter'=>'Inter,ui-sans-serif,system-ui,sans-serif','system'=>'system-ui,-apple-system,Segoe UI,sans-serif','humanist'=>'Segoe UI,Trebuchet MS,sans-serif','nunito'=>'Nunito,Trebuchet MS,sans-serif','poppins'=>'Poppins,Century Gothic,sans-serif','roboto'=>'Roboto,Arial,sans-serif','lato'=>'Lato,Arial,sans-serif','serif'=>'Georgia,Cambria,serif','merriweather'=>'Merriweather,Georgia,serif','georgia'=>'Georgia,Times New Roman,serif','mono'=>'Cascadia Code,Consolas,monospace'];
    $radius = ['soft'=>'8px','rounded'=>'16px','square'=>'2px'][$appearance['border_radius'] ?? 'soft'];
@endphp
<style>:root{--app-primary:{{$primary}};--app-sidebar:{{$sidebarColor}};--app-accent:{{$accent}};--app-background:{{$background}};--app-radius:{{$radius}}}body{font-family:{!!$fonts[$appearance['font_family']??'inter']!!};background:var(--app-background)!important}#sidebar{background:var(--app-sidebar)!important}.btn-primary{background:var(--app-primary)!important}.eyebrow{color:var(--app-primary)!important}.card,.rounded-xl{border-radius:var(--app-radius)!important}#sidebar .bg-gradient-to-br{background:linear-gradient(135deg,var(--app-primary),var(--app-accent))!important}body.density-compact main{font-size:12px}body.density-compact .card{line-height:1.25}</style>
<body class="client-module density-{{$appearance['density'] ?? 'comfortable'}}">
<div class="min-h-screen">
    @include('partials.sidebar')
    <div class="app-shell min-w-0">
        <header class="app-topbar sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-slate-200/80 bg-white/90 px-3 backdrop-blur-xl sm:px-5 lg:px-6">
            <div class="flex min-w-0 items-center gap-2"><button id="menu" class="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-600 lg:hidden" aria-label="Abrir menú"><i data-lucide="menu" class="icon"></i></button><nav class="hidden min-w-0 items-center gap-2 text-xs text-slate-400 md:flex" aria-label="Breadcrumb"><a href="{{route('dashboard')}}" class="hover:text-indigo-600">Inicio</a><span>/</span><span class="truncate font-medium text-slate-600">@yield('title')</span></nav></div>
            <form action="{{route('search')}}" class="relative max-w-xl flex-1"><i data-lucide="search" class="icon pointer-events-none absolute left-3 top-2.5 text-slate-400"></i><input name="q" value="{{request()->routeIs('search')?request('q'):''}}" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-3 text-xs outline-none transition focus:border-indigo-300 focus:bg-white" placeholder="Buscar cliente, cédula, crédito o solicitud…" aria-label="Búsqueda global"></form>
            <div class="flex items-center gap-2"><button class="relative grid h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Actividades"><i data-lucide="bell" class="icon"></i></button><span class="hidden text-[11px] text-slate-400 xl:inline">{{now()->translatedFormat('d M Y')}}</span><button class="grid h-9 w-9 place-items-center rounded-full bg-indigo-50 text-[10px] font-semibold text-indigo-700" aria-label="Perfil" title="{{auth()->user()?->name??'Administrador'}}">{{collect(explode(' ',auth()->user()?->name??'Administrador'))->take(2)->map(fn($v)=>mb_substr($v,0,1))->join('')}}</button><form method="POST" action="{{route('logout')}}" class="m-0">@csrf<button type="submit" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" title="Cerrar sesión"><i data-lucide="log-out" class="icon"></i><span class="hidden sm:inline">Salir</span></button></form></div>
        </header>
        <main class="mx-auto max-w-[1500px] p-3 sm:p-5 lg:p-6">
            @include('partials.print-brand')
            @if(session('success'))<div class="mb-4 flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status"><i data-lucide="circle-check" class="icon"></i>{{session('success')}}</div>@endif
            @yield('content')
        </main>
    </div>
</div>
<div id="overlay" class="fixed inset-0 z-40 hidden bg-slate-950/60 lg:hidden"></div>
</body>
</html>
