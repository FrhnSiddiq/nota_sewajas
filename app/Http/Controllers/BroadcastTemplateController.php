<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Broadcast\BroadcastTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BroadcastTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $templates = BroadcastTemplate::query()
            ->with(['branch', 'creator'])
            ->when(!$user->isSuperAdmin(), function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->whereNull('branch_id')->orWhere('branch_id', $user->branch_id);
                });
            })
            ->latest()
            ->paginate(20);

        return view('broadcast_templates.index', compact('templates'));
    }

    public function create(Request $request): View
    {
        return view('broadcast_templates.form', [
            'template' => new BroadcastTemplate(),
            'branches' => $this->availableBranches($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80'],
            'content' => ['required', 'string', 'max:2000'],
            'branch_id' => [
                Rule::prohibitedIf(!$user->isSuperAdmin()),
                'nullable',
                'integer',
                'exists:branches,id',
            ],
        ]);
        $branchId = $user->isSuperAdmin() ? ($validated['branch_id'] ?? null) : null;
        $this->authorizeBranch($request, $branchId);

        BroadcastTemplate::create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'variables' => $this->extractVariables($validated['content']),
            'is_active' => true,
            'branch_id' => $branchId,
            'created_by' => $user->id,
        ]);

        return redirect()->route('broadcast-campaigns.templates.index')
            ->with('success', 'Template pesan berhasil dibuat.');
    }

    public function edit(Request $request, BroadcastTemplate $template): View
    {
        $this->authorizeTemplate($request, $template);

        return view('broadcast_templates.form', [
            'template' => $template,
            'branches' => $this->availableBranches($request),
        ]);
    }

    public function update(Request $request, BroadcastTemplate $template): RedirectResponse
    {
        $this->authorizeTemplate($request, $template);
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80'],
            'content' => ['required', 'string', 'max:2000'],
            'branch_id' => [
                Rule::prohibitedIf(!$user->isSuperAdmin()),
                'nullable',
                'integer',
                'exists:branches,id',
            ],
        ]);
        $branchId = $user->isSuperAdmin() ? ($validated['branch_id'] ?? null) : $user->branch_id;
        if ($user->isSuperAdmin()) {
            $this->authorizeBranch($request, $branchId);
        } else {
            $branchId = $template->branch_id;
        }

        $template->update([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'variables' => $this->extractVariables($validated['content']),
            'branch_id' => $branchId,
        ]);

        return redirect()->route('broadcast-campaigns.templates.index')
            ->with('success', 'Template pesan berhasil diperbarui.');
    }

    public function toggle(Request $request, BroadcastTemplate $template): RedirectResponse
    {
        $this->authorizeTemplate($request, $template);
        $template->update(['is_active' => !$template->is_active]);

        return back()->with('success', 'Status template berhasil diperbarui.');
    }

    public function destroy(Request $request, BroadcastTemplate $template): RedirectResponse
    {
        $this->authorizeTemplate($request, $template);
        $template->delete();

        return redirect()->route('broadcast-campaigns.templates.index')
            ->with('success', 'Template pesan berhasil diarsipkan.');
    }

    private function availableBranches(Request $request)
    {
        $user = $request->user();

        return Branch::query()
            ->when(!$user->isSuperAdmin(), fn ($query) => $query->where('id', $user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function authorizeTemplate(Request $request, BroadcastTemplate $template): void
    {
        $user = $request->user();
        if (!$user->isSuperAdmin()) {
            $canManageBranchTemplate = $template->branch_id !== null
                && (int) $template->branch_id === (int) $user->branch_id;
            $canManageOwnGlobalTemplate = $template->branch_id === null
                && (int) $template->created_by === (int) $user->id;

            abort_unless($canManageBranchTemplate || $canManageOwnGlobalTemplate, 403);
        }
    }

    private function authorizeBranch(Request $request, ?int $branchId): void
    {
        if ($branchId === null) {
            return;
        }

        abort_unless($request->user()->canAccessBranch((int) $branchId), 403);
    }

    /**
     * @return array<int, string>
     */
    private function extractVariables(string $content): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*\}\}/', $content, $matches);

        return array_values(array_unique($matches[1]));
    }
}
