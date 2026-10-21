@extends('Layouts.app')

@section('title', 'Broadcast Campaign - SewaJas')
@section('page-title', 'Broadcast Campaign')
@section('subtitle', 'Kelola campaign broadcast')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-extrabold text-slate-950">Daftar Campaign</h2>
            <p class="mt-1 text-sm text-slate-500">Kelola campaign In-App dan broadcast WhatsApp manual.</p>
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <a href="{{ route('broadcast-campaigns.templates.index') }}" class="btn-secondary w-full justify-center px-4 py-2 sm:w-auto">
                <i data-lucide="files" class="h-4 w-4"></i>
                Template Pesan
            </a>
            <a href="{{ route('broadcast-campaigns.create') }}" class="btn-primary w-full justify-center px-4 py-2 sm:w-auto">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Buat Campaign
            </a>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white shadow-card-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Channel</th>
                        <th class="px-5 py-3 font-semibold">Nama Campaign</th>
                        <th class="px-5 py-3 font-semibold">Tipe Penerima</th>
                        <th class="px-5 py-3 font-semibold">Target</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Progress</th>
                        <th class="px-5 py-3 font-semibold">Tanggal</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($campaigns as $campaign)
                        <tr class="hover:bg-slate-50/60" data-campaign-id="{{ $campaign->id }}">
                            <td class="px-5 py-3">
                                <div class="font-semibold text-slate-900">{{ $campaign->name }}</div>
                                <div class="text-xs text-slate-500">{{ $campaign->provider }}</div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($campaign->channels ?? ['whatsapp'] as $channel)
                                        @php
                                            $channelLabel = match($channel) {
                                                'in_app' => 'In-App',
                                                'whatsapp' => 'WhatsApp',
                                                default => ucfirst($channel),
                                            };
                                        @endphp
                                        <span class="badge badge-blue">{{ $channelLabel }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="badge">{{ ucfirst($campaign->recipient_type) }}</span>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ ucfirst(str_replace('_', ' ', $campaign->target_type)) }}</td>
                            <td class="px-5 py-3">
                                @php
                                    $statusColors = [
                                        'draft' => 'bg-slate-100 text-slate-700',
                                        'scheduled' => 'bg-blue-100 text-blue-700',
                                        'queued' => 'bg-yellow-100 text-yellow-700',
                                        'processing' => 'bg-orange-100 text-orange-700',
                                        'completed' => 'bg-green-100 text-green-700',
                                        'failed' => 'bg-red-100 text-red-700',
                                        'partial' => 'bg-purple-100 text-purple-700',
                                    ];
                                    $color = $statusColors[$campaign->status] ?? 'bg-slate-100 text-slate-700';
                                @endphp
                                <span class="badge {{ $color }}">{{ ucfirst($campaign->status) }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 rounded-full bg-slate-100">
                                        <div class="h-2 rounded-full bg-blue-600" style="width: {{ $campaign->total_target > 0 ? ($campaign->total_success / $campaign->total_target * 100) : 0 }}%"></div>
                                    </div>
                                    <span class="text-xs text-slate-500">{{ $campaign->total_success }}/{{ $campaign->total_target }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-slate-500">
                                {{ optional($campaign->scheduled_at)->format('d M Y H:i') ?? optional($campaign->created_at)->format('d M Y H:i') }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('broadcast-campaigns.show', $campaign) }}" class="btn-ghost px-2 py-1 text-xs">
                                    Detail
                                </a>
                                @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdminToko())
                                    <button type="button" onclick="openDeleteModal('{{ $campaign->id }}', '{{ $campaign->name }}')" class="btn-ghost px-2 py-1 text-xs text-red-600 hover:text-red-700 ml-1">
                                        <i data-lucide="trash-2" class="h-3.5 w-3.5 inline-block align-text-bottom"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">
                                Belum ada campaign broadcast. Buat campaign baru untuk mulai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($campaigns->hasPages())
            <div class="px-5 py-3 border-t border-slate-100">
                {{ $campaigns->links() }}
            </div>
        @endif
    </div>
</div>

{{-- MODAL HAPUS CAMPAIGN --}}
<div id="deleteCampaignModal" style="display:none; position:fixed; inset:0; z-index:50; padding:16px; align-items:center; justify-content:center; background:rgba(26,18,8,.4); backdrop-filter:blur(6px);">
    <div class="card bg-white rounded-2xl border border-slate-200 w-full max-w-md overflow-hidden" style="position:relative;">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-red-50">
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="none">
                        <path d="M3 5h10M6 5V3.5A.5.5 0 0 1 6.5 3h3a.5.5 0 0 1 .5.5V5M5 5l.75 8h4.5L11 5" stroke="#DC2626" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h5 class="font-semibold text-red-700 text-[15px]">Hapus Campaign</h5>
            </div>
            <button onclick="closeDeleteModal()" class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        <div class="px-5 py-5 space-y-4">
            <div class="flex items-start gap-3 p-3.5 rounded-xl border border-red-100 bg-red-50">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="flex-shrink-0 mt-0.5">
                    <path d="M8 6v3.5M8 11.5v.5" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/>
                    <path d="M7.13 2.5L1.3 12.5a1 1 0 0 0 .87 1.5h11.66a1 1 0 0 0 .87-1.5L8.87 2.5a1 1 0 0 0-1.74 0z" stroke="#DC2626" stroke-width="1.4" stroke-linejoin="round"/>
                </svg>
                <p class="text-[12.5px] text-red-700 leading-relaxed">
                    Yakin ingin menghapus campaign <strong id="deleteCampaignName"></strong>? Semua log broadcast terkait juga akan dihapus. Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                </p>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-200 flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
            <button onclick="closeDeleteModal()" class="w-full sm:w-auto flex items-center justify-center px-5 py-2.5 rounded-xl border border-slate-200 text-[13.5px] font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                Batal
            </button>
            <button type="button" id="confirmDeleteBtn" class="w-full sm:w-auto flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-[13.5px] font-semibold text-white bg-red-600 hover:bg-red-700 transition-colors">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                    <path d="M3 5h10M6 5V3.5A.5.5 0 0 1 6.5 3h3a.5.5 0 0 1 .5.5V5M5 5l.75 8h4.5L11 5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Ya, Hapus Campaign
            </button>
        </div>
    </div>
</div>

<script>
let deleteCampaignId = null;

function openDeleteModal(id, name) {
    deleteCampaignId = id;
    document.getElementById('deleteCampaignName').textContent = name;
    const m = document.getElementById('deleteCampaignModal');
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    if (typeof window.stopNotificationPoll === 'function') {
        window.stopNotificationPoll();
    }
}

function closeDeleteModal() {
    document.getElementById('deleteCampaignModal').style.display = 'none';
    document.body.style.overflow = '';
    deleteCampaignId = null;

    if (typeof window.resumeNotificationPoll === 'function') {
        window.resumeNotificationPoll();
    }
}

document.getElementById('deleteCampaignModal').addEventListener('click', function (e) {
    if (e.target === this) closeDeleteModal();
});

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
    if (!deleteCampaignId) return;

    const btn = this;
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent mr-2"></span> Menghapus...';

    const timeoutId = setTimeout(function () {
        btn.disabled = false;
        btn.innerHTML = originalContent;
        alert('Permintaan habis waktu. Silakan coba lagi atau refresh halaman.');
    }, 30000);

    fetch(`/broadcast-campaigns/${deleteCampaignId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        },
    })
    .then(function (response) {
        clearTimeout(timeoutId);
        console.log('Delete response:', response.status, response.statusText);

        if (response.status === 403) {
            throw new Error('Unauthorized');
        }
        if (response.status === 404) {
            throw new Error('Campaign sudah tidak ada. Memuat ulang halaman...');
        }
        if (!response.ok) {
            throw new Error('Failed to delete');
        }
        return response.json();
    })
    .then(function (data) {
        console.log('Delete success:', data);
        const row = document.querySelector(`tr[data-campaign-id="${deleteCampaignId}"]`);
        if (row) {
            row.style.transition = 'opacity 0.3s, transform 0.3s';
            row.style.opacity = '0';
            row.style.transform = 'translateX(10px)';
            setTimeout(function () { row.remove(); }, 300);
        }
        closeDeleteModal();

        const successMsg = document.createElement('div');
        successMsg.className = 'fixed top-4 right-4 z-50 px-4 py-3 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 shadow-lg text-sm font-medium';
        successMsg.textContent = 'Campaign berhasil dihapus.';
        document.body.appendChild(successMsg);
        setTimeout(function () {
            successMsg.style.transition = 'opacity 0.3s';
            successMsg.style.opacity = '0';
            setTimeout(function () { successMsg.remove(); }, 300);
        }, 2500);
    })
    .catch(function (error) {
        console.error('Delete error:', error);
        alert(error.message || 'Gagal menghapus campaign.');
    })
    .finally(function () {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
});
</script>

@endsection
