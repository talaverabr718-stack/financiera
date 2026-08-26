<div class="mb-6 flex flex-wrap gap-2">
@foreach(['index'=>'Resumen','brand'=>'Marca','modules'=>'Módulos','permissions'=>'Permisos','appearance'=>'Apariencia','general'=>'Institución','financial'=>'Financiera','accounting'=>'Contabilidad','sequences'=>'Consecutivos'] as $route=>$label)
<a href="{{route('settings.'.$route)}}" class="rounded-lg px-3.5 py-2 text-xs font-semibold {{request()->routeIs('settings.'.$route)||request()->routeIs('settings.'.$route.'.*')?'bg-indigo-500 text-white':'border border-slate-200 bg-white text-slate-600 hover:border-indigo-200'}}">{{$label}}</a>
@endforeach
</div>
