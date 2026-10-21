<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SewaJas - Rental Jas Management')</title>
    @php
        $faviconPath = \App\Services\SettingsService::get('app_logo');
        $faviconUrl = $faviconPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($faviconPath)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($faviconPath)
            : asset('favicon.ico');
    @endphp
    <link rel="icon" href="{{ $faviconUrl }}">

    <!-- SewaJas Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    @vite(['resources/css/app.css', 'resources/css/customers.css', 'resources/js/app.js'])
    @stack('styles')
    @stack('head')
</head>

<body class="min-h-screen overflow-x-hidden font-sans" style="background: var(--bg); color: var(--text);">
@php
    $user = auth()->user();
    $navGroups = [
        'Utama' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'layout-dashboard', 'active' => 'dashboard', 'roles' => ['super_admin','admin_toko','sales']],
        ],
        'Operasional' => [
            ['label' => 'Penyewaan', 'route' => 'rentals.index', 'icon' => 'shirt', 'active' => ['rentals.index', 'rentals.create', 'rentals.store', 'rentals.show', 'rentals.edit', 'rentals.update', 'rentals.destroy', 'rentals.payment', 'rentals.payment.update', 'rentals.payment.destroy', 'rentals.payment.refund', 'rentals.payment.void', 'rentals.return', 'rentals.cancel-return', 'rentals.update-status', 'rentals.confirm-return-ajax', 'rentals.invoice', 'rentals.pdf', 'rentals.whatsapp', 'rentals.reminder', 'rentals.receipt.*', 'rentals.cancel'], 'roles' => ['super_admin','admin_toko','sales']],
            ['label' => 'Scan QR', 'route' => 'rentals.scan', 'icon' => 'scan-line', 'active' => ['rentals.scan', 'rentals.scan.show'], 'roles' => ['super_admin','admin_toko','sales']],
            ['label' => 'Broadcast', 'route' => 'broadcast-campaigns.index', 'icon' => 'send', 'active' => 'broadcast-campaigns.*', 'roles' => ['super_admin','admin_toko']],
        ],
        'Analisis' => [
            ['label' => 'Laporan Keuangan', 'route' => 'reports.index', 'icon' => 'chart-no-axes-combined', 'active' => ['reports.index', 'reports.revenue', 'reports.transactions', 'reports.returns', 'reports.stock', 'reports.customers', 'reports.payments', 'reports.export.*'], 'roles' => ['super_admin','admin_toko']],
        ],
        'Master Data' => [
            ['label' => 'Pelanggan', 'route' => 'customers.index', 'icon' => 'phone', 'active' => 'customers.*', 'roles' => ['super_admin','admin_toko','sales']],
            ['label' => 'Produk', 'route' => 'products.index', 'icon' => 'package', 'active' => 'products.*', 'roles' => ['super_admin','admin_toko','sales']],
            ['label' => 'Kategori', 'route' => 'categories.index', 'icon' => 'tags', 'active' => 'categories.*', 'roles' => ['super_admin']],
        ],
        'Log' => [
            ['label' => 'Log Keseluruhan', 'route' => 'reports.activity-log', 'icon' => 'scroll-text', 'active' => ['reports.activity-log', 'reports.activity-log.*'], 'roles' => ['super_admin']],
            ['label' => 'Riwayat Penyewaan', 'route' => 'rental-histories.index', 'icon' => 'archive', 'active' => 'rental-histories.*', 'roles' => ['super_admin','admin_toko','sales']],
        ],
        'Admin' => [
            ['label' => 'Cabang', 'route' => 'branches.index', 'icon' => 'building-2', 'active' => 'branches.*', 'roles' => ['super_admin']],
            ['label' => 'Pengguna', 'route' => 'users.index', 'icon' => 'users-round', 'active' => 'users.*', 'roles' => ['super_admin']],
            ['label' => 'Pengaturan', 'route' => 'settings.index', 'icon' => 'settings', 'active' => 'settings.*', 'roles' => ['super_admin']],
        ],
        'Akun' => [
            ['label' => 'Profil', 'route' => 'profile.edit', 'icon' => 'user-cog', 'active' => 'profile.*', 'roles' => ['super_admin','admin_toko','sales']],
        ],
    ];

    $canSee = fn(array $roles) => $user && in_array($user->role, $roles, true);
 @endphp

<div x-data="{ sidebarMobileOpen: false, userMenuOpen: false }" class="min-h-screen">
    <!-- Sidebar (SewaJas Blue Theme) -->
    <aside
        id="sidebar-drawer"
        x-cloak
        class="sidebar-drawer fixed inset-y-0 left-0 z-50 w-[260px] border-r border-slate-200 bg-slate-900 backdrop-blur-xl shadow-[0_10px_35px_rgba(15,23,42,0.40)] lg:z-40"
        :class="sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-x-2"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 -translate-x-2"
    >
        <!-- Mobile-only drawer; desktop rail stays visible below -->
        <div class="flex h-full flex-col">
            <!-- Brand -->
            <div class="flex items-center gap-3 border-b border-slate-700 px-5 py-4" style="min-height:72px; background:#0f172a;">
                @php
                    $appLogo = \App\Services\SettingsService::get('app_logo');
                    $appName = \App\Services\SettingsService::get('app_name', 'SewaJas');
                    $appTagline = \App\Services\SettingsService::get('app_tagline', 'RENTAL JAS');
                @endphp
                @if($appLogo && \Illuminate\Support\Facades\Storage::disk('public')->exists($appLogo))
                    @php
                        $fullPath = storage_path('app/public/' . $appLogo);
                        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
                        $appLogoUrl = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($fullPath));
                    @endphp
                    <img src="{{ $appLogoUrl }}" alt="{{ $appName }}" class="h-9 w-9 rounded-xl object-contain">
                @else
                    <div class="h-11 w-11 items-center justify-center rounded-2xl flex" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #ffffff; box-shadow: 0 10px 35px rgba(37,99,235,0.25);">
                        <!-- Blazer Icon SVG -->
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7h18l-1 14H4L3 7z"/>
                            <path d="M7 7l1-4h8l1 7"/>
                            <path d="M12 11v10"/>
                            <path d="M8 21h8"/>
                        </svg>
                    </div>
                @endif
                <div class="min-w-0">
                    <div class="text-base font-bold tracking-tight text-white truncate font-sans">{{ $appName }}</div>
                    <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-300">{{ $appTagline }}</div>
                </div>
                <button type="button" @click="sidebarMobileOpen = false" class="ml-auto rounded-xl p-2 text-slate-400 hover:bg-white/10 lg:hidden">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>


            <!-- User card -->
            @auth
            <div class="mx-4 mt-4 rounded-2xl border border-slate-700 bg-slate-800 p-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl font-bold text-sm text-white" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-bold text-white">{{ $user->name }}</div>
                        <div class="truncate text-xs font-medium text-blue-300">{{ $user->role_label }}</div>
                    </div>
                </div>
            </div>
            @endauth

            <!-- Nav -->
            <nav id="sidebar-navigation" class="min-h-0 flex-1 overflow-y-auto px-4 py-5">
                @foreach($navGroups as $group => $items)
                    @php($visibleItems = collect($items)->filter(fn($item) => $canSee($item['roles'])))
                    @if($visibleItems->isNotEmpty())
                        <div class="mb-6">
                            <div class="mb-3 px-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $group }}</div>
                            <div class="space-y-1">
                                @foreach($visibleItems as $item)
                                    <a
                                        href="{{ route($item['route']) }}"
                                        @click="sessionStorage.setItem('sidebar-nav-scroll-top', document.getElementById('sidebar-navigation').scrollTop); sidebarMobileOpen = false"
                                        class="sidebar-item flex items-center gap-3 rounded-[16px] px-3 py-2.5 text-sm font-semibold ds-transition {{ request()->routeIs($item['active']) ? 'active' : 'text-slate-300' }}"
                                    >
                                        <i data-lucide="{{ $item['icon'] }}" class="h-4 w-4"></i>
                                        <span class="truncate">{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>

            @if($canSee(['super_admin', 'admin_toko', 'sales']))
                <div class="shrink-0 border-t border-slate-700 p-4" style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
                    <form id="logoutSidebarForm" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="button" onclick="confirmAction('logoutSidebarForm', 'Keluar dari Sistem', 'Apakah Anda yakin ingin keluar dari web ini?', 'Ya, Keluar', '#dc2626')"
                            class="w-full flex items-center gap-3 rounded-[16px] px-3 py-3 text-sm font-semibold ds-transition text-rose-400 hover:bg-rose-500/10">
                            <i data-lucide="log-out" class="h-4 w-4"></i>
                            <span>Keluar</span>
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </aside>

    <!-- Mobile overlay -->
    <div x-show="sidebarMobileOpen" @click="sidebarMobileOpen = false" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" style="display:none"></div>

    <!-- Main -->
    <main class="min-h-screen lg:pl-[260px]">
        <!-- Navbar -->
        <header class="sticky top-0 z-[80] flex w-full border-b" style="height:72px; background: rgba(255,255,255,.88); border-bottom-color: rgba(226,232,240,.95); backdrop-filter: blur(14px);">
            <div class="h-full w-full flex items-center justify-between px-4 lg:px-8 gap-3">

                <!-- Breadcrumb + Greeting -->
                <div class="min-w-0 flex-1 flex items-center gap-3">
                    <button type="button" @click="sidebarMobileOpen = !sidebarMobileOpen" :aria-expanded="sidebarMobileOpen.toString()" aria-controls="sidebar-drawer" aria-label="Buka atau tutup menu navigasi" class="lg:hidden rounded-[16px] border border-slate-200 bg-white p-2 ds-transition">
                        <i data-lucide="menu" class="h-5 w-5 text-slate-700"></i>
                    </button>
                    <div class="min-w-0">
                        <h1 class="truncate text-xl font-bold tracking-tight text-slate-800 font-sans">@yield('page-title', 'Dashboard')</h1>
                    </div>
                </div>

                <!-- Search (desktop center) -->
                <form action="{{ route('search.index') }}" method="GET" class="hidden lg:flex items-center gap-2 ds-search px-3 ds-transition" style="max-width:560px; width: 560px;">
                    <i data-lucide="search" class="h-4 w-4 text-slate-500"></i>
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Cari invoice, customer, produk..."

                    >
                    @if(request('q'))
                        <button type="button" data-url="{{ route('search.index') }}" onclick="window.location=this.dataset.url" class="ml-2 inline-flex items-center justify-center h-8 w-8 rounded-[14px] hover:bg-blue-50 ds-transition">
                            <i data-lucide="x" class="h-4 w-4 text-slate-500"></i>
                        </button>
                    @endif
                </form>

                <!-- Right actions -->
                <div class="flex items-center gap-2">
                    @if($overdueCount > 0)
                        <a href="{{ route('rentals.index', ['status' => 'overdue']) }}" class="hidden lg:flex items-center gap-2 rounded-[16px] border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 ds-transition">
                            <i data-lucide="alert-triangle" class="h-4 w-4"></i>
                            {{ $overdueCount }} telat
                        </a>
                    @endif

                    <button type="button" class="lg:hidden rounded-[16px] border border-slate-200 bg-white p-2 ds-transition" @click="window.location='{{ route('search.index') }}'">
                        <i data-lucide="search" class="h-5 w-5 text-slate-700"></i>
                    </button>

                    <a href="{{ route('notifications.index') }}" class="relative rounded-[16px] border border-slate-200 bg-white p-2 shadow-sm hover:shadow-md ds-transition">
                        <i data-lucide="bell" class="h-5 w-5 text-slate-700"></i>
                        @if($unreadNotif > 0)
                            <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] font-bold text-white">
                                {{ $unreadNotif > 9 ? '9+' : $unreadNotif }}
                            </span>
                        @endif
                    </a>

                    <!-- Avatar dropdown (pure UI) -->
                    <div class="relative" x-data="{ open:false }">
                        <button @click="open=!open" class="flex items-center gap-2 rounded-[16px] border border-slate-200 bg-white p-1.5 ds-transition hover:bg-blue-50">
                            <div class="h-10 w-10 rounded-full flex items-center justify-center font-bold text-white" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </div>
                        </button>

                        <div x-show="open" @click.outside="open=false" x-transition
                             class="absolute right-0 mt-3 w-64 rounded-[20px] border border-slate-200 bg-white shadow-xl p-3 ds-transition" style="display:none">
                            <div class="px-2 py-2">
                                <div class="text-sm font-bold text-slate-800">{{ auth()->user()->name }}</div>
                                <div class="text-xs font-medium text-slate-500">{{ auth()->user()->email }}</div>
                            </div>
                            <div class="border-t my-2 border-slate-200"></div>
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-[14px] px-2 py-2 text-sm font-semibold text-slate-700 hover:bg-blue-50 ds-transition">
                                <i data-lucide="user" class="h-4 w-4"></i>
                                Profil Saya
                            </a>
@auth
                            @if(auth()->user()->isSuperAdmin())
                                <a href="{{ route('settings.index') }}" class="mt-1 flex items-center gap-2 rounded-[14px] px-2 py-2 text-sm font-semibold text-slate-700 hover:bg-blue-50 ds-transition">
                                    <i data-lucide="settings" class="h-4 w-4"></i>
                                    Pengaturan
                                </a>
                            @endif
                            @endauth
                            <form id="logoutDropdownForm" method="POST" action="{{ route('logout') }}" class="mt-1">
                                @csrf
                                <button type="button" onclick="confirmAction('logoutDropdownForm', 'Keluar dari Sistem', 'Apakah Anda yakin ingin keluar dari web ini?', 'Ya, Keluar', '#dc2626')" class="w-full flex items-center gap-2 rounded-[14px] px-2 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 ds-transition">
                                    <i data-lucide="log-out" class="h-4 w-4"></i>
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- content -->
        <section class="px-4 py-6 lg:px-8 pb-28 lg:pb-6">
            <div class="space-y-6">
                @includeIf('components.flash-messages')
                @yield('content')
            </div>
        </section>
    </main>

    <!-- Floating Quick Actions (Mobile) -->
    <div class="fixed inset-x-0 bottom-0 z-40 lg:hidden" style="padding-bottom: max(.5rem, env(safe-area-inset-bottom));">
        <div class="mx-3 mb-3 rounded-[20px] border border-slate-200 bg-white/88 backdrop-blur-xl shadow-[0_14px_50px_rgba(15,23,42,0.10)]">
            <div class="grid grid-cols-4 gap-1 p-2">
                <a href="{{ route('dashboard') }}" class="ds-hover-lift flex flex-col items-center justify-center gap-1 rounded-[16px] py-2 {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-600 border border-blue-200' : 'bg-transparent text-slate-500 hover:bg-blue-50 border border-transparent' }}" style="border-color: rgba(226,232,240,.95);">
                    <i data-lucide="layout-dashboard" class="h-5 w-5"></i>
                    <span class="text-[10px] font-bold">Dashboard</span>
                </a>
                <a href="{{ route('rentals.scan') }}" class="ds-hover-lift flex flex-col items-center justify-center gap-1 rounded-[16px] py-2 {{ request()->routeIs('rentals.scan', 'rentals.scan.show') ? 'bg-blue-50 text-blue-600 border border-blue-200' : 'bg-transparent text-slate-500 hover:bg-blue-50 border border-transparent' }}" style="border-color: rgba(226,232,240,.95);">
                    <i data-lucide="scan-line" class="h-5 w-5"></i>
                    <span class="text-[10px] font-bold">Scan QR</span>
                </a>
                <a href="{{ route('customers.index') }}" class="ds-hover-lift flex flex-col items-center justify-center gap-1 rounded-[16px] py-2 {{ request()->routeIs('customers.*') ? 'bg-blue-50 text-blue-600 border border-blue-200' : 'bg-transparent text-slate-500 hover:bg-blue-50 border border-transparent' }}" style="border-color: rgba(226,232,240,.95);">
                    <i data-lucide="users" class="h-5 w-5"></i>
                    <span class="text-[10px] font-bold">Pelanggan</span>
                </a>
                <a href="{{ route('notifications.index') }}" class="ds-hover-lift flex flex-col items-center justify-center gap-1 rounded-[16px] py-2 bg-transparent text-slate-500 hover:bg-blue-50 border border-slate-200 relative">
                    <i data-lucide="bell" class="h-5 w-5"></i>
                    <span class="text-[10px] font-bold">Notifs</span>
                    @if($unreadNotif > 0)
                        <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] font-bold text-white">
                            {{ $unreadNotif > 9 ? '9+' : $unreadNotif }}
                        </span>
                    @endif
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
        const sidebarNavigation = document.getElementById('sidebar-navigation');
        const savedSidebarScrollTop = sessionStorage.getItem('sidebar-nav-scroll-top');
        if (sidebarNavigation && savedSidebarScrollTop !== null) {
            sidebarNavigation.scrollTop = Number(savedSidebarScrollTop);
            sessionStorage.removeItem('sidebar-nav-scroll-top');
        }

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    });
    document.addEventListener('alpine:init', () => {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    });

    // Global destructive action confirmation
    window.confirmDelete = function(formId, label = 'data ini') {
        Swal.fire({
            title: 'Hapus Data',
            text: 'Apakah Anda yakin ingin menghapus ' + label + '?\n\nData yang dihapus tidak dapat dipulihkan kembali.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                confirmButton: 'rounded-xl px-5 py-2.5 text-sm font-semibold',
                cancelButton: 'rounded-xl px-5 py-2.5 text-sm font-semibold',
                popup: 'rounded-2xl'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menghapus...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading(),
                });
                document.getElementById(formId).submit();
            }
        });
    };

    window.confirmAction = function(formId, title, text, confirmText = 'Ya, Lanjutkan', confirmColor = '#dc2626') {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#64748b',
            confirmButtonText: confirmText,
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                confirmButton: 'rounded-xl px-5 py-2.5 text-sm font-semibold',
                cancelButton: 'rounded-xl px-5 py-2.5 text-sm font-semibold',
                popup: 'rounded-2xl'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading(),
                });
                document.getElementById(formId).submit();
            }
        });
    };
</script>

@stack('scripts')
</body>
</html>
