<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Broadcast\BroadcastCampaign;
use App\Models\Broadcast\BroadcastLog;
use App\Models\Broadcast\BroadcastTemplate;
use App\Models\Customer;
use App\Models\User;
use Tests\TestCase;

class BroadcastCampaignRecipientSelectionTest extends TestCase
{
    public function test_create_form_explains_channel_driven_recipient_type_and_branch_scoped_sales_picker(): void
    {
        $branch = $this->createBranch('UI001');
        $sender = $this->createUser(User::ROLE_SUPER_ADMIN, null, 'ui-sender');
        $this->createUser(User::ROLE_SALES, $branch->id, 'ui-sales');

        $this->actingAs($sender)
            ->get(route('broadcast-campaigns.create'))
            ->assertOk()
            ->assertSee('recipientTypeLabel', false)
            ->assertSee('type="checkbox"', false)
            ->assertSee('id="customerDropdown"', false)
            ->assertSee('id="templateFields"', false)
            ->assertSee('id="customMessageContainer"', false)
            ->assertSee("customMessageContainer.classList.add('hidden')", false)
            ->assertDontSee('Panduan')
            ->assertSee(route('broadcast-campaigns.templates.index'))
            ->assertSee('data-branch-id="' . $branch->id . '"', false);
    }

    public function test_in_app_campaign_can_use_a_template_without_custom_message(): void
    {
        $branch = $this->createBranch('TPL01');
        $sender = $this->createUser(User::ROLE_SUPER_ADMIN, null, 'template-sender');
        $sales = $this->createUser(User::ROLE_SALES, $branch->id, 'template-sales');
        $template = BroadcastTemplate::create([
            'name' => 'Template In-App',
            'category' => 'Pengumuman',
            'content' => 'Pesan untuk {{name}}',
            'variables' => [],
            'is_active' => true,
            'created_by' => $sender->id,
        ]);

        $this->actingAs($sender)
            ->post(route('broadcast-campaigns.store'), [
                'name' => 'Campaign Template In-App',
                'channel' => 'in_app',
                'recipient_type' => 'user',
                'target_type' => 'sales',
                'target_filters' => ['branch_id' => $branch->id],
                'recipient_ids' => [$sales->id],
                'template_id' => $template->id,
            ])
            ->assertRedirect();

        $campaign = BroadcastCampaign::query()->where('name', 'Campaign Template In-App')->firstOrFail();
        $this->assertSame($template->id, $campaign->template_id);
        $this->assertNull($campaign->custom_message);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'user',
            'notifiable_id' => $sales->id,
            'title' => 'Broadcast: Campaign Template In-App',
            'message' => 'Pesan untuk User template-sales',
        ]);
        $this->assertDatabaseHas('broadcast_logs', [
            'campaign_id' => $campaign->id,
            'recipient_id' => $sales->id,
            'rendered_message' => 'Pesan untuk User template-sales',
        ]);
    }

    public function test_campaign_requires_a_template_or_custom_message(): void
    {
        $sender = $this->createUser(User::ROLE_SUPER_ADMIN, null, 'empty-message-sender');

        $this->actingAs($sender)
            ->from(route('broadcast-campaigns.create'))
            ->post(route('broadcast-campaigns.store'), [
                'name' => 'Campaign Tanpa Pesan',
                'channel' => 'in_app',
                'recipient_type' => 'user',
                'target_type' => 'all',
            ])
            ->assertRedirect(route('broadcast-campaigns.create'))
            ->assertSessionHasErrors('custom_message');

        $this->assertDatabaseMissing('broadcast_campaigns', [
            'name' => 'Campaign Tanpa Pesan',
        ]);
    }

    public function test_in_app_sales_campaign_only_notifies_selected_sales_from_selected_branch(): void
    {
        $branch = $this->createBranch('SEL01');
        $otherBranch = $this->createBranch('SEL02');
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN, null, 'sender');
        $selectedSales = $this->createUser(User::ROLE_SALES, $branch->id, 'selected-sales');
        $unselectedSales = $this->createUser(User::ROLE_SALES, $branch->id, 'unselected-sales');
        $otherBranchSales = $this->createUser(User::ROLE_SALES, $otherBranch->id, 'other-branch-sales');
        $branchAdmin = $this->createUser(User::ROLE_ADMIN_TOKO, $branch->id, 'branch-admin');

        $response = $this->actingAs($admin)->post(route('broadcast-campaigns.store'), [
            'name' => 'Sales cabang terpilih',
            'channel' => 'in_app',
            'recipient_type' => 'user',
            'target_type' => 'sales',
            'target_filters' => ['branch_id' => $branch->id],
            'recipient_ids' => [$selectedSales->id],
            'custom_message' => 'Informasi untuk Sales',
        ]);

        $response->assertRedirect();

        $campaign = BroadcastCampaign::query()->where('name', 'Sales cabang terpilih')->firstOrFail();
        $this->assertSame($branch->id, $campaign->branch_id);
        $this->assertSame([$selectedSales->id], $campaign->logs()->pluck('recipient_id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseMissing('broadcast_logs', [
            'campaign_id' => $campaign->id,
            'recipient_id' => $unselectedSales->id,
        ]);
        $this->assertDatabaseMissing('broadcast_logs', [
            'campaign_id' => $campaign->id,
            'recipient_id' => $otherBranchSales->id,
        ]);
        $this->assertDatabaseMissing('broadcast_logs', [
            'campaign_id' => $campaign->id,
            'recipient_id' => $branchAdmin->id,
        ]);
    }

    public function test_in_app_branch_target_includes_all_eligible_users_in_that_branch_only(): void
    {
        $branch = $this->createBranch('BRN01');
        $otherBranch = $this->createBranch('BRN02');
        $sender = $this->createUser(User::ROLE_ADMIN_TOKO, $branch->id, 'branch-sender');
        $sameBranchSales = $this->createUser(User::ROLE_SALES, $branch->id, 'same-branch-sales');
        $sameBranchAdmin = $this->createUser(User::ROLE_ADMIN_TOKO, $branch->id, 'same-branch-admin');
        $otherBranchSales = $this->createUser(User::ROLE_SALES, $otherBranch->id, 'remote-sales');
        $inactiveSales = $this->createUser(User::ROLE_SALES, $branch->id, 'inactive-sales', false);

        $campaign = $this->createInAppCampaign($sender, $branch, 'branch', []);
        $campaign->queueMessages();

        $recipientIds = $campaign->logs()->pluck('recipient_id')->map(fn ($id) => (int) $id)->all();
        sort($recipientIds);
        $expectedIds = [$sameBranchSales->id, $sameBranchAdmin->id];
        sort($expectedIds);

        $this->assertSame($expectedIds, $recipientIds);
        $this->assertDatabaseMissing('broadcast_logs', [
            'campaign_id' => $campaign->id,
            'recipient_id' => $otherBranchSales->id,
        ]);
        $this->assertDatabaseMissing('broadcast_logs', [
            'campaign_id' => $campaign->id,
            'recipient_id' => $inactiveSales->id,
        ]);
    }

    public function test_in_app_all_target_spans_accessible_branches_without_including_sender_or_other_roles(): void
    {
        $branch = $this->createBranch('ALL01');
        $otherBranch = $this->createBranch('ALL02');
        $sender = $this->createUser(User::ROLE_ADMIN_TOKO, $branch->id, 'all-sender');
        $sameBranchSales = $this->createUser(User::ROLE_SALES, $branch->id, 'all-local-sales');
        $otherBranchSales = $this->createUser(User::ROLE_SALES, $otherBranch->id, 'all-remote-sales');
        $customerRoleUser = $this->createUser(User::ROLE_CUSTOMER, $otherBranch->id, 'customer-role');
        $optedOutSales = $this->createUser(User::ROLE_SALES, $otherBranch->id, 'opted-out-sales');
        $optedOutSales->update(['opt_out' => true]);

        $campaign = $this->createInAppCampaign($sender, $branch, 'all', []);
        $campaign->queueMessages();

        $recipientIds = $campaign->logs()->pluck('recipient_id')->map(fn ($id) => (int) $id)->all();
        sort($recipientIds);
        $expectedIds = [$sameBranchSales->id, $otherBranchSales->id];
        sort($expectedIds);

        $this->assertSame($expectedIds, $recipientIds);
        $this->assertDatabaseMissing('broadcast_logs', [
            'campaign_id' => $campaign->id,
            'recipient_id' => $sender->id,
        ]);
        $this->assertDatabaseMissing('broadcast_logs', [
            'campaign_id' => $campaign->id,
            'recipient_id' => $customerRoleUser->id,
        ]);
        $this->assertDatabaseMissing('broadcast_logs', [
            'campaign_id' => $campaign->id,
            'recipient_id' => $optedOutSales->id,
        ]);
    }

    public function test_in_app_branch_and_sales_targets_require_branch_and_sales_selection(): void
    {
        $branch = $this->createBranch('REQ01');
        $sender = $this->createUser(User::ROLE_SUPER_ADMIN, null, 'required-sender');

        $this->actingAs($sender)
            ->from(route('broadcast-campaigns.create'))
            ->post(route('broadcast-campaigns.store'), [
                'name' => 'Missing sales selection',
                'channel' => 'in_app',
                'recipient_type' => 'user',
                'target_type' => 'sales',
                'custom_message' => 'Informasi untuk Sales',
            ])
            ->assertRedirect(route('broadcast-campaigns.create'))
            ->assertSessionHasErrors(['target_filters.branch_id', 'recipient_ids']);

        $this->assertDatabaseMissing('broadcast_campaigns', ['name' => 'Missing sales selection']);
    }

    public function test_in_app_sales_target_rejects_sales_from_another_branch(): void
    {
        $branch = $this->createBranch('SCP01');
        $otherBranch = $this->createBranch('SCP02');
        $sender = $this->createUser(User::ROLE_SUPER_ADMIN, null, 'scope-sender');
        $otherBranchSales = $this->createUser(User::ROLE_SALES, $otherBranch->id, 'scope-sales');

        $this->actingAs($sender)
            ->from(route('broadcast-campaigns.create'))
            ->post(route('broadcast-campaigns.store'), [
                'name' => 'Sales dari cabang lain',
                'channel' => 'in_app',
                'recipient_type' => 'user',
                'target_type' => 'sales',
                'target_filters' => ['branch_id' => $branch->id],
                'recipient_ids' => [$otherBranchSales->id],
                'custom_message' => 'Informasi untuk Sales',
            ])
            ->assertRedirect(route('broadcast-campaigns.create'))
            ->assertSessionHasErrors('recipient_ids');

        $this->assertDatabaseMissing('broadcast_campaigns', ['name' => 'Sales dari cabang lain']);
    }

    public function test_target_options_are_restricted_to_the_selected_channel(): void
    {
        $branch = $this->createBranch('TGT01');
        $sender = $this->createUser(User::ROLE_SUPER_ADMIN, null, 'target-sender');

        $this->actingAs($sender)
            ->from(route('broadcast-campaigns.create'))
            ->post(route('broadcast-campaigns.store'), [
                'name' => 'WhatsApp dengan target Sales',
                'channel' => 'whatsapp',
                'recipient_type' => 'customer',
                'target_type' => 'sales',
                'custom_message' => 'Pesan WhatsApp',
            ])
            ->assertRedirect(route('broadcast-campaigns.create'))
            ->assertSessionHasErrors('target_type');

        $this->from(route('broadcast-campaigns.create'))
            ->post(route('broadcast-campaigns.store'), [
                'name' => 'In-App dengan target Customer',
                'channel' => 'in_app',
                'recipient_type' => 'user',
                'target_type' => 'customer',
                'custom_message' => 'Informasi internal',
            ])
            ->assertRedirect(route('broadcast-campaigns.create'))
            ->assertSessionHasErrors('target_type');

        $this->assertDatabaseMissing('broadcast_campaigns', ['name' => 'WhatsApp dengan target Sales']);
        $this->assertDatabaseMissing('broadcast_campaigns', ['name' => 'In-App dengan target Customer']);
    }

    public function test_whatsapp_campaign_cannot_be_scheduled_because_sending_is_manual(): void
    {
        $branch = $this->createBranch('WSC01');
        $sender = $this->createUser(User::ROLE_SUPER_ADMIN, null, 'whatsapp-schedule-sender');
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'name' => 'Scheduled WhatsApp Customer',
            'phone' => '081234567890',
            'is_blacklisted' => false,
            'opt_out' => false,
        ]);

        $this->actingAs($sender)
            ->from(route('broadcast-campaigns.create'))
            ->post(route('broadcast-campaigns.store'), [
                'name' => 'Scheduled WhatsApp',
                'channel' => 'whatsapp',
                'recipient_type' => 'customer',
                'target_type' => 'customer',
                'recipient_ids' => [$customer->id],
                'custom_message' => 'Pesan',
                'send_mode' => 'scheduled',
                'scheduled_at' => now()->addHour()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('broadcast-campaigns.create'))
            ->assertSessionHasErrors(['send_mode', 'scheduled_at']);

        $this->assertDatabaseMissing('broadcast_campaigns', ['name' => 'Scheduled WhatsApp']);
    }

    public function test_scheduled_in_app_campaign_shows_recipients_then_marks_them_sent_when_processed(): void
    {
        $branch = $this->createBranch('SCH01');
        $sender = $this->createUser(User::ROLE_SUPER_ADMIN, null, 'scheduled-sender');
        $sales = $this->createUser(User::ROLE_SALES, $branch->id, 'scheduled-sales');

        $this->actingAs($sender)
            ->post(route('broadcast-campaigns.store'), [
                'name' => 'Scheduled In-App',
                'channel' => 'in_app',
                'recipient_type' => 'user',
                'target_type' => 'sales',
                'target_filters' => ['branch_id' => $branch->id],
                'recipient_ids' => [$sales->id],
                'custom_message' => 'Pesan terjadwal',
                'send_mode' => 'scheduled',
                'scheduled_at' => now()->addHour()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect();

        $campaign = BroadcastCampaign::query()->where('name', 'Scheduled In-App')->firstOrFail();
        $this->assertDatabaseHas('broadcast_campaigns', [
            'id' => $campaign->id,
            'status' => 'scheduled',
            'total_target' => 1,
        ]);
        $this->assertDatabaseHas('broadcast_logs', [
            'campaign_id' => $campaign->id,
            'recipient_type' => 'user',
            'recipient_id' => $sales->id,
            'provider' => 'in_app',
            'status' => 'pending',
        ]);

        $campaign->update(['scheduled_at' => now()->subMinute()]);
        $campaign->refresh()->queueMessages();

        $this->assertSame(1, $campaign->logs()->count());
        $this->assertDatabaseHas('broadcast_logs', [
            'campaign_id' => $campaign->id,
            'recipient_type' => 'user',
            'recipient_id' => $sales->id,
            'provider' => 'in_app',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('broadcast_campaigns', [
            'id' => $campaign->id,
            'status' => 'completed',
            'total_target' => 1,
            'total_success' => 1,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'user',
            'notifiable_id' => $sales->id,
            'title' => 'Broadcast: Scheduled In-App',
            'message' => 'Pesan terjadwal',
        ]);
    }

    public function test_opening_whatsapp_link_does_not_mark_log_sent_until_manual_confirmation(): void
    {
        $branch = $this->createBranch('WAC01');
        $sender = $this->createUser(User::ROLE_SUPER_ADMIN, null, 'whatsapp-sender');
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'name' => 'Customer WhatsApp',
            'phone' => '081234567890',
            'is_blacklisted' => false,
            'opt_out' => false,
        ]);
        $campaign = BroadcastCampaign::create([
            'name' => 'WhatsApp manual confirmation',
            'type' => 'manual',
            'target_type' => 'customer',
            'recipient_type' => 'customer',
            'target_filters' => ['recipient_ids' => [$customer->id]],
            'provider' => 'whatsapp',
            'channels' => ['whatsapp'],
            'status' => 'processing',
            'created_by' => $sender->id,
            'branch_id' => $branch->id,
            'message_template' => 'Pesan untuk {{name}}',
        ]);
        $log = $campaign->logs()->create([
            'recipient_type' => 'customer',
            'recipient_id' => $customer->id,
            'phone' => $customer->phone,
            'rendered_message' => 'Pesan untuk Customer WhatsApp',
            'provider' => 'whatsapp',
            'status' => 'pending',
        ]);

        $this->actingAs($sender)
            ->get(route('broadcast-campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('min-w-[760px]', false)
            ->assertSee('rounded-[12px] border border-blue-600 bg-white px-3 text-xs font-semibold text-blue-700', false)
            ->assertSee('Konfirmasi setelah menekan Kirim di WhatsApp.');

        $this->post(route('broadcast-campaigns.send-to-customer', [$campaign, $customer]))
            ->assertRedirect('https://wa.me/6281234567890?text=Pesan+untuk+Customer+WhatsApp');

        $this->assertDatabaseHas('broadcast_logs', [
            'id' => $log->id,
            'status' => 'pending',
            'sent_at' => null,
        ]);
        $this->assertDatabaseHas('broadcast_campaigns', [
            'id' => $campaign->id,
            'status' => 'processing',
        ]);

        $this->post(route('broadcast-campaigns.confirm-customer-sent', [$campaign, $customer]))
            ->assertRedirect(route('broadcast-campaigns.show', $campaign))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('broadcast_logs', [
            'id' => $log->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('broadcast_campaigns', [
            'id' => $campaign->id,
            'status' => 'completed',
            'total_success' => 1,
        ]);
    }

    private function createBranch(string $code): Branch
    {
        return Branch::create([
            'name' => "Cabang {$code}",
            'code' => $code,
            'address' => 'Alamat pengujian',
            'is_active' => true,
        ]);
    }

    private function createUser(string $role, ?int $branchId, string $suffix, bool $active = true): User
    {
        return User::create([
            'name' => "User {$suffix}",
            'email' => "{$suffix}@example.test",
            'password' => 'password',
            'role' => $role,
            'branch_id' => $branchId,
            'is_active' => $active,
            'opt_out' => false,
        ]);
    }

    private function createInAppCampaign(User $sender, Branch $branch, string $target, array $recipientIds): BroadcastCampaign
    {
        return BroadcastCampaign::create([
            'name' => "Campaign {$target}",
            'type' => 'manual',
            'target_type' => $target,
            'recipient_type' => 'user',
            'target_filters' => [
                'branch_id' => $target === 'all' ? null : $branch->id,
                'recipient_ids' => $recipientIds,
            ],
            'provider' => 'in_app',
            'channels' => ['in_app'],
            'status' => 'draft',
            'created_by' => $sender->id,
            'branch_id' => $target === 'all' ? $sender->branch_id : $branch->id,
            'message_template' => 'Pesan pengujian',
        ]);
    }
}
