@props(['post', 'user', 'events'])

@php
    $currentReaction = $post->reactions->first()?->type;
    $reactionOptions = [
        'like' => ['icon' => '👍', 'label' => 'Like'],
        'love' => ['icon' => '💚', 'label' => 'Love'],
        'celebrate' => ['icon' => '🎉', 'label' => 'Celebrate'],
    ];
@endphp

<article class="relative rounded-xl border border-[#397565]/15 bg-white shadow-[0_14px_38px_rgba(18,16,23,.07)]" data-feed-post>
    <header class="flex items-center gap-3 p-4">
        <x-student-avatar :user="$post->author" />

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <strong class="truncate text-sm">{{ $post->author->full_name }}</strong>
                @if($post->is_official)
                    <span class="rounded-full bg-[#C6F24E]/40 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-[#397565]">Official announcement</span>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-1.5 text-[10px] text-[#121017]/40">
                <time datetime="{{ $post->reviewed_at?->toIso8601String() }}">
                    {{ $post->reviewed_at?->diffForHumans() }}
                </time>
                <span aria-hidden="true">·</span>
                <span class="font-bold text-[#397565]">{{ str($post->category)->replace('-', ' ')->title() }}</span>
                @if($post->event)
                    <span aria-hidden="true">·</span>
                    <span class="truncate">{{ $post->event->title }}</span>
                @endif
            </div>
        </div>

        @if($post->user_id === $user->id)
            <details class="relative z-20">
                <summary
                    class="grid h-11 w-11 cursor-pointer list-none place-items-center rounded-lg text-xl font-black text-[#397565] transition hover:bg-[#397565]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#397565]"
                    aria-label="Edit post"
                    title="Edit post"
                >
                    <span aria-hidden="true">•••</span>
                </summary>

                <form
                    class="absolute right-0 top-12 z-30 grid w-[min(420px,calc(100vw-2rem))] gap-3 rounded-xl border border-[#397565]/15 bg-white p-4 shadow-[0_20px_55px_rgba(18,16,23,.2)]"
                    method="POST"
                    action="{{ route('student.posts.update', $post) }}"
                    enctype="multipart/form-data"
                >
                    @csrf
                    @method('PUT')

                    <div>
                        <strong class="text-sm">Edit post</strong>
                        <p class="mt-1 text-[10px] font-bold text-[#FF6B2C]">
                            Saving changes returns this post to Pending for adviser review.
                        </p>
                    </div>

                    <textarea class="min-h-24 rounded-lg border border-[#121017]/10 bg-[#F7F4ED]/70 p-3 text-sm outline-none focus:border-[#397565]/40 focus:ring-2 focus:ring-[#397565]/10" name="content" required>{{ $post->content }}</textarea>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <select class="h-10 rounded-lg border border-[#121017]/10 bg-white px-3 text-xs" name="event_id">
                            <option value="">General community</option>
                            @foreach($events as $event)
                                <option value="{{ $event->id }}" @selected($post->event_id === $event->id)>{{ $event->title }}</option>
                            @endforeach
                        </select>

                        <select class="h-10 rounded-lg border border-[#121017]/10 bg-white px-3 text-xs" name="category">
                            @foreach(\App\Models\Post::CATEGORIES as $category)
                                <option value="{{ $category }}" @selected($post->category === $category)>
                                    {{ str($category)->replace('-', ' ')->title() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <input class="text-xs" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                    <button class="min-h-10 rounded-lg bg-[#397565] px-4 text-xs font-black text-white transition hover:bg-[#2f6658]">Save and resubmit</button>
                </form>
            </details>
        @endif
    </header>

    <div class="px-4 pb-4">
        <p class="whitespace-pre-line text-sm leading-6">{{ $post->content }}</p>
    </div>

    @if($post->image_path)
        <x-post-image data-post-media :post="$post" :alt="$post->author->full_name.' post image'" />
    @endif

    <section aria-label="Post reactions and comments">
        @if($post->reactions_count || $post->comments_count)
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-[11px] text-[#121017]/50">
                <span>
                    @if($post->reactions_count)
                        <span aria-hidden="true">👍 💚</span>
                        {{ $post->reactions_count }} {{ str('reaction')->plural($post->reactions_count) }}
                    @endif
                </span>
                @if($post->comments_count)
                    <a class="hover:text-[#397565] hover:underline" href="#comment-post-{{ $post->id }}">
                        {{ $post->comments_count }} {{ str('comment')->plural($post->comments_count) }}
                    </a>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-2 border-y border-[#397565]/10 px-2 py-1">
            <details class="group relative">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-center gap-2 rounded-lg text-xs font-black transition hover:bg-[#397565]/8 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#397565] {{ $currentReaction ? 'text-[#397565]' : 'text-[#121017]/60' }}">
                    <span aria-hidden="true">{{ $currentReaction ? $reactionOptions[$currentReaction]['icon'] : '♡' }}</span>
                    {{ $currentReaction ? $reactionOptions[$currentReaction]['label'] : 'React' }}
                </summary>

                <div class="absolute bottom-12 left-2 z-20 flex gap-1 rounded-full border border-[#397565]/15 bg-white p-1.5 shadow-[0_12px_35px_rgba(18,16,23,.18)]">
                    @foreach($reactionOptions as $type => $option)
                        <form method="POST" action="{{ route('student.posts.reactions.update', $post) }}">
                            @csrf
                            @method('PUT')
                            <button
                                class="grid h-11 w-11 place-items-center rounded-full text-xl transition hover:-translate-y-1 hover:bg-[#E3F0EC] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#397565] {{ $currentReaction === $type ? 'bg-[#C6F24E]/35' : '' }}"
                                type="submit"
                                name="reaction"
                                value="{{ $type }}"
                                aria-label="{{ $currentReaction === $type ? 'Remove '.$option['label'].' reaction' : 'React with '.$option['label'] }}"
                                title="{{ $option['label'] }}"
                            >
                                {{ $option['icon'] }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </details>

            <a class="flex min-h-11 items-center justify-center gap-2 rounded-lg text-xs font-black text-[#121017]/60 transition hover:bg-[#397565]/8 hover:text-[#397565] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#397565]" href="#comment-post-{{ $post->id }}">
                <svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.5 9.5 0 0 1-4-.9l-5 1 1.2-4.2A8.4 8.4 0 1 1 21 11.5Z" />
                </svg>
                Comment
            </a>
        </div>

        <div class="space-y-3 p-4">
            @if($post->comments_count > $post->comments->count())
                <p class="text-[11px] font-bold text-[#397565]">
                    Showing the latest {{ $post->comments->count() }} of {{ $post->comments_count }} comments
                </p>
            @endif

            @foreach($post->comments->sortBy('created_at') as $comment)
                <div class="flex items-start gap-2.5">
                    <x-student-avatar :user="$comment->author" size="h-8 w-8" />
                    <div class="min-w-0 flex-1">
                        <div class="rounded-xl bg-[#F0F5F3] px-3 py-2">
                            <div class="flex items-start justify-between gap-2">
                                <strong class="truncate text-[11px]">{{ $comment->author->full_name }}</strong>
                                @if($comment->user_id === $user->id)
                                    <form method="POST" action="{{ route('student.posts.comments.destroy', $comment) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-[10px] font-bold text-[#121017]/40 hover:text-[#c84510]" type="submit" aria-label="Delete your comment" title="Delete comment">Delete</button>
                                    </form>
                                @endif
                            </div>
                            <p class="mt-0.5 break-words text-xs leading-5">{{ $comment->body }}</p>
                        </div>
                        <time class="ml-2 mt-1 block text-[9px] text-[#121017]/40" datetime="{{ $comment->created_at->toIso8601String() }}">
                            {{ $comment->created_at->diffForHumans() }}
                        </time>
                    </div>
                </div>
            @endforeach

            <form class="flex items-center gap-2" method="POST" action="{{ route('student.posts.comments.store', $post) }}">
                @csrf
                <x-student-avatar :user="$user" size="h-8 w-8" />
                <label class="sr-only" for="comment-post-{{ $post->id }}">Write a comment</label>
                <input
                    class="min-h-11 min-w-0 flex-1 rounded-full border border-[#397565]/10 bg-[#F0F5F3] px-4 text-xs outline-none placeholder:text-[#121017]/35 focus:border-[#397565]/40 focus:ring-2 focus:ring-[#397565]/10"
                    id="comment-post-{{ $post->id }}"
                    name="body"
                    type="text"
                    maxlength="500"
                    placeholder="Write a comment…"
                    required
                >
                <button class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#397565] text-white transition hover:bg-[#2f6658] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#397565] focus-visible:ring-offset-2" type="submit" aria-label="Post comment">
                    <svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m4 4 17 8-17 8 3-8-3-8Zm3 8h14" />
                    </svg>
                </button>
            </form>
        </div>
    </section>
</article>
