@extends('Layouts.app')

@section('title', 'Buat Broadcast Campaign - SewaJas')
@section('page-title', 'Buat Broadcast Campaign')
@section('subtitle', 'Buat campaign broadcast multi-channel')

@section('content')
<div class="w-full">
    <section class="w-full rounded-3xl border border-slate-200 bg-white p-5 shadow-card-sm lg:p-8">
        <form method="POST" action="{{ route('broadcast-campaigns.store') }}" class="space-y-5" id="campaignForm">
            @csrf
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Nama Campaign</label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-input" maxlength="120" placeholder="Contoh: Promo Akhir Pekan" required>
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Channel</label>
                <div class="flex flex-wrap gap-3">
                    @foreach($channels as $channel)
                        <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 transition hover:border-blue-200">
                            <input type="radio" name="channel" value="{{ $channel['value'] }}" class="rounded border-slate-300" @checked(old('channel') === $channel['value'])>
                            <span class="text-sm font-semibold text-slate-700">{{ $channel['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('channel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Tipe Penerima</label>
                    <p id="recipientTypeLabel" class="form-input bg-slate-50 text-slate-600">Tipe penerima mengikuti channel yang dipilih.</p>
                    @error('recipient_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <input type="hidden" name="recipient_type" id="recipient_type_hidden" value="{{ old('recipient_type') }}">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Target</label>
                    <select name="target_type" id="target_type" class="form-input" required>
                        <option value="">Pilih target</option>
                        @foreach($targetTypeOptions as $option)
                            @php
                                $channelRestriction = match($option['value']) {
                                    'sales' => 'in_app',
                                    'rental_status', 'product', 'category' => 'whatsapp',
                                    default => null,
                                };
                            @endphp
                            <option value="{{ $option['value'] }}" @if($channelRestriction) data-channel="{{ $channelRestriction }}" @endif @selected(old('target_type') === $option['value'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    @error('target_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div id="filterContainer" class="hidden space-y-4">
                <div id="branchFilter" class="hidden">
                    <label class="mb-2 block text-sm font-bold text-slate-700">Cabang</label>
                    <select name="target_filters[branch_id]" id="branchSelect" class="form-input">
                        <option value="">Semua cabang</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('target_filters.branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('target_filters.branch_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div id="customerFilter" class="hidden">
                    <label class="mb-2 block text-sm font-bold text-slate-700">Pilih Pelanggan</label>
                    <details id="customerDropdown" class="relative">
                        <summary class="form-input flex cursor-pointer list-none items-center justify-between">
                            <span id="customerSelectionLabel">Pilih satu atau beberapa Customer</span>
                            <i data-lucide="chevron-down" class="h-4 w-4"></i>
                        </summary>
                        <div class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                        @foreach($customers as $customer)
                            <label class="customer-option flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm hover:bg-slate-50">
                                <input type="checkbox" name="recipient_ids[]" value="{{ $customer->id }}"
                                    @checked(old('recipient_type') === 'customer' && old('target_type') === 'customer' && in_array($customer->id, old('recipient_ids', [])))>
                                <span>{{ $customer->name }} ({{ $customer->phone }})</span>
                            </label>
                        @endforeach
                        </div>
                    </details>
                    @error('recipient_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-500">Pilih satu atau beberapa Customer dari daftar.</p>
                </div>

                <div id="userFilter" class="hidden">
                    <label class="mb-2 block text-sm font-bold text-slate-700">Pilih Sales</label>
                    <details id="salesDropdown" class="relative">
                        <summary class="form-input flex cursor-pointer list-none items-center justify-between">
                            <span id="salesSelectionLabel">Pilih satu atau beberapa Sales</span>
                            <i data-lucide="chevron-down" class="h-4 w-4"></i>
                        </summary>
                        <div class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                        @foreach($users as $userItem)
                            @if($userItem->role === \App\Models\User::ROLE_SALES)
                                <label class="sales-option flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm hover:bg-slate-50"
                                    data-branch-id="{{ $userItem->branch_id }}"
                                    data-role="{{ $userItem->role }}">
                                    <input type="checkbox" name="recipient_ids[]" value="{{ $userItem->id }}"
                                        @checked(old('recipient_type') === 'user' && old('target_type') === 'sales' && old('target_filters.branch_id') == $userItem->branch_id && in_array($userItem->id, old('recipient_ids', [])))>
                                    <span>{{ $userItem->name }}</span>
                                </label>
                            @endif
                        @endforeach
                        </div>
                    </details>
                    @error('recipient_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-500">Daftar menampilkan Sales aktif dari cabang yang dipilih.</p>
                    <p id="noSalesOptions" class="mt-2 hidden text-sm text-amber-700">Tidak ada Sales aktif yang dapat menerima broadcast di cabang ini.</p>
                </div>
            </div>

            <div id="templateFields">
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label for="template_id" class="block text-sm font-bold text-slate-700">Template Pesan (Opsional)</label>
                        <a href="{{ route('broadcast-campaigns.templates.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">
                            Kelola Template
                        </a>
                    </div>
                    <select name="template_id" id="template_id" class="form-input">
                        <option value="">Pilih template (opsional)</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" @selected(old('template_id') == $template->id)>{{ $template->name }} ({{ $template->category }})</option>
                        @endforeach
                    </select>
                    @error('template_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div id="templatePreview" class="hidden rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="mb-2 text-xs font-semibold text-slate-500">Preview Template:</p>
                    <p id="templateContent" class="text-sm text-slate-700"></p>
                    <p id="templateVariables" class="mt-2 text-xs text-slate-500"></p>
                </div>
            </div>

            <div id="customMessageContainer">
                <label class="mb-2 block text-sm font-bold text-slate-700">Pesan</label>
                <textarea id="customMessageInput" name="custom_message" rows="5" class="form-input" placeholder="Tulis pesan">{{ old('custom_message') }}</textarea>
                @error('custom_message') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <fieldset id="sendScheduling" class="space-y-3">
                <legend class="mb-2 block text-sm font-bold text-slate-700">Waktu Pengiriman</legend>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" name="send_mode" value="now" @checked(old('send_mode', 'now') === 'now')>
                    <span>Kirim sekarang</span>
                </label>
                <label id="scheduledModeOption" class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" name="send_mode" value="scheduled" @checked(old('send_mode') === 'scheduled')>
                    <span>Jadwalkan</span>
                </label>
                <div id="scheduledAtContainer" class="hidden pl-6">
                    <label for="scheduled_at" class="mb-2 block text-sm font-semibold text-slate-700">Tanggal dan waktu</label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at') }}" class="form-input">
                </div>
                @error('scheduled_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('send_mode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </fieldset>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-5">
                <a href="{{ route('broadcast-campaigns.index') }}" class="btn-ghost px-4 py-2">Batal</a>
                <button type="submit" class="btn-primary px-6 py-2">
                    <i data-lucide="send" class="h-4 w-4"></i>
                    Buat Campaign
                </button>
            </div>
        </form>
    </section>
</div>
@endsection

@push('scripts')
<script>
const recipientTypeHidden = document.getElementById('recipient_type_hidden');
const recipientTypeLabel = document.getElementById('recipientTypeLabel');
const targetTypeSelect = document.getElementById('target_type');
const filterContainer = document.getElementById('filterContainer');
const branchFilter = document.getElementById('branchFilter');
const branchSelect = document.getElementById('branchSelect');
const customerFilter = document.getElementById('customerFilter');
const customerDropdown = document.getElementById('customerDropdown');
const customerSelectionLabel = document.getElementById('customerSelectionLabel');
const customerOptions = document.querySelectorAll('.customer-option');
const userFilter = document.getElementById('userFilter');
const salesDropdown = document.getElementById('salesDropdown');
const salesSelectionLabel = document.getElementById('salesSelectionLabel');
const noSalesOptions = document.getElementById('noSalesOptions');
const salesOptions = document.querySelectorAll('.sales-option');
const templateIdSelect = document.getElementById('template_id');
const templatePreview = document.getElementById('templatePreview');
const templateContent = document.getElementById('templateContent');
const templateVariables = document.getElementById('templateVariables');
const channelInputs = document.querySelectorAll('input[name="channel"]');
const sendModeInputs = document.querySelectorAll('input[name="send_mode"]');
const sendScheduling = document.getElementById('sendScheduling');
const scheduledModeOption = document.getElementById('scheduledModeOption');
const scheduledAtContainer = document.getElementById('scheduledAtContainer');
const scheduledAtInput = document.getElementById('scheduled_at');
const customMessageContainer = document.getElementById('customMessageContainer');
const customMessageInput = document.getElementById('customMessageInput');

const validCombinations = {
    customer: ['all', 'branch', 'customer'],
    user: ['all', 'branch', 'sales'],
    both: ['all', 'branch'],
};

const templates = @json($templates);

function getSelectedChannel() {
    const checked = document.querySelector('input[name="channel"]:checked');
    return checked ? checked.value : null;
}

function isWhatsappSelected() {
    return getSelectedChannel() === 'whatsapp';
}

function updateRecipientTypeLock() {
    const selectedChannel = getSelectedChannel();
    
    if (selectedChannel === 'in_app') {
        if (recipientTypeHidden) recipientTypeHidden.value = 'user';
        recipientTypeLabel.textContent = 'User internal (Admin Toko dan Sales)';
    } else if (selectedChannel === 'whatsapp') {
        if (recipientTypeHidden) recipientTypeHidden.value = 'customer';
        recipientTypeLabel.textContent = 'Customer';
    } else {
        if (recipientTypeHidden) recipientTypeHidden.value = '';
        recipientTypeLabel.textContent = 'Tipe penerima mengikuti channel yang dipilih.';
    }
}

function updateSendMode() {
    const whatsapp = isWhatsappSelected();
    sendScheduling.classList.toggle('hidden', whatsapp);
    scheduledModeOption.classList.toggle('hidden', whatsapp);
    if (whatsapp) {
        document.querySelector('input[name="send_mode"][value="now"]').checked = true;
    }
    const scheduled = document.querySelector('input[name="send_mode"]:checked')?.value === 'scheduled';
    scheduledAtContainer.classList.toggle('hidden', !scheduled);
    scheduledAtInput.disabled = !scheduled;
    scheduledAtInput.required = scheduled;
    sendModeInputs.forEach(input => {
        input.disabled = whatsapp && input.value === 'scheduled';
    });
}

function updateTargetOptions() {
    const selectedChannel = getSelectedChannel();
    const recipientType = selectedChannel === 'in_app' ? 'user' : (selectedChannel === 'whatsapp' ? 'customer' : null);
    const allowedTargets = recipientType ? validCombinations[recipientType] : [];

    Array.from(targetTypeSelect.options).forEach(option => {
        if (option.value === '') {
            option.disabled = false;
            return;
        }

        if (option.value === 'all') {
            option.textContent = selectedChannel === 'in_app'
                ? 'Semua User Internal'
                : (selectedChannel === 'whatsapp' ? 'Semua Customer' : 'Semua');
        }
        if (option.value === 'sales') {
            option.textContent = 'Pilih Sales';
        }
        const isAllowed = allowedTargets.includes(option.value);
        const channelMatch = !option.dataset.channel || option.dataset.channel === selectedChannel;
        option.disabled = !(isAllowed && channelMatch);
        option.hidden = option.disabled;
    });

    if (targetTypeSelect.value && targetTypeSelect.querySelector('option:checked')?.disabled) {
        targetTypeSelect.value = '';
    }

    updateFilters();
}

function updateFilters() {
    const selectedChannel = getSelectedChannel();
    const targetType = targetTypeSelect.value;
    const isInApp = selectedChannel === 'in_app';
    const showBranchFilter = targetType === 'branch' || (isInApp && targetType === 'sales');
    const showCustomerFilter = !isInApp && targetType === 'customer';
    const showSalesFilter = isInApp && targetType === 'sales';

    branchFilter.classList.toggle('hidden', !showBranchFilter);
    customerFilter.classList.toggle('hidden', !showCustomerFilter);
    userFilter.classList.toggle('hidden', !showSalesFilter);
    filterContainer.classList.toggle('hidden', !(showBranchFilter || showCustomerFilter || showSalesFilter));

    branchSelect.disabled = !showBranchFilter;
    branchSelect.required = isInApp && showBranchFilter;
    branchSelect.options[0].textContent = branchSelect.required ? 'Pilih cabang' : 'Semua cabang';

    let selectedCustomerCount = 0;
    customerOptions.forEach(option => {
        const checkbox = option.querySelector('input[type="checkbox"]');
        checkbox.disabled = !showCustomerFilter;
        if (!showCustomerFilter) checkbox.checked = false;
        if (checkbox.checked) selectedCustomerCount++;
    });
    customerSelectionLabel.textContent = selectedCustomerCount
        ? `${selectedCustomerCount} Customer dipilih`
        : 'Pilih satu atau beberapa Customer';
    customerDropdown.classList.toggle('hidden', !showCustomerFilter);

    const selectedBranchId = branchSelect.value;
    let selectedSalesCount = 0;
    let availableSalesCount = 0;
    salesOptions.forEach(option => {
        const checkbox = option.querySelector('input[type="checkbox"]');
        const isAvailable = showSalesFilter && selectedBranchId !== '' && option.dataset.branchId === selectedBranchId;
        option.classList.toggle('hidden', !isAvailable);
        checkbox.disabled = !isAvailable;
        if (!isAvailable) checkbox.checked = false;
        if (isAvailable) availableSalesCount++;
        if (checkbox.checked) selectedSalesCount++;
    });

    salesSelectionLabel.textContent = selectedSalesCount
        ? `${selectedSalesCount} Sales dipilih`
        : 'Pilih satu atau beberapa Sales';
    salesDropdown.classList.toggle('hidden', !selectedBranchId || availableSalesCount === 0);
    noSalesOptions.classList.toggle('hidden', !showSalesFilter || !selectedBranchId || availableSalesCount > 0);
}

function updateTemplatePreview() {
    const templateId = templateIdSelect.value;
    if (!templateId) {
        templatePreview.classList.add('hidden');
        customMessageContainer.classList.remove('hidden');
        customMessageInput.disabled = false;
        customMessageInput.required = true;
        return;
    }

    const template = templates.find(t => t.id == templateId);
    if (!template) {
        templatePreview.classList.add('hidden');
        customMessageContainer.classList.remove('hidden');
        customMessageInput.disabled = false;
        customMessageInput.required = true;
        return;
    }

    templateContent.textContent = template.content;
    if (template.variables && template.variables.length > 0) {
        templateVariables.textContent = 'Variabel: ' + template.variables.join(', ');
        templateVariables.classList.remove('hidden');
    } else {
        templateVariables.classList.add('hidden');
    }
    templatePreview.classList.remove('hidden');
    customMessageContainer.classList.add('hidden');
    customMessageInput.disabled = true;
    customMessageInput.required = false;
}

channelInputs.forEach(input => {
    input.addEventListener('change', function () {
        updateRecipientTypeLock();
        updateTargetOptions();
        updateSendMode();
    });
});

targetTypeSelect.addEventListener('change', updateFilters);
branchSelect.addEventListener('change', updateFilters);
sendModeInputs.forEach(input => input.addEventListener('change', updateSendMode));
salesOptions.forEach(option => {
    option.querySelector('input[type="checkbox"]').addEventListener('change', updateFilters);
});
customerOptions.forEach(option => {
    option.querySelector('input[type="checkbox"]').addEventListener('change', updateFilters);
});
templateIdSelect.addEventListener('change', updateTemplatePreview);

document.getElementById('campaignForm').addEventListener('submit', function (e) {
    const selectedChannel = getSelectedChannel();
    if (!selectedChannel) {
        e.preventDefault();
        alert('Pilih minimal satu channel.');
        return;
    }
    if (selectedChannel === 'whatsapp') {
        const recipientType = recipientTypeHidden.value;
        const targetType = targetTypeSelect.value;
        if (recipientType !== 'customer') {
            e.preventDefault();
            alert('Channel WhatsApp hanya mendukung target Customer.');
            return;
        }
        if (targetType === 'sales') {
            e.preventDefault();
            alert('Channel WhatsApp tidak mendukung target Sales.');
            return;
        }
    }
    if (selectedChannel === 'in_app') {
        if (['branch', 'sales'].includes(targetTypeSelect.value) && !branchSelect.value) {
            e.preventDefault();
            alert('Pilih cabang terlebih dahulu.');
            return;
        }
        if (targetTypeSelect.value === 'sales' && !Array.from(salesOptions).some(option => option.querySelector('input[type="checkbox"]').checked)) {
            e.preventDefault();
            alert('Pilih minimal satu Sales.');
            return;
        }
    }
});

document.addEventListener('DOMContentLoaded', () => {
    updateRecipientTypeLock();
    updateTargetOptions();
    updateSendMode();
    updateTemplatePreview();
    lucide.createIcons();
});
</script>
@endpush
