@props(['title', 'description' => null, 'eyebrow' => null])
<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @if($eyebrow)<p class="eyebrow">{{$eyebrow}}</p>@endif
        <h1 class="page-title {{$eyebrow?'mt-1':''}}">{{$title}}</h1>
        @if($description)<p class="page-description">{{$description}}</p>@endif
    </div>
    @if(isset($actions))<div class="flex shrink-0 flex-wrap items-center gap-2">{{$actions}}</div>@endif
</div>
