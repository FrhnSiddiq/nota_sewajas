<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->query('type', 'all');
        $user = $request->user();

        $query = Notification::query()
            ->where('notifiable_type', 'user')
            ->where('notifiable_id', $user->id)
            ->latest();

        if ($type !== 'all') {
            $query->byType($type);
        }

        $notifications = $query->paginate(20)->withQueryString();

        return view('notifications.index', compact('notifications', 'type'));
    }

    public function data(Request $request): JsonResponse
    {
        $type  = $request->input('type', $request->input('filter', 'all'));
        $user  = $request->user();

        $query = Notification::query()
            ->where('notifiable_type', 'user')
            ->where('notifiable_id', $user->id)
            ->latest();

        if ($type !== 'all') {
            $query->byType($type);
        }

        $paginator = $query->paginate(20);
        $notifications = $paginator->getCollection()->map(fn($n) => [
            'id'         => $n->id,
            'type'       => $n->type,
            'title'      => $n->title,
            'message'    => $n->message,
            'is_read'    => $n->is_read,
            'time_ago'   => $n->time_ago,
            'icon_name'  => $n->icon_name,
            'icon_class' => $n->icon_class,
            'meta'       => $n->meta,
            'action_url' => $n->action_url,
        ]);

        return response()->json([
            'notifications' => $notifications,
            'counts'        => $this->getCounts(),
            'has_more'      => $paginator->hasMorePages(),
        ]);
    }

    public function count(): JsonResponse
    {
        return response()->json($this->getCounts());
    }

    public function markRead(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $notification = Notification::where('notifiable_type', 'user')
            ->where('notifiable_id', $user->id)
            ->findOrFail($id);
        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['counts' => $this->getCounts()]);
        }

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $q = Notification::where('notifiable_type', 'user')
            ->where('notifiable_id', $user->id)
            ->unread();

        if ($request->input('type')) {
            $q->byType($request->input('type'));
        }

        $q->update(['is_read' => true, 'read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['counts' => $this->getCounts()]);
        }

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function destroy(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $notification = Notification::where('notifiable_type', 'user')
            ->where('notifiable_id', $user->id)
            ->findOrFail($id);
        $notification->delete();

        if ($request->expectsJson()) {
            return response()->json(['counts' => $this->getCounts()]);
        }

        return redirect()->route('notifications.index')->with('success', 'Notifikasi berhasil dihapus.');
    }

    /**
     * @return array<string, int>
     */
    private function getCounts(): array
    {
        $user = auth()->user();

        $rows = Notification::where('notifiable_type', 'user')
            ->where('notifiable_id', $user->id)
            ->unread()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        return [
            'total'         => array_sum($rows),
            'rental_new'    => $rows['rental_new']    ?? 0,
            'rental_return' => $rows['rental_return'] ?? 0,
            'rental_late'   => $rows['rental_late']   ?? 0,
            'payment'       => $rows['payment']       ?? 0,
            'reminder'      => $rows['reminder']      ?? 0,
            'system'        => $rows['system']        ?? 0,
        ];
    }

    public function show(int $id): View
    {
        $user = auth()->user();
        $notification = Notification::where('notifiable_type', 'user')
            ->where('notifiable_id', $user->id)
            ->findOrFail($id);

        if (!$notification->is_read) {
            $notification->update(['is_read' => true, 'read_at' => now()]);
        }

        return view('components.show', compact('notification'));
    }
}
