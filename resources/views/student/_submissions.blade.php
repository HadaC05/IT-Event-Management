@if($myPosts->isNotEmpty())
<section class="rounded-3xl border border-[#397565]/15 bg-[#397565]/8 p-5 shadow-[0_12px_35px_rgba(18,16,23,.05)]">
    <div class="flex items-start gap-3">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[#397565] text-white"><svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5V4Zm3 4h8m-8 4h8m-8 4h5"/></svg></span>
        <div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-2"><h2 class="font-black">Pending posts</h2><span class="rounded-full bg-[#C6F24E] px-2.5 py-1 text-[10px] font-black text-[#121017]">{{ $myPosts->count() }}</span></div><p class="mt-1 text-xs leading-5 text-[#121017]/50">{{ $myPosts->where('status','pending')->count() }} waiting · {{ $myPosts->where('status','rejected')->count() }} rejected</p></div>
    </div>
    <button class="mt-4 flex min-h-11 w-full items-center justify-center rounded-xl bg-white text-xs font-black text-[#397565] shadow-sm hover:bg-[#F7F4ED]" type="button" data-submissions-open>View submissions</button>
</section>

<dialog class="m-auto max-h-[calc(100vh_-_2rem)] w-[min(760px,calc(100%_-_2rem))] overflow-hidden rounded-3xl border-0 bg-white p-0 text-[#121017] shadow-[0_28px_90px_rgba(18,16,23,.3)] backdrop:bg-[#0d1714]/75 backdrop:backdrop-blur-sm dark:bg-[#17231f] dark:text-[#eef5f2]" data-submissions-dialog aria-labelledby="profile-submissions-title">
    <header class="flex items-start justify-between gap-4 border-b border-[#121017]/8 p-5 sm:p-6"><div><div class="flex items-center gap-2"><h2 class="text-xl font-black" id="profile-submissions-title">Your submissions</h2><span class="rounded-full bg-[#C6F24E] px-2.5 py-1 text-[10px] font-black text-[#121017]">{{ $myPosts->count() }}</span></div><p class="mt-1 text-xs text-[#121017]/45">Only you can see these review updates.</p></div><button class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#F7F4ED] text-xl hover:bg-[#397565]/10" type="button" data-submissions-close aria-label="Close submissions">&times;</button></header>
    <div class="max-h-[calc(100vh_-_9rem)] space-y-3 overflow-y-auto p-5 sm:p-6">
        @foreach($myPosts as $post)
        <article class="rounded-2xl border p-4 {{ $post->status==='rejected'?'border-[#FF6B2C]/25 bg-[#FF6B2C]/5':'border-[#397565]/15 bg-[#397565]/5' }}">
            <div class="flex items-center justify-between gap-3"><span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase {{ $post->status==='rejected'?'bg-[#FF6B2C]/15 text-[#c84510]':'bg-[#C6F24E]/35 text-[#397565]' }}">{{ $post->status==='pending'?'Waiting for adviser approval':'Rejected' }}</span><time class="text-[10px] text-[#121017]/40">{{ $post->created_at->diffForHumans() }}</time></div>
            <p class="mt-3 whitespace-pre-line text-sm leading-6">{{ $post->content }}</p>
            @if($post->image_path)<x-post-image class="mt-3" :post="$post" compact />@endif
            @if($post->rejection_reason)<p class="mt-3 rounded-xl bg-white px-3 py-2 text-xs"><strong>Adviser note:</strong> {{ $post->rejection_reason }}</p>@endif
            @if($post->status==='pending')
            <div class="mt-3 flex items-start gap-2">
                <details class="min-w-0 flex-1"><summary class="inline-flex min-h-10 cursor-pointer items-center rounded-xl border border-[#121017]/10 bg-white px-4 text-xs font-black">Edit</summary><form class="mt-3 grid gap-3 rounded-xl bg-white p-3" method="POST" action="{{ route('student.posts.update',$post) }}" enctype="multipart/form-data">@csrf @method('PUT')<textarea class="min-h-24 rounded-xl border border-[#121017]/10 p-3 text-sm" name="content" required>{{ $post->content }}</textarea><div class="grid gap-2 sm:grid-cols-2"><select class="h-10 rounded-xl border border-[#121017]/10 px-3 text-xs" name="event_id"><option value="">General community</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected($post->event_id===$event->id)>{{ $event->title }}</option>@endforeach</select><select class="h-10 rounded-xl border border-[#121017]/10 px-3 text-xs" name="category">@foreach(\App\Models\Post::CATEGORIES as $category)<option value="{{ $category }}" @selected($post->category===$category)>{{ str($category)->replace('-',' ')->title() }}</option>@endforeach</select></div><input class="text-xs" type="file" name="image" accept="image/jpeg,image/png,image/webp"><button class="min-h-10 rounded-xl bg-[#397565] px-4 text-xs font-black text-white">Save changes</button></form></details>
                <form method="POST" action="{{ route('student.posts.destroy',$post) }}" onsubmit="return confirm('Delete this pending post?')">@csrf @method('DELETE')<button class="min-h-10 rounded-xl border border-[#FF6B2C]/25 bg-white px-4 text-xs font-black text-[#c84510]">Delete</button></form>
            </div>
            @endif
        </article>
        @endforeach
    </div>
</dialog>
@endif
