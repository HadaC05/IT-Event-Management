@props(['events'])

<section class="overflow-hidden rounded-xl border border-[#397565]/25 bg-[#121017] text-white shadow-[0_16px_45px_rgba(18,16,23,.14)]" data-student-feature-carousel aria-labelledby="featured-events-heading">
    <header class="flex items-center justify-between gap-4 border-b border-white/10 px-5 py-4">
        <div>
            <p class="text-[9px] font-black uppercase tracking-[.17em] text-[#C6F24E]">Featured in CITE</p>
            <h2 class="mt-1 text-lg font-black" id="featured-events-heading">Event spotlight</h2>
        </div>
        <span class="rounded-full bg-white/10 px-3 py-1 text-[10px] font-bold text-white/65">
            {{ $events->count() }} featured
        </span>
    </header>

    <div class="relative">
        @forelse ($events as $event)
            <article
                class="relative min-h-60 overflow-hidden bg-cover bg-center p-5 sm:min-h-68 sm:p-6 {{ $loop->first ? '' : 'hidden' }}"
                data-student-feature-slide
                aria-hidden="{{ $loop->first ? 'false' : 'true' }}"
                @if ($event->poster_path)
                    style="background-image: linear-gradient(90deg, rgba(18,16,23,.94), rgba(18,16,23,.48)), url('{{ asset('storage/'.$event->poster_path) }}')"
                @else
                    style="background-image: radial-gradient(circle at 85% 20%, rgba(198,242,78,.25), transparent 30%), linear-gradient(135deg, #397565, #121017 72%)"
                @endif
            >
                <div class="relative flex min-h-52 max-w-2xl flex-col justify-end sm:min-h-56">
                    <div class="mb-auto flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-[#C6F24E] px-3 py-1 text-[9px] font-black uppercase tracking-wider text-[#121017]">
                            {{ $event->schedule_state }}
                        </span>
                        @if ($event->type)
                            <span class="rounded-full bg-white/15 px-3 py-1 text-[9px] font-black uppercase tracking-wider backdrop-blur">
                                {{ $event->type->label }}
                            </span>
                        @endif
                    </div>
                    <h3 class="text-3xl font-black tracking-[-.04em] sm:text-4xl">{{ $event->title }}</h3>
                    @if ($event->description)
                        <p class="mt-2 line-clamp-2 text-sm leading-6 text-white/70">{{ $event->description }}</p>
                    @endif
                    <p class="mt-3 text-xs font-bold text-white/75">
                        {{ $event->start_at->format('M j, Y · g:i A') }} · {{ $event->location ?: 'CITE Campus' }}
                    </p>
                </div>
            </article>
        @empty
            <div class="grid min-h-52 place-items-center px-6 py-9 text-center">
                <div>
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-[#C6F24E] text-xl text-[#121017]">✦</span>
                    <h3 class="mt-4 text-xl font-black">Event spotlight is ready</h3>
                    <p class="mt-1 text-sm text-white/55">Featured upcoming events will appear in this carousel.</p>
                </div>
            </div>
        @endforelse

        @if ($events->count() > 1)
            <div class="absolute bottom-5 right-5 flex items-center gap-2">
                <button class="grid h-11 w-11 place-items-center rounded-full bg-black/45 text-white backdrop-blur transition hover:bg-[#C6F24E] hover:text-[#121017]" type="button" data-student-feature-previous aria-label="Previous featured event">←</button>
                <div class="flex gap-1.5 rounded-full bg-black/40 px-3 py-2 backdrop-blur">
                    @foreach ($events as $event)
                        <button class="h-2.5 w-2.5 rounded-full {{ $loop->first ? 'bg-[#C6F24E]' : 'bg-white/45' }}" type="button" data-student-feature-dot="{{ $loop->index }}" aria-label="Show {{ $event->title }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"></button>
                    @endforeach
                </div>
                <button class="grid h-11 w-11 place-items-center rounded-full bg-black/45 text-white backdrop-blur transition hover:bg-[#C6F24E] hover:text-[#121017]" type="button" data-student-feature-next aria-label="Next featured event">→</button>
            </div>
        @endif
    </div>
</section>
