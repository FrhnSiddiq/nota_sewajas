@extends('Layouts.app')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">

    <a href="{{ url()->previous() }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-900 mb-5 transition">
        ← Kembali
    </a>

    <div class="card overflow-hidden">

        <div class="flex items-start gap-4 p-6 border-b border-slate-200">
            <div class="text-2xl w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 bg-blue-50 border border-blue-100">
                {{ $notification->icon_name ?? '🔔' }}
            </div>
            <div class="flex-1">
                @if(!$notification->is_read)
                    <span class="inline-block text-[11px] text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full mb-2">
                        ● Belum dibaca
                    </span>
                @endif
                <h1 class="text-slate-900 font-semibold text-lg leading-snug mb-1">
                    {{ $notification->title }}
                </h1>
                <p class="text-xs text-slate-500">
                    {{ $notification->created_at->diffForHumans() }}
                    · {{ $notification->created_at->translatedFormat('d F Y, H:i') }} WIB
                </p>
            </div>
        </div>

        <div class="p-6 space-y-5">

            @if(isset($notification->meta['invoice_no']))
                <span class="inline-flex items-center gap-1.5 text-xs text-blue-700 bg-blue-50 border border-blue-200 px-2.5 py-1 rounded-md">
                    🧾 {{ $notification->meta['invoice_no'] }}
                </span>
            @endif

            <div class="rounded-xl border border-slate-200 border-l-4 border-l-blue-600 bg-white p-4 text-sm leading-relaxed text-slate-700 shadow-sm">
                {!! nl2br(e($notification->message)) !!}
            </div>

            @if(!($notification->meta['broadcast'] ?? false) && !empty($notification->meta))
                <div class="grid grid-cols-2 gap-2 text-sm">
                    @foreach($notification->meta as $key => $value)
                        @if($key !== 'invoice_no')
                            <div class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">
                                <div class="text-[11px] text-slate-500 capitalize mb-0.5">{{ str_replace('_', ' ', $key) }}</div>
                                <div class="text-slate-800 font-medium text-xs">{{ $value }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="flex items-center gap-2 flex-wrap pt-2 border-t border-slate-200">

                @if($notification->action_url)
                    <a href="{{ $notification->action_url }}"
                       class="btn-primary inline-flex items-center gap-1.5 text-sm px-4 py-2 rounded-lg transition">
                        Lihat Detail →
                    </a>
                @endif

                @if(!$notification->is_read)
                    <form action="{{ route('notifications.read', $notification->id) }}" method="POST">
                        @csrf
                        <button class="btn-secondary text-sm px-4 py-2 rounded-lg transition">
                            Tandai dibaca
                        </button>
                    </form>
                @endif

                <form action="{{ route('notifications.destroy', $notification->id) }}" method="POST" id="deleteNotification_{{ $notification->id }}" class="hidden">
                    @csrf @method('DELETE')
                </form>
                <button type="button" onclick="confirmDelete('deleteNotification_{{ $notification->id }}', 'notifikasi ini')" class="text-sm text-red-600 border border-red-200 px-4 py-2 rounded-lg hover:text-red-700 hover:bg-red-50 transition">
                    Hapus
                </button>

            </div>
        </div>

        <div class="px-6 py-3 text-[11px] text-slate-500 bg-slate-50 border-t border-slate-200">
            Dikirim {{ $notification->created_at->translatedFormat('d F Y · H:i') }} WIB
        </div>

    </div>
</div>
@endsection