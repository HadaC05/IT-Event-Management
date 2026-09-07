@props(['name'])

@if ($errors->has($name))
    <small {{ $attributes->class('flex items-start gap-1 text-xs font-semibold leading-5 text-[#FF6B2C]') }} role="alert"><span aria-hidden="true">⚠</span><span>{{ $errors->first($name) }}</span></small>
@endif
