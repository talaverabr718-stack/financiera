@php
    $moduleState = \Illuminate\Support\Facades\Schema::hasTable('system_modules') ? \App\Models\SystemModule::with(['users'=>fn($query)=>auth()->check()?$query->whereKey(auth()->id()):$query->whereRaw('1=0')])->get()->keyBy('key') : collect();
    $brand = \Illuminate\Support\Facades\Schema::hasTable('system_settings') ? \App\Models\SystemSetting::where('group','brand')->pluck('value','key') : collect();
    $brandLogoVersion = \Illuminate\Support\Facades\Schema::hasTable('system_settings') ? optional(\App\Models\SystemSetting::where('key','logo_path')->first())->updated_at?->timestamp : null;
    $navigation = [
        ['group'=>'Resumen','items'=>[
            ['route' => 'dashboard', 'label' => 'Panel general', 'icon' => 'layout-dashboard'],
        ]],
        ['group'=>'Crédito','items'=>[
            ['route' => 'clients.index', 'label' => 'Clientes', 'icon' => 'users'],
            ['route' => 'applications.index', 'label' => 'Solicitudes', 'icon' => 'file-check-2'],
            ['route' => 'loans.index', 'label' => 'Cartera', 'icon' => 'landmark'],
        ]],
        ['group'=>'Operación','items'=>[
            ['route' => 'routes.index', 'label' => 'Rutas', 'icon' => 'map'],
            ['route' => 'collections.index', 'label' => 'Cobranza', 'icon' => 'hand-coins'],
            ['section' => 'caja', 'label' => 'Caja', 'icon' => 'wallet-cards'],
        ]],
        ['group'=>'Administración','items'=>[
            ['route' => 'collaborators.index', 'label' => 'Colaboradores', 'icon' => 'contact-round'],
            ['route' => 'accounting.dashboard', 'label' => 'Contabilidad', 'icon' => 'book-open'],
            ['route' => 'reports.index', 'label' => 'Reportes', 'icon' => 'chart-no-axes-combined'],
            ['route' => 'settings.index', 'label' => 'Configuración', 'icon' => 'settings'],
        ]],
    ];
@endphp
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex w-60 -translate-x-full flex-col bg-[#10172a] text-white transition-all duration-200 lg:translate-x-0">
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/[.06] px-4">@if(filled($brand['logo_path']??null))<img src="{{route('settings.logo',['v'=>$brandLogoVersion])}}" alt="Logo" class="h-9 w-9 shrink-0 rounded-lg bg-white object-contain p-1">@else<div class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-cyan-400 text-xs font-bold shadow-lg shadow-indigo-950/30">360</div>@endif<div class="sidebar-brand-copy min-w-0"><p class="truncate text-sm font-semibold">{{$brand['system_name']??'Centro Financiero'}}</p><p class="truncate text-[10px] text-slate-500">{{$brand['system_tagline']??'Operación 360°'}}</p></div><button id="sidebar-close" class="ml-auto grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Cerrar menú"><i data-lucide="x" class="icon"></i></button></div>
    <nav class="flex-1 space-y-3 overflow-y-auto px-3 py-3" aria-label="Navegación principal">
        @foreach($navigation as $group)
        <div><p class="sidebar-group px-3 pb-1.5 pt-1 text-[9px] font-semibold uppercase tracking-[.16em] text-slate-600">{{$group['group']}}</p><div class="space-y-0.5">
        @foreach($group['items'] as $item)
            @php
                $moduleKey = ['dashboard'=>'dashboard','clients.index'=>'clients','applications.index'=>'applications','loans.index'=>'loans','routes.index'=>'routes','collections.index'=>'collections','collaborators.index'=>'collaborators','accounting.dashboard'=>'accounting','reports.index'=>'reports'][$item['route']??''] ?? ($item['section']??null);
                $module = $moduleState->get($moduleKey);
                $grant = $module?->users->first()?->pivot;
                $allowed = !$module || ($module->is_enabled && $module->is_visible && (!$grant || $grant->can_view));
                $href = isset($item['route']) ? route($item['route']) : route('section', $item['section']);
                $active = isset($item['route'])
                    ? request()->routeIs($item['route']) || ($item['route'] === 'clients.index' && request()->routeIs('clients.*')) || ($item['route'] === 'loans.index' && request()->routeIs('loans.*')) || ($item['route'] === 'collaborators.index' && request()->routeIs('collaborators.*')) || ($item['route'] === 'applications.index' && request()->routeIs('applications.*', 'products.*')) || ($item['route'] === 'routes.index' && request()->routeIs('routes.*')) || ($item['route'] === 'collections.index' && request()->routeIs('collections.*')) || ($item['route'] === 'accounting.dashboard' && request()->routeIs('accounting.*')) || ($item['route'] === 'reports.index' && request()->routeIs('reports.*')) || ($item['route'] === 'settings.index' && request()->routeIs('settings.*'))
                    : request()->routeIs('section') && request()->route('section') === $item['section'];
            @endphp
            @continue(!$allowed)
            <a href="{{$href}}" class="nav-link {{$active?'nav-link-active':''}}" title="{{$item['label']}}" @if($active) aria-current="page" @endif><i data-lucide="{{$item['icon']}}" class="icon shrink-0"></i><span class="sidebar-label truncate">{{$item['label']}}</span></a>
        @endforeach
        </div></div>
        @endforeach
    </nav>
    <div class="border-t border-white/[.07] p-3"><div class="flex items-center gap-3 rounded-lg p-2"><div class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-700 text-[10px] font-semibold">{{collect(explode(' ',auth()->user()?->name??'Administrador'))->take(2)->map(fn($v)=>mb_substr($v,0,1))->join('')}}</div><div class="sidebar-profile-copy min-w-0"><p class="truncate text-xs font-semibold">{{auth()->user()?->name??'Administrador'}}</p><p class="truncate text-[10px] text-slate-500">Oficina central</p></div></div></div>
</aside>
