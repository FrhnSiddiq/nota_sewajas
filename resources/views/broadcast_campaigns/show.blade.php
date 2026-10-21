@extends('Layouts.app')

@section('title', $campaign->name . ' - SewaJas')
@section('page-title', $campaign->name)
@section('subtitle', 'Detail campaign broadcast')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-extrabold text-slate-950">Detail Campaign</h2>
            <p class="mt-1 text-sm text-slate-500">Dibuat {{ $campaign->created_at->format('d M Y H:i') }} oleh {{ $campaign->creator->name ?? '-' }}</p>
        </div>
        <a href="{{ route('broadcast-campaigns.index') }}" class="btn-ghost inline-flex min-h-11 items-center gap-2 rounded-[12px] px-4">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            Kembali
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-card-sm lg:p-7">
                <h3 class="mb-4 text-base font-extrabold text-slate-900">Informasi Campaign</h3>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Nama</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ $campaign->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Tipe Penerima</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ ucfirst($campaign->recipient_type) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Target Type</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ ucfirst(str_replace('_', ' ', $campaign->target_type)) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Status</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">
                            @if($campaign->status === 'completed')
                                <span class="badge badge-green">Completed</span>
                            @elseif($campaign->status === 'draft')
                                <span class="badge badge-slate">Draft</span>
                            @else
                                <span class="badge badge-blue">{{ ucfirst($campaign->status) }}</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-card-sm lg:p-7">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-slate-900">Pesan</h3>
                    <button type="button" id="editMessageBtn" class="text-xs font-semibold text-blue-600 hover:text-blue-700">
                        Edit Pesan
                    </button>
                </div>
                <div id="messageView">
                    <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ $campaign->resolveMessage() }}</p>
                </div>
                <div id="messageEdit" class="hidden">
                    <textarea id="messageTextarea" rows="6" class="form-input w-full">{{ $campaign->resolveMessage() }}</textarea>
                    <p class="mt-2 text-xs text-slate-500">Variabel tersedia: <code class="bg-slate-100 px-1 rounded">@{{name}}</code> <code class="bg-slate-100 px-1 rounded">@{{phone}}</code> <code class="bg-slate-100 px-1 rounded">@{{sender_name}}</code></p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" id="saveMessageBtn" class="btn-primary px-4 py-2 text-sm">Simpan</button>
                        <button type="button" id="cancelEditBtn" class="btn-ghost inline-flex min-h-11 items-center rounded-[12px] px-4 text-sm">Batal</button>
                    </div>
                </div>
            </div>

        </div>

        <div class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-card-sm">
                <h3 class="mb-4 text-base font-extrabold text-slate-900">Progress</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600">Total Target</span>
                        <span class="font-bold text-slate-900">{{ $campaign->logs->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600">Terkirim</span>
                        <span class="font-bold text-green-700">{{ $campaign->logs->where('status', 'sent')->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600">Terjadwal</span>
                        <span class="font-bold text-blue-700">{{ $campaign->status === 'scheduled' ? $campaign->logs->where('status', 'pending')->count() : 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600">Menunggu Konfirmasi</span>
                        <span class="font-bold text-slate-700">{{ $campaign->status === 'scheduled' ? 0 : $campaign->logs->where('status', 'pending')->count() }}</span>
                    </div>
                    <div class="mt-2 h-3 rounded-full bg-slate-100">
                        @php
                            $total = $campaign->logs->count();
                            $sent = $campaign->logs->where('status', 'sent')->count();
                            $percentage = $total > 0 ? round(($sent / $total) * 100) : 0;
                        @endphp
                        <div class="h-3 rounded-full bg-blue-600 transition-all" style="width: {{ $percentage }}%"></div>
                    </div>
                    <p class="text-xs text-slate-500 text-right">{{ $percentage }}%</p>
                </div>
            </div>

            @if($campaign->template)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-card-sm">
                    <h3 class="mb-3 text-base font-extrabold text-slate-900">Template</h3>
                    <p class="text-sm font-medium text-slate-900">{{ $campaign->template->name }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ $campaign->template->content }}</p>
                </div>
            @endif
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-card-sm lg:p-7">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-extrabold text-slate-900">Daftar Penerima</h3>
            <span class="badge badge-blue">{{ $campaign->logs->count() }} penerima</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Nama</th>
                        <th class="px-4 py-2 font-semibold">Phone</th>
                        <th class="w-40 px-4 py-2 font-semibold">Status</th>
                        <th class="w-60 px-4 py-2 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                        @forelse($campaign->logs as $log)
                            @php
                                $recipient = $log->recipient;
                                $isPending = $log->status === 'pending';
                                $isScheduled = $campaign->status === 'scheduled' && $isPending;
                                $isCustomerRecipient = $log->recipient_type === 'customer';
                                $canSend = $isPending && !$isScheduled && $isCustomerRecipient && $recipient !== null;
                                $statusBadge = $isScheduled
                                    ? '<span class="badge bg-blue-100 text-blue-700">Terjadwal</span>'
                                    : ($isPending
                                        ? '<span class="badge bg-slate-100 text-slate-700">Belum Dikirim</span>'
                                    : ($log->status === 'sent'
                                        ? '<span class="badge bg-green-100 text-green-700">Dikonfirmasi Terkirim</span>'
                                        : '<span class="badge bg-amber-100 text-amber-700">' . e(ucfirst($log->status)) . '</span>'));
                            @endphp
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium text-slate-900">{{ $recipient?->name ?? ($log->recipient_name ?? '-') }}</div>
                                </td>
                                <td class="px-4 py-3 align-top text-slate-600">{{ $log->phone }}</td>
                                <td class="px-4 py-3 align-top">{!! $statusBadge !!}</td>
                                <td class="px-4 py-3 align-top">
                                    @if($canSend)
                                        <div class="flex min-w-52 flex-col items-start gap-2">
                                        <form method="POST" action="{{ route('broadcast-campaigns.send-to-customer', [$campaign, $recipient]) }}" target="_blank" rel="noopener">
                                            @csrf
                                            <button type="submit" class="btn-primary w-48 text-xs">
                                                Buka WhatsApp
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('broadcast-campaigns.confirm-customer-sent', [$campaign, $recipient]) }}">
                                            @csrf
                                            <!-- rounded-[12px] (bukan rounded-xl): kombinasi bg-white + rounded-xl kena rule kartu (.bg-white.rounded-xl) sehingga radius & bayangan tombol berubah -->
                                                <button type="submit" class="inline-flex min-h-11 w-48 items-center justify-center rounded-[12px] border border-blue-600 bg-white px-3 text-xs font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                                                Saya sudah mengirim
                                            </button>
                                        </form>
                                        <p class="text-xs leading-relaxed text-slate-500">Konfirmasi setelah menekan Kirim di WhatsApp.</p>
                                        </div>
                                    @elseif($isScheduled)
                                        <span class="text-xs text-slate-400">Menunggu waktu pengiriman</span>
                                    @elseif(!$isPending)
                                        <span class="text-xs text-slate-400">
                                            {{ $log->status === 'sent' ? 'Terkirim ' : '' }}{{ optional($log->sent_at)->format('d M Y H:i') ?? '' }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">Belum Dikirim</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">
                                Belum ada penerima.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const editMessageBtn = document.getElementById('editMessageBtn');
    const saveMessageBtn = document.getElementById('saveMessageBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const messageView = document.getElementById('messageView');
    const messageEdit = document.getElementById('messageEdit');
    const messageTextarea = document.getElementById('messageTextarea');
    const campaignId = {{ $campaign->id }};

    if (editMessageBtn) {
        editMessageBtn.addEventListener('click', () => {
            messageView.classList.add('hidden');
            messageEdit.classList.remove('hidden');
        });
    }

    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', () => {
            messageView.classList.remove('hidden');
            messageEdit.classList.add('hidden');
        });
    }

    if (saveMessageBtn) {
        saveMessageBtn.addEventListener('click', async () => {
            const message = messageTextarea.value.trim();
            if (!message) {
                alert('Pesan tidak boleh kosong.');
                return;
            }

            try {
                const response = await fetch(`/broadcast-campaigns/${campaignId}/message`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ custom_message: message }),
                });

                if (!response.ok) {
                    throw new Error('Gagal menyimpan pesan.');
                }

                messageView.querySelector('p').textContent = message;
                messageView.classList.remove('hidden');
                messageEdit.classList.add('hidden');
            } catch (error) {
                alert(error.message);
            }
        });
    }
});
</script>
@endpush
