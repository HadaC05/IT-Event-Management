@props(['user', 'size' => 'h-11 w-11'])
@if($user->profile_photo_path)
    <img {{ $attributes->class([$size, 'shrink-0 rounded-full object-cover ring-2 ring-[#397565]/10']) }} src="{{ asset('storage/'.$user->profile_photo_path) }}" alt="{{ $user->full_name }} profile picture">
@else
    <span {{ $attributes->class([$size, 'grid shrink-0 place-items-center rounded-full bg-[#C6F24E] text-xs font-black text-[#121017] ring-2 ring-[#397565]/10']) }} aria-label="{{ $user->full_name }} profile picture">{{ $user->initials }}</span>
@endif
