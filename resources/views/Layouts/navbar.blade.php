<!-- Top Navbar -->
<header class="h-[60px] lg:h-[72px] border-b flex items-center px-3 lg:px-6 gap-2 lg:gap-4 sticky top-0 z-30"
        style="background: var(--bg-navbar); border-color: var(--border); backdrop-filter: blur(10px);">

    <!-- Hamburger (mobile only) -->
    <button @click="sidebarMobileOpen = !sidebarMobileOpen"
            class="lg:hidden p-2 rounded-xl flex-shrink-0"
            style="background: var(--secondary); color: var(--text-soft)">
        <i data-lucide="menu" class="w-4 h-4"></i>
    </button>

    <!-- Sidebar toggle (desktop only) -->
    <button @click="sidebarOpen = !sidebarOpen"
            class="hidden lg:flex p-2 rounded-xl"
            style="background: var(--secondary); color: var(--text-soft)">
        <i data-lucide="panel-left" class="w-4 h-4"></i>
    </button>

    <!-- Page Title -->
    <div class="flex-1 min-w-0">
        <h1 class="font-playfair text-base lg:text-xl font-semibold truncate" style="color: var(--text-dark)">
            @yield('page-title', 'Dashboard')
        </h1>
        @hasSection('subtitle')
            <p class="text-[10px] lg:text-xs mt-0.5 truncate hidden sm:block" style="color: var(--text-soft)">@yield('subtitle')</p>
        @endif
    </div>

    <!-- Search (tablet+ only, hidden on small mobile) -->
    @php
        $showSearchRoutes = ['search.index', 'rentals.index', 'customers.index', 'products.index', 'reports.transactions'];
    @endphp
    @if(in_array(Route::currentRouteName(), $showSearchRoutes))
        <div class="hidden md:flex items-center gap-2 px-3 py-2 rounded-xl border w-56 lg:w-72 transition-all flex-shrink-0"
             style="background: var(--bg-input); border-color: var(--border);"
             x-data="{ focused: false }"
             :class="focused ? 'border-amber-300 shadow-sm' : ''">
            <i data-lucide="search" class="w-4 h-4 flex-shrink-0" style="color: var(--text-soft)"></i>
            <input type="text" placeholder="Cari invoice, customer..."
                   class="flex-1 text-sm bg-transparent outline-none"
                   style="color: var(--text-dark);"
                   @focus="focused = true" @blur="focused = false"
                   @keyup.enter="window.location.href = '{{ route('search.index') }}?q=' + $event.target.value">
        </div>
    @endif

    <!-- Overdue Alert (hidden on small mobile) -->
    @if ($overdueCount > 0)
        <a href="{{ route('rentals.index', ['status' => 'overdue']) }}"
           class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex-shrink-0"
           style="background: #FFF1F0; color: #C0392B; border: 1px solid #FECACA;">
            <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
            <span>{{ $overdueCount }} Telat</span>
        </a>
    @endif

    <!-- Dark Mode Toggle -->
    <button @click="darkMode = !darkMode; $fetch('/profile/theme', { method: 'POST', body: JSON.stringify({ theme: darkMode ? 'dark' : 'light' }) })"
            class="p-2 rounded-xl transition-all hover:scale-105 flex-shrink-0"
            style="background: var(--secondary); color: var(--text-soft)">
        <i data-lucide="sun" class="w-4 h-4" x-show="!darkMode"></i>
        <i data-lucide="moon" class="w-4 h-4" x-show="darkMode"></i>
    </button>

    <!-- Notifications -->
    <x-notification-bell />

    <!-- User Menu -->
    <div class="relative flex-shrink-0" x-data="{ open: false }">
        <button @click="open = !open" class="flex items-center gap-2 rounded-xl transition-all hover:scale-105">

            <!-- Avatar -->
            <div class="w-8 h-8 lg:w-9 lg:h-9 rounded-xl ring-2 ring-amber-200 flex items-center justify-center flex-shrink-0 font-bold text-xs text-white"
                 style="background: linear-gradient(135deg, #0d6efd, #0dcaf0);">
                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
            </div>

            <!-- Name (desktop only) -->
            <div class="hidden lg:block text-left">
                <p class="text-sm font-semibold leading-tight" style="color: var(--text-dark)">
                    {{ Str::limit(auth()->user()->name, 16) }}
                </p>
                <p class="text-xs leading-tight" style="color: var(--text-soft)">
                    {{ auth()->user()->role }}
                </p>
            </div>

            <i data-lucide="chevron-down" class="w-3.5 h-3.5 hidden lg:block" style="color: var(--text-soft)"></i>
        </button>

        <!-- Dropdown -->
        <div x-show="open" @click.outside="open = false"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="absolute right-0 mt-2 w-56 rounded-2xl shadow-xl border overflow-hidden z-50"
             style="background: var(--bg-dropdown); border-color: var(--border);">
            <div class="p-4 border-b" style="border-color: var(--border);">
                <p class="text-sm font-semibold" style="color: var(--text-dark)">{{ auth()->user()->name }}</p>
                <p class="text-xs mt-0.5" style="color: var(--text-soft)">{{ auth()->user()->email }}</p>
            </div>
            <div class="p-2">
                <a href="{{ route('profile.edit') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors hover:bg-gray-50"
                   style="color: var(--text-dark)">
                    <i data-lucide="user" class="w-4 h-4" style="color: var(--text-soft)"></i>
                    Profil Saya
                </a>
                @can('manage-branches')
                    <a href="{{ route('settings.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors hover:bg-gray-50"
                       style="color: var(--text-dark)">
                        <i data-lucide="settings" class="w-4 h-4" style="color: var(--text-soft)"></i>
                        Pengaturan
                    </a>
                @endcan
                <div class="border-t my-1" style="border-color: var(--border)"></div>
                <form id="logoutNavbarForm" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="button"
                            onclick="confirmAction('logoutNavbarForm', 'Keluar dari Sistem', 'Apakah Anda yakin ingin keluar dari web ini?', 'Ya, Keluar', '#dc2626')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm w-full text-left transition-colors hover:bg-red-50"
                            style="color: #C0392B">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
