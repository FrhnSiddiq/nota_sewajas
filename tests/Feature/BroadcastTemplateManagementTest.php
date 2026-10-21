<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Broadcast\BroadcastTemplate;
use App\Models\User;
use Tests\TestCase;

class BroadcastTemplateManagementTest extends TestCase
{
    public function test_super_admin_can_create_edit_disable_and_archive_a_template(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'branch_access_type' => 'all',
            'is_active' => true,
        ]);
        $branch = $this->createBranch('TPL01');

        $this->actingAs($user)
            ->get(route('broadcast-campaigns.index'))
            ->assertOk()
            ->assertSee('Template Pesan');

        $this->post(route('broadcast-campaigns.templates.store'), [
            'name' => 'Promo Akhir Pekan',
            'category' => 'Promosi',
            'content' => 'Halo {{name}}, hubungi {{phone}}.',
            'branch_id' => $branch->id,
        ])->assertRedirect(route('broadcast-campaigns.templates.index'));

        $template = BroadcastTemplate::query()->where('name', 'Promo Akhir Pekan')->firstOrFail();
        $this->assertSame(['name', 'phone'], $template->variables);
        $this->assertTrue($template->is_active);

        $this->get(route('broadcast-campaigns.templates.index'))
            ->assertOk()
            ->assertSee('Halo {{name}}, hubungi {{phone}}.');

        $this->get(route('broadcast-campaigns.create'))
            ->assertOk()
            ->assertSee('Promo Akhir Pekan')
            ->assertSee('Halo {{name}}, hubungi {{phone}}.');

        $this->patch(route('broadcast-campaigns.templates.update', $template), [
            'name' => 'Promo Mingguan',
            'category' => 'Promosi',
            'content' => 'Selamat datang, {{name}}.',
            'branch_id' => '',
        ])->assertRedirect(route('broadcast-campaigns.templates.index'));

        $this->assertDatabaseHas('broadcast_templates', [
            'id' => $template->id,
            'name' => 'Promo Mingguan',
            'branch_id' => null,
        ]);
        $this->assertSame(['name'], $template->fresh()->variables);

        $this->patch(route('broadcast-campaigns.templates.toggle', $template->fresh()))
            ->assertRedirect();
        $this->assertDatabaseHas('broadcast_templates', [
            'id' => $template->id,
            'is_active' => false,
        ]);

        $this->delete(route('broadcast-campaigns.templates.destroy', $template->fresh()))
            ->assertRedirect(route('broadcast-campaigns.templates.index'));
        $this->assertSoftDeleted('broadcast_templates', ['id' => $template->id]);
    }

    public function test_admin_sees_global_and_own_branch_templates_but_not_other_branch_templates(): void
    {
        $branch = $this->createBranch('TPL02');
        $otherBranch = $this->createBranch('TPL03');
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'branch_access_type' => 'all',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_TOKO,
            'branch_id' => $branch->id,
            'branch_access_type' => 'specific',
            'is_active' => true,
        ]);

        $globalTemplate = BroadcastTemplate::create([
            'name' => 'Template dari Super Admin',
            'category' => 'Informasi',
            'content' => 'Pesan global.',
            'is_active' => true,
            'branch_id' => null,
            'created_by' => $superAdmin->id,
        ]);
        BroadcastTemplate::create([
            'name' => 'Template cabang sendiri',
            'category' => 'Informasi',
            'content' => 'Pesan cabang.',
            'is_active' => true,
            'branch_id' => $branch->id,
            'created_by' => $admin->id,
        ]);
        BroadcastTemplate::create([
            'name' => 'Template cabang lain',
            'category' => 'Informasi',
            'content' => 'Pesan cabang lain.',
            'is_active' => true,
            'branch_id' => $otherBranch->id,
            'created_by' => $superAdmin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('broadcast-campaigns.templates.index'))
            ->assertOk()
            ->assertSee('Template dari Super Admin')
            ->assertSee('Template cabang sendiri')
            ->assertDontSee('Template cabang lain')
            ->assertSee('Template global');

        $this->get(route('broadcast-campaigns.create'))
            ->assertOk()
            ->assertSee('Template dari Super Admin')
            ->assertSee('Template cabang sendiri')
            ->assertDontSee('Template cabang lain');

        $this->get(route('broadcast-campaigns.templates.edit', $globalTemplate))
            ->assertForbidden();
    }

    public function test_admin_template_is_global_and_admin_cannot_submit_branch_scope(): void
    {
        $branch = $this->createBranch('TPL04');
        $otherBranch = $this->createBranch('TPL05');
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_TOKO,
            'branch_id' => $branch->id,
            'branch_access_type' => 'specific',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('broadcast-campaigns.templates.create'))
            ->assertOk()
            ->assertSee('Semua cabang')
            ->assertDontSee('<select id="branch_id"', false)
            ->assertDontSee('Variabel yang dikenali');

        $this->post(route('broadcast-campaigns.templates.store'), [
            'name' => 'Template milik cabang',
            'category' => 'Informasi',
            'content' => 'Pesan untuk cabang.',
        ])->assertRedirect(route('broadcast-campaigns.templates.index'));

        $template = BroadcastTemplate::query()->where('name', 'Template milik cabang')->firstOrFail();
        $this->assertNull($template->branch_id);
        $this->assertSame($admin->id, $template->created_by);

        $this->from(route('broadcast-campaigns.templates.create'))
            ->post(route('broadcast-campaigns.templates.store'), [
                'name' => 'Template lintas cabang',
                'category' => 'Informasi',
                'content' => 'Tidak boleh.',
                'branch_id' => $otherBranch->id,
            ])
            ->assertRedirect(route('broadcast-campaigns.templates.create'))
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('broadcast_templates', ['name' => 'Template lintas cabang']);

        $otherAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN_TOKO,
            'branch_id' => $otherBranch->id,
            'branch_access_type' => 'specific',
            'is_active' => true,
        ]);
        $this->actingAs($otherAdmin)
            ->get(route('broadcast-campaigns.templates.index'))
            ->assertOk()
            ->assertSee('Template milik cabang');
        $this->get(route('broadcast-campaigns.templates.edit', $template))
            ->assertForbidden();
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
}
