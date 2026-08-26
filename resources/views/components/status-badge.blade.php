@props(['status'])
@php
$map=[
 'active'=>['Activo','bg-emerald-50 text-emerald-700'],'approved'=>['Aprobado','bg-emerald-50 text-emerald-700'],'paid'=>['Pagado','bg-emerald-50 text-emerald-700'],'visited'=>['Visitado','bg-emerald-50 text-emerald-700'],
 'pending'=>['Pendiente','bg-amber-50 text-amber-700'],'submitted'=>['Enviada','bg-blue-50 text-blue-700'],'review'=>['En revisión','bg-blue-50 text-blue-700'],'proposed'=>['Propuesto','bg-blue-50 text-blue-700'],
 'rejected'=>['Rechazado','bg-rose-50 text-rose-700'],'delinquent'=>['En mora','bg-rose-50 text-rose-700'],'overdue'=>['Vencido','bg-rose-50 text-rose-700'],'blocked'=>['Bloqueado','bg-rose-50 text-rose-700'],
 'cancelled'=>['Cancelado','bg-slate-100 text-slate-600'],'inactive'=>['Inactivo','bg-slate-100 text-slate-600'],'released'=>['Liberado','bg-slate-100 text-slate-600'],'draft'=>['Borrador','bg-slate-100 text-slate-600'],
]; [$label,$classes]=$map[$status]??[ucfirst((string)$status),'bg-slate-100 text-slate-600'];
@endphp
<span {{$attributes->class(['badge',$classes])}}><i class="status-dot" aria-hidden="true"></i>{{$label}}</span>
