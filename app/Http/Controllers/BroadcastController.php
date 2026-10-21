<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BroadcastController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $broadcasts = Notification::query()
            ->where('type', 'system')
            ->where('title', 'like', 'Broadcast:%')
            ->when(!$user->isSuperAdmin(), fn (Builder $q) => $q->whereIn('branch_id', $user->accessible_branch_ids))
            ->latest()
            ->limit(40)
            ->get();

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::whereIn('id', $user->accessible_branch_ids)->get(['id', 'name']);

        $roleOptions = [
            User::ROLE_ADMIN_TOKO => 'Admin Cabang',
            User::ROLE_SALES => 'Sales',
        ];

        if ($user->isSuperAdmin()) {
            $roleOptions = [User::ROLE_SUPER_ADMIN => 'Super Admin'] + $roleOptions;
        }

        return view('broadcasts.index', compact('broadcasts', 'branches', 'roleOptions'));
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1200'],
            'target' => ['required', Rule::in(['all', 'role', 'branch'])],
            'role' => ['required_if:target,role', 'nullable', Rule::in([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN_TOKO, User::ROLE_SALES])],
            'branch_id' => ['required_if:target,branch', 'nullable', 'integer', 'exists:branches,id'],
            'action_url' => ['nullable', 'url', 'max:255'],
        ]);

        $recipients = User::query()->active();

        if (!$user->isSuperAdmin()) {
            $recipients->whereIn('branch_id', $user->accessible_branch_ids)
                ->whereIn('role', [User::ROLE_ADMIN_TOKO, User::ROLE_SALES]);
        }

        if ($validated['target'] === 'role') {
            $recipients->where('role', $validated['role']);
        }

        if ($validated['target'] === 'branch') {
            $branchId = $user->isSuperAdmin()
                ? ($validated['branch_id'] ?? null)
                : ($validated['branch_id'] ?? $user->branch_id);
            if (!$user->isSuperAdmin() && $branchId !== null) {
                abort_unless($user->canAccessBranch((int) $branchId), 403, 'Cabang di luar cakupan akses.');
            }
            if ($branchId !== null) {
                $recipients->where('branch_id', $branchId);
            }
            $validated['branch_id'] = $branchId;
        }

        if ($validated['target'] === 'all' && !$user->isSuperAdmin()) {
            $validated['branch_id'] = $user->branch_id;
        }

        $recipientIds = $recipients->pluck('id')->all();

        if ($recipientIds === []) {
            return back()
                ->withInput()
                ->with('error', 'Tidak ada penerima yang cocok dengan target broadcast.');
        }

        $this->notificationService->broadcast(
            array_map(fn ($id) => ['type' => User::class, 'id' => $id], $recipientIds),
            'system',
            'Broadcast: ' . $validated['title'],
            $validated['message'],
            [
                'broadcast' => true,
                'sender_id' => $user->id,
                'sender_name' => $user->name,
                'target' => $validated['target'],
                'role' => $validated['role'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'recipient_count' => count($recipientIds),
            ],
            $validated['branch_id'] ?? null,
            $validated['action_url'] ?? null,
            'send',
            'blue'
        );

        return redirect()
            ->route('broadcasts.index')
            ->with('success', 'Broadcast berhasil dikirim ke ' . count($recipientIds) . ' pengguna.');
    }

    public function destroy(Request $request, Notification $broadcast)
    {
        $user = $request->user();
        if (!$user || (!$user->isSuperAdmin() && !$user->isAdminToko())) {
            abort(403, 'Unauthorized action.');
        }

        if (!$broadcast->title || !str_starts_with($broadcast->title, 'Broadcast:')) {
            abort(404);
        }

        $broadcast->delete();

        return redirect()->route('broadcasts.index')
            ->with('success', 'Broadcast berhasil dihapus.');
    }
}
