{{-- Unified Flash Messages component
Cakupan:
- session('success')
- session('error')
- session('warning')
- $errors->any()
--}}

@if (session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="mb-4 flex items-start gap-3 px-4 py-3 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-800 shadow-sm">
        <i data-lucide="check-circle" class="mt-0.5 h-5 w-5 text-emerald-700"></i>
        <span class="text-sm font-semibold">{{ session('success') }}</span>
    </div>
@endif

@if (session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="mb-4 flex items-start gap-3 px-4 py-3 rounded-2xl border border-red-200 bg-red-50 text-red-800 shadow-sm">
        <i data-lucide="alert-circle" class="mt-0.5 h-5 w-5 text-red-700"></i>
        <span class="text-sm font-semibold">{{ session('error') }}</span>
    </div>
@endif

@if (session('warning'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="mb-4 flex items-start gap-3 px-4 py-3 rounded-2xl border border-amber-200 bg-amber-50 text-amber-800 shadow-sm">
        <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 text-amber-700"></i>
        <span class="text-sm font-semibold">{{ session('warning') }}</span>
    </div>
@endif

@if ($errors->any() && !$errors->hasBag('payment'))
    <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 text-red-800 p-4 shadow-sm">
        <div class="flex items-center gap-2 text-sm font-bold">
            <i data-lucide="alert-circle" class="h-5 w-5"></i>
            Periksa kembali input berikut
        </div>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

