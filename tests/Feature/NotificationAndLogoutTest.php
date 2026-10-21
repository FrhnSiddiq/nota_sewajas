<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Notification;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationAndLogoutTest extends TestCase
{
    public function test_html_notification_actions_redirect_and_only_change_the_signed_in_users_rows(): void
    {
        $user = $this->makeUser('first');
        $otherUser = $this->makeUser('other');
        $firstUnread = $this->makeNotification($user, false);
        $secondUnread = $this->makeNotification($user, false);
        $otherUnread = $this->makeNotification($otherUser, false);

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notifications', ['id' => $firstUnread->id, 'is_read' => true]);
        $this->assertDatabaseHas('notifications', ['id' => $secondUnread->id, 'is_read' => true]);
        $this->assertDatabaseHas('notifications', ['id' => $otherUnread->id, 'is_read' => false]);

        $singleUnread = $this->makeNotification($user, false);
        $this->from(route('notifications.show', $singleUnread->id))
            ->post(route('notifications.read', $singleUnread->id))
            ->assertRedirect(route('notifications.show', $singleUnread->id))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('notifications', ['id' => $singleUnread->id, 'is_read' => true]);

        $ownedToDelete = $this->makeNotification($user, true);
        $this->from(route('notifications.index'))
            ->delete(route('notifications.destroy', $ownedToDelete->id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('notifications', ['id' => $ownedToDelete->id]);
    }

    public function test_ajax_notification_actions_keep_json_and_reject_other_users_notifications(): void
    {
        $user = $this->makeUser('ajax');
        $otherUser = $this->makeUser('ajax-other');
        $owned = $this->makeNotification($user, false);
        $foreign = $this->makeNotification($otherUser, false);

        $this->actingAs($user)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('counts.total', 0);

        $this->assertDatabaseHas('notifications', ['id' => $foreign->id, 'is_read' => false]);

        $this->postJson(route('notifications.read', $foreign->id))->assertNotFound();
        $this->assertDatabaseHas('notifications', ['id' => $foreign->id, 'is_read' => false]);
        $this->deleteJson(route('notifications.destroy', $foreign->id))->assertNotFound();
        $this->assertDatabaseHas('notifications', ['id' => $foreign->id]);

        $ajaxUnread = $this->makeNotification($user, false);
        $this->postJson(route('notifications.read', $ajaxUnread->id))
            ->assertOk()
            ->assertJsonPath('counts.total', 0);
        $this->assertDatabaseHas('notifications', ['id' => $ajaxUnread->id, 'is_read' => true]);

        $this->deleteJson(route('notifications.destroy', $owned->id))
            ->assertOk()
            ->assertJsonPath('counts.total', 0);
        $this->assertDatabaseMissing('notifications', ['id' => $owned->id]);
    }

    public function test_prune_command_deletes_notifications_older_than_thirty_days_including_unread(): void
    {
        $user = $this->makeUser('prune-notifications');
        $oldRead = $this->makeNotification($user, true);
        $oldUnread = $this->makeNotification($user, false);
        $recent = $this->makeNotification($user, false);
        $boundary = $this->makeNotification($user, true);
        $cutoff = now()->subDays(30);

        DB::table('notifications')->whereIn('id', [$oldRead->id, $oldUnread->id])->update([
            'created_at' => $cutoff->copy()->subSecond(),
        ]);
        DB::table('notifications')->where('id', $boundary->id)->update([
            'created_at' => $cutoff,
        ]);
        DB::table('notifications')->where('id', $recent->id)->update([
            'created_at' => now()->subDays(29),
        ]);

        $this->artisan('notifications:prune')
            ->expectsOutput('Deleted 2 expired notifications.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', ['id' => $oldRead->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $oldUnread->id]);
        $this->assertDatabaseHas('notifications', ['id' => $boundary->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recent->id]);
    }

    public function test_broadcast_notification_detail_uses_light_message_panel_and_alert_icon_without_technical_meta(): void
    {
        $user = $this->makeUser('broadcast-detail');
        $notification = Notification::create([
            'notifiable_type' => 'user',
            'notifiable_id' => $user->id,
            'type' => 'system',
            'title' => 'Broadcast: Info',
            'message' => 'Pesan broadcast',
            'meta' => [
                'broadcast' => true,
                'role' => 'sales',
                'target' => 'branch',
                'branch_id' => 2,
                'sender_id' => 1,
            ],
        ]);

        $this->actingAs($user)
            ->get(route('notifications.show', $notification->id))
            ->assertOk()
            ->assertSee('Pesan broadcast')
            ->assertSee('bg-white', false)
            ->assertSee('class="card overflow-hidden"', false)
            ->assertSee('🔔')
            ->assertDontSee('⚙️')
            ->assertDontSee('bg-[#1a1d26]', false)
            ->assertDontSee('sender_id')
            ->assertDontSee('branch_id');
    }

    public function test_logout_forms_confirm_and_logout_invalidates_the_authenticated_session(): void
    {
        $user = $this->makeUser('logout');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('id="logoutSidebarForm"', false)
            ->assertSee("onclick=\"confirmAction('logoutSidebarForm', 'Keluar dari Sistem', 'Apakah Anda yakin ingin keluar dari web ini?', 'Ya, Keluar', '#dc2626')\"", false)
            ->assertSee('id="logoutDropdownForm"', false)
            ->assertSee("onclick=\"confirmAction('logoutDropdownForm', 'Keluar dari Sistem', 'Apakah Anda yakin ingin keluar dari web ini?', 'Ya, Keluar', '#dc2626')\"", false)
            ->assertDontSee("confirm('Apakah Anda yakin ingin keluar?')", false)
            ->assertSee('method="POST" action="' . route('logout') . '"', false)
            ->assertSee('name="_token"', false);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_sidebar_keeps_scroll_position_and_marks_activity_log_as_active(): void
    {
        $user = $this->makeUser('sidebar');

        $this->actingAs($user)
            ->get(route('reports.activity-log'))
            ->assertOk()
            ->assertSee('id="sidebar-navigation"', false)
            ->assertSee(
                'class="sidebar-item flex items-center gap-3 rounded-[16px] px-3 py-2.5 text-sm font-semibold ds-transition active"',
                false
            )
            ->assertSee("sessionStorage.setItem('sidebar-nav-scroll-top'", false)
            ->assertSee("sessionStorage.removeItem('sidebar-nav-scroll-top')", false);
    }

    public function test_configured_app_logo_is_used_as_browser_favicon(): void
    {
        $logoPath = 'settings/app-logos/current-logo.png';
        Storage::disk('public')->put($logoPath, 'logo');
        DB::table('settings')->updateOrInsert(
            ['key' => 'app_logo'],
            ['value' => $logoPath]
        );
        SettingsService::forget();

        $faviconUrl = Storage::disk('public')->url($logoPath);

        try {
            $this->get(route('login'))
                ->assertOk()
                ->assertSee('rel="icon" href="' . $faviconUrl . '"', false);

            $this->actingAs($this->makeUser('favicon'))
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('rel="icon" href="' . $faviconUrl . '"', false);
        } finally {
            Storage::disk('public')->delete($logoPath);
            SettingsService::forget();
        }
    }

    public function test_admin_toko_cannot_access_global_activity_logs_or_see_the_menu(): void
    {
        $branch = Branch::create([
            'name' => 'Cabang Admin',
            'code' => 'ADM01',
            'address' => 'Alamat Cabang',
            'phone' => '021123456',
            'email' => 'admin.branch@example.test',
            'city' => 'Yogyakarta',
            'province' => 'DI Yogyakarta',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'email' => 'admin-log@example.test',
            'role' => User::ROLE_ADMIN_TOKO,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.activity-log'))
            ->assertForbidden();

        $this->get(route('rentals.activity-log'))->assertForbidden();
        $this->get(route('reports.activity-log.export.csv'))->assertForbidden();
        $this->get(route('branches.index'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Log Keseluruhan');
    }

    private function makeUser(string $suffix): User
    {
        return User::factory()->create([
            'email' => "{$suffix}@example.test",
            'role' => User::ROLE_SUPER_ADMIN,
            'branch_access_type' => 'all',
            'is_active' => true,
        ]);
    }

    private function makeNotification(User $user, bool $read): Notification
    {
        return Notification::create([
            'notifiable_type' => 'user',
            'notifiable_id' => $user->id,
            'type' => 'system',
            'title' => 'Pemberitahuan test',
            'message' => 'Isi notifikasi untuk pengujian.',
            'is_read' => $read,
            'read_at' => $read ? now() : null,
        ]);
    }
}
