// resources/js/notifications.js
// Daftarkan di resources/js/app.js:
//   import './notifications.js'

window.notificationBell = function () {
    return {
        // ── State ────────────────────────────────────────────────────────────
        open:          false,
        loading:       false,
        notifications: [],
        counts:        { total: 0, rental_new: 0, rental_return: 0, rental_late: 0, payment: 0, reminder: 0, system: 0 },
        filter:        'all',
        page:          1,
        hasMore:       false,
        pollTimer:     null,
        hasFetched:    false,
        errorMessage:  '',

        tabs: [
            { key: 'all',           label: 'Semua'        },
            { key: 'rental_late',   label: 'Telat'        },
            { key: 'reminder',      label: 'Reminder'     },
            { key: 'rental_return', label: 'Pengembalian' },
            { key: 'payment',       label: 'Pembayaran'   },
            { key: 'system',        label: 'Sistem'       },
        ],

        // ── Init ─────────────────────────────────────────────────────────────
        init() {
            this.fetchCount()
            // Polling badge setiap 30 detik
            this.pollTimer = setInterval(() => this.fetchCount(), 30_000)

            // Cleanup saat komponen dihancurkan
            this.$el.addEventListener('alpine:destroy', () => {
                clearInterval(this.pollTimer)
            })
        },

        // ── Toggle panel ─────────────────────────────────────────────────────
        toggle() {
            this.open = !this.open
            if (this.open && !this.hasFetched) {
                this.hasFetched = true
                this.fetchNotifications()
            }
        },

        // ── Fetch hanya count (ringan, untuk badge) ───────────────────────────
        async fetchCount() {
            try {
                const res = await fetch('/notifications/count', {
                    headers: this._headers(),
                })
                if (!res.ok) return
                this.counts = await res.json()
            } catch { /* silent */ }
        },

        // ── Fetch list notifikasi ─────────────────────────────────────────────
        async fetchNotifications(append = false) {
            this.loading = true
            this.errorMessage = ''
            try {
                const params = new URLSearchParams({ page: this.page })
                if (this.filter !== 'all') params.set('type', this.filter)

                const res = await fetch(`/notifications/data?${params}`, {
                    headers: this._headers(),
                })
                if (!res.ok) throw new Error('Request gagal')

                const json = await res.json()

                this.notifications = append
                    ? [...this.notifications, ...json.notifications]
                    : json.notifications

                this.hasMore = json.has_more ?? json.notifications.length === 20
                this.counts  = json.counts
            } catch (e) {
                this.hasFetched = false
                this.errorMessage = 'Notifikasi gagal dimuat. Coba lagi.'
                console.error('Gagal memuat notifikasi:', e)
            } finally {
                this.loading = false
            }
        },

        // ── Tandai satu dibaca ────────────────────────────────────────────────
        async onItemClick(notif) {
            // Redirect jika ada action_url
            if (notif.action_url) {
                if (!notif.is_read) {
                    try {
                        const res = await fetch(`/notifications/${notif.id}/read`, {
                            method: 'POST',
                            headers: this._headers(),
                        })
                        if (!res.ok) throw new Error('Request gagal')

                        const json = await res.json()
                        this.counts = json.counts ?? this.counts
                    } catch {
                        this.errorMessage = 'Notifikasi gagal ditandai dibaca. Coba lagi.'
                        return
                    }
                }
                window.location.href = notif.action_url
                return
            }

            if (notif.is_read) return

            try {
                const res = await fetch(`/notifications/${notif.id}/read`, {
                    method:  'POST',
                    headers: this._headers(),
                })
                if (!res.ok) throw new Error('Request gagal')

                const json = await res.json()
                const item = this.notifications.find(n => n.id === notif.id)
                if (item) item.is_read = true
                this.counts = json.counts ?? this.counts
                this.errorMessage = ''
            } catch {
                await this.fetchNotifications()
                this.errorMessage = 'Notifikasi gagal ditandai dibaca. Coba lagi.'
            }
        },

        // ── Tandai semua dibaca ───────────────────────────────────────────────
        async markAllRead() {
            try {
                const body = this.filter !== 'all' ? JSON.stringify({ type: this.filter }) : '{}'
                const res = await fetch('/notifications/read-all', {
                    method:  'POST',
                    headers: this._headers(),
                    body,
                })
                if (!res.ok) throw new Error('Request gagal')

                const json = await res.json()
                this.notifications = this.notifications.map(n => ({ ...n, is_read: true }))
                this.counts = json.counts
                this.errorMessage = ''
            } catch {
                await this.fetchNotifications()
                await this.fetchCount()
                this.errorMessage = 'Notifikasi gagal ditandai dibaca. Coba lagi.'
            }
        },

        // ── Hapus satu ────────────────────────────────────────────────────────
        async deleteNotif(id) {
            try {
                const res = await fetch(`/notifications/${id}`, {
                    method:  'DELETE',
                    headers: this._headers(),
                })
                if (!res.ok) throw new Error('Request gagal')

                const json = await res.json()
                this.notifications = this.notifications.filter(n => n.id !== id)
                this.counts = json.counts ?? this.counts
                this.errorMessage = ''
            } catch {
                await this.fetchNotifications()
                await this.fetchCount()
                this.errorMessage = 'Notifikasi gagal dihapus. Coba lagi.'
            }
        },

        // ── Ganti filter ──────────────────────────────────────────────────────
        changeFilter(key) {
            this.filter = key
            this.page   = 1
            this.hasMore = false
            this.fetchNotifications(false)
        },

        // ── Load more ─────────────────────────────────────────────────────────
        loadMore() {
            if (!this.loading && this.hasMore) {
                this.page++
                this.fetchNotifications(true)
            }
        },

        // ── Helpers ───────────────────────────────────────────────────────────
        tabCount(key) {
            if (key === 'all') return this.counts.total
            return this.counts[key] ?? 0
        },

        iconBg(type) {
            const map = {
                rental_new:    'notif-icon-wrap ni-blue',
                rental_return: 'notif-icon-wrap ni-green',
                rental_late:   'notif-icon-wrap ni-red',
                payment:       'notif-icon-wrap ni-emerald',
                reminder:      'notif-icon-wrap ni-amber',
                system:        'notif-icon-wrap ni-slate',
            }
            return map[type] ?? 'notif-icon-wrap ni-slate'
        },

        dotColor(type) {
            const map = {
                rental_new:    'notif-dot dot-blue',
                rental_return: 'notif-dot dot-green',
                rental_late:   'notif-dot dot-red',
                payment:       'notif-dot dot-emerald',
                reminder:      'notif-dot dot-amber',
                system:        'notif-dot dot-slate',
            }
            return map[type] ?? 'notif-dot dot-slate'
        },

        _headers() {
            return {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            }
        },
    }
}
