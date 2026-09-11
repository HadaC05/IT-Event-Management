@props([
    'post',
    'alt' => 'Post attachment',
    'compact' => false,
])

<div {{ $attributes->class([
    'overflow-hidden bg-[#121017]',
    'aspect-video max-h-56 rounded-xl' => $compact,
    'aspect-[4/5] max-h-[700px] w-full border-y border-[#121017]/7' => ! $compact,
]) }}>
    <img
        class="h-full w-full object-contain"
        src="{{ asset('storage/'.$post->image_path) }}"
        alt="{{ $alt }}"
        loading="lazy"
    >
</div>
