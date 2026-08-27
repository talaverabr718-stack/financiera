@php
    $sidebarNavigation = app(\App\Services\NavigationService::class)->forUser(auth()->user());
@endphp
<div
    id="legacy-vue-sidebar"
    data-navigation='@json($sidebarNavigation)'
    data-user='@json(auth()->user()?->only("id", "name", "email"))'
    data-routes='@json(["logout" => route("logout"), "search" => route("search")])'
    data-current-url="{{ request()->fullUrl() }}"
    data-csrf="{{ csrf_token() }}"
></div>
