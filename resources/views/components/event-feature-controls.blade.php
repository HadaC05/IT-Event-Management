@props(['event', 'action'])

@php
    $canBeFeatured = in_array($event->status?->label, ['upcoming', 'ongoing'], true)
        && $event->end_at->isFuture();
@endphp

<section class="mb-6 rounded-2xl border border-[#397565]/25 bg-[#121017] p-5 text-[#F3F0E9] shadow-sm">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-xl">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-[10px] font-black uppercase tracking-[.16em] text-[#C6F24E]">Homepage carousel</p>
                @if ($event->is_featured)
                    <span class="rounded-full bg-[#C6F24E] px-2.5 py-1 text-[9px] font-black uppercase text-[#121017]">Featured</span>
                @endif
            </div>
            <h2 class="mt-2 text-lg font-black">Feature this existing event</h2>
            <p class="mt-1 text-xs leading-5 text-white/55">
                The carousel reuses this event’s title, description, poster, schedule, and status.
                Completed or expired events disappear automatically.
            </p>
        </div>

        @if ($canBeFeatured)
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <form class="flex flex-col gap-2 sm:flex-row sm:items-end" method="POST" action="{{ $action }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="is_featured" value="1">

                    <label class="grid gap-1">
                        <span class="text-[9px] font-black uppercase tracking-wider text-white/50">Order</span>
                        <input
                            class="h-10 w-full rounded-lg border border-white/15 bg-white/10 px-3 text-xs font-bold text-white outline-none focus:border-[#C6F24E] sm:w-24"
                            name="featured_order"
                            type="number"
                            min="1"
                            max="999"
                            value="{{ old('featured_order', $event->featured_order) }}"
                            placeholder="Auto"
                        >
                    </label>

                    <label class="grid gap-1">
                        <span class="text-[9px] font-black uppercase tracking-wider text-white/50">Show until</span>
                        <input
                            class="h-10 rounded-lg border border-white/15 bg-white/10 px-3 text-xs font-bold text-white outline-none focus:border-[#C6F24E]"
                            name="featured_until"
                            type="datetime-local"
                            value="{{ old('featured_until', $event->featured_until?->format('Y-m-d\TH:i')) }}"
                            max="{{ $event->end_at->format('Y-m-d\TH:i') }}"
                        >
                    </label>

                    <button class="min-h-10 rounded-lg bg-[#C6F24E] px-4 text-xs font-black text-[#121017] transition hover:bg-white" type="submit">
                        {{ $event->is_featured ? 'Update feature' : 'Feature event' }}
                    </button>
                </form>

                @if ($event->is_featured)
                    <form method="POST" action="{{ $action }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_featured" value="0">
                        <button class="min-h-10 rounded-lg border border-white/15 px-4 text-xs font-black text-white/70 transition hover:border-[#FF6B2C] hover:text-[#FF6B2C]" type="submit">
                            Remove
                        </button>
                    </form>
                @endif
            </div>
        @else
            <p class="rounded-xl bg-white/[.07] px-4 py-3 text-xs font-bold text-white/60">
                Only upcoming or ongoing events can be featured.
            </p>
        @endif
    </div>

    <x-form-error class="mt-3" name="is_featured" />
    <x-form-error class="mt-3" name="featured_order" />
    <x-form-error class="mt-3" name="featured_until" />
</section>
