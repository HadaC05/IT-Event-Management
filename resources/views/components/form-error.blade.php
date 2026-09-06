@props(['name'])

@if ($errors->has($name))
    <small {{ $attributes->class('flex items-start gap-1 text-xs font-semibold leading-5 text-red-600') }} role="alert"><span aria-hidden="true">⚠</span><span>{{ $errors->first($name) }}</span></small>
@endif
