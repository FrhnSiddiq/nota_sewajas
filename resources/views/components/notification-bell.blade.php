{{-- resources/views/components/notification-bell.blade.php --}}
{{-- Cara pakai di layout: <x-notification-bell /> --}}

<div
    x-data="notificationBell()"
    x-init="init()"
    class="relative"
>
    {{-- ── Bell Button ───────────────────────────────────────────────── --}}
    <button
        @click="toggle()"
        :class="open ? 'bell-active' : ''"
        class="notif-bell-btn"
        title="Notifikasi"
        aria-label="Notifikasi"
    >
        {{-- Pulse ring --}}
        <span x-show="counts.total > 0" class="notif-pulse-ring"></span>

        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             :class="open ? 'text-gold-light' : 'text-cream'"
             class="notif-bell-icon" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>

        {{-- Badge --}}
        <span
            x-show="counts.total > 0"
            x-text="counts.total > 99 ? '99+' : counts.total"
            class="notif-badge"
        ></span>
    </button>

    {{-- ── Dropdown Panel ─────────────────────────────────────────────── --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-[-6px]"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.outside="open = false"
        class="notif-panel"
        style="display:none"
    >
        {{-- Header --}}
        <div class="notif-panel-head">
            <div>
                <p class="notif-panel-title">Notifikasi</p>
                <p class="notif-panel-sub" x-text="counts.total > 0 ? counts.total + ' belum dibaca' : 'Semua sudah dibaca'"></p>
            </div>
            <div class="notif-panel-actions">
                <button
                    x-show="counts.total > 0"
                    @click="markAllRead()"
                    class="notif-btn-markall"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg>
                    Tandai semua
                </button>
                <button @click="open = false" class="notif-btn-close" aria-label="Tutup">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <p x-show="errorMessage" x-text="errorMessage" role="alert" class="px-4 py-2 text-sm text-rose-700 bg-rose-50"></p>

        {{-- Filter Tabs --}}
        <div class="notif-filter-tabs">
            <template x-for="tab in tabs" :key="tab.key">
                <button
                    @click="changeFilter(tab.key)"
                    :class="filter === tab.key ? 'notif-tab-active' : ''"
                    class="notif-tab"
                >
                    <span x-text="tab.label"></span>
                    <span
                        x-show="tabCount(tab.key) > 0"
                        x-text="tabCount(tab.key)"
                        :class="filter === tab.key ? 'notif-tab-count-active' : 'notif-tab-count'"
                    ></span>
                </button>
            </template>
        </div>

        {{-- List --}}
        <div class="notif-list" id="notifScrollArea">

            {{-- Loading state --}}
            <div x-show="loading" class="notif-loading">
                <div class="notif-spinner"></div>
                <span>Memuat notifikasi...</span>
            </div>

            {{-- Empty state --}}
            <div x-show="!loading && notifications.length === 0" class="notif-empty">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="notif-empty-icon" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
                <p class="notif-empty-text">Tidak ada notifikasi</p>
                <p class="notif-empty-sub">Semua aktivitas sudah ditangani</p>
            </div>

            {{-- Items --}}
            <template x-for="notif in notifications" :key="notif.id">
                <div
                    @click="onItemClick(notif)"
                    :class="!notif.is_read ? 'notif-item unread' : 'notif-item'"
                    class="notif-item group"
                >
                    {{-- Unread bar --}}
                    <span x-show="!notif.is_read" class="notif-unread-bar"></span>

                    {{-- Icon --}}
                    <div :class="iconBg(notif.type)" class="notif-icon-wrap">
                        <span x-text="notif.icon_name" class="notif-icon-emoji"></span>
                    </div>

                    {{-- Body --}}
                    <div class="notif-body">
                        <div class="notif-row-top">
                            <span class="notif-title" x-text="notif.title"></span>
                            <span class="notif-time" x-text="notif.time_ago"></span>
                        </div>
                        <p class="notif-msg" x-text="notif.message"></p>
                        <span
                            x-show="notif.meta && notif.meta.invoice_no"
                            x-text="notif.meta ? notif.meta.invoice_no : ''"
                            class="notif-tag"
                        ></span>
                    </div>

                    {{-- Delete button --}}
                    <button
                        @click.stop="deleteNotif(notif.id)"
                        class="notif-delete-btn"
                        title="Hapus"
                        aria-label="Hapus notifikasi"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    </button>

                    {{-- Unread dot --}}
                    <span x-show="!notif.is_read" :class="dotColor(notif.type)" class="notif-dot"></span>
                </div>
            </template>

            {{-- Load more --}}
            <button
                x-show="hasMore && !loading"
                @click="loadMore()"
                class="notif-load-more"
            >
                Muat lebih banyak
            </button>
        </div>

        {{-- Footer --}}
        <div class="notif-panel-footer">
            <a href="{{ route('notifications.index') }}" class="notif-see-all">
                Lihat semua notifikasi
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</div>
