<?php

declare(strict_types=1);

namespace App\Models\Broadcast;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BroadcastCampaign extends Model
{
    use HasFactory;

    protected $table = 'broadcast_campaigns';

    protected $fillable = [
        'name',
        'type',
        'trigger_event',
        'template_id',
        'custom_message',
        'message_template',
        'target_type',
        'recipient_type',
        'target_filters',
        'provider',
        'channels',
        'status',
        'total_target',
        'total_success',
        'total_failed',
        'scheduled_at',
        'started_at',
        'completed_at',
        'created_by',
        'branch_id',
        'delay_seconds',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'target_filters' => 'array',
        'channels' => 'array',
        'custom_message' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'delay_seconds' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(BroadcastTemplate::class, 'template_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<BroadcastLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(BroadcastLog::class, 'campaign_id');
    }

    public function queueMessages(): void
    {
        $recipients = $this->resolveRecipients();
        $channels = $this->channels ?? ['whatsapp'];
        $inAppRecipients = in_array('in_app', $channels, true)
            ? $recipients->filter(fn ($r) => in_array($r['type'], ['user', 'customer'], true))->values()
            : collect();
        $whatsappRecipients = in_array('whatsapp', $channels, true)
            ? $recipients->where('type', 'customer')->values()
            : collect();

        if ($this->scheduled_at?->isFuture()) {
            $this->update([
                'status' => 'scheduled',
                'total_target' => $inAppRecipients->count() + $whatsappRecipients->count(),
                'total_success' => 0,
                'total_failed' => 0,
            ]);

            foreach ($inAppRecipients as $recipient) {
                $this->logs()->updateOrCreate(
                    [
                        'recipient_type' => $recipient['type'],
                        'recipient_id' => $recipient['id'],
                        'provider' => 'in_app',
                    ],
                    [
                        'phone' => $recipient['phone'] ?? '',
                        'rendered_message' => $this->resolveMessageForRecipient($recipient),
                        'status' => 'pending',
                        'sent_at' => null,
                    ]
                );
            }

            foreach ($whatsappRecipients as $recipient) {
                $this->logs()->updateOrCreate(
                    [
                        'recipient_type' => $recipient['type'],
                        'recipient_id' => $recipient['id'],
                        'provider' => null,
                    ],
                    [
                        'phone' => $recipient['phone'],
                        'rendered_message' => $this->resolveMessageForRecipient($recipient),
                        'status' => 'pending',
                        'sent_at' => null,
                    ]
                );
            }

            return;
        }

        $this->update([
            'status' => 'processing',
            'started_at' => now(),
            'total_target' => $recipients->count(),
        ]);

        $message = $this->resolveMessage();

        if (in_array('in_app', $channels, true)) {
            if ($inAppRecipients->isNotEmpty()) {
                app(\App\Services\NotificationService::class)->broadcast(
                    $inAppRecipients->map(fn ($r) => [
                        'type' => $r['type'] === 'user' ? \App\Models\User::class : \App\Models\Customer::class,
                        'id' => $r['id'],
                        'message' => $this->resolveMessageForRecipient($r),
                    ])->toArray(),
                    'system',
                    'Broadcast: ' . $this->name,
                    $message,
                    [
                        'broadcast' => true,
                        'sender_id' => $this->created_by,
                        'sender_name' => $this->created_by ? (\App\Models\User::find($this->created_by)?->name ?? null) : null,
                        'target' => $this->target_type,
                        'role' => null,
                        'branch_id' => $this->branch_id,
                        'recipient_count' => $inAppRecipients->count(),
                    ],
                    $this->branch_id,
                    null,
                    'send',
                    'blue'
                );
                foreach ($inAppRecipients as $recipient) {
                    $this->logs()->updateOrCreate(
                        [
                            'recipient_type' => $recipient['type'],
                            'recipient_id' => $recipient['id'],
                            'provider' => 'in_app',
                        ],
                        [
                            'phone' => $recipient['phone'] ?? '',
                            'rendered_message' => $this->resolveMessageForRecipient($recipient),
                            'status' => 'sent',
                            'sent_at' => now(),
                        ]
                    );
                }
            }
            if (!in_array('whatsapp', $channels, true)) {
                $this->update(['status' => 'completed', 'total_success' => $inAppRecipients->count(), 'completed_at' => now()]);
            }
        }

        if (in_array('whatsapp', $channels, true)) {
            foreach ($whatsappRecipients as $recipient) {
                $renderedMessage = $this->resolveMessageForRecipient($recipient);

                $this->logs()->updateOrCreate(
                    [
                        'recipient_type' => $recipient['type'],
                        'recipient_id' => $recipient['id'],
                        'provider' => null,
                    ],
                    [
                        'phone' => $recipient['phone'],
                        'rendered_message' => $renderedMessage,
                        'status' => 'pending',
                    ]
                );
            }
        }
    }

    private function resolveRecipients()
    {
        $filters = $this->target_filters ?? [];
        $isInApp = in_array('in_app', $this->channels ?? [], true);
        $branchId = $isInApp
            ? (in_array($this->target_type, ['branch', 'sales'], true) ? ($filters['branch_id'] ?? $this->branch_id) : null)
            : ($filters['branch_id'] ?? $this->branch_id);

        $customers = collect();
        $users = collect();

        if (in_array($this->recipient_type, ['customer', 'both'])) {
            $query = Customer::query()->where('opt_out', false);

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            if ($this->target_type === 'customer') {
                $selectedIds = $filters['recipient_ids'] ?? [];
                if ($selectedIds) {
                    $query->whereIn('id', $selectedIds);
                }
            }

            $customers = $query->get(['id', 'name', 'phone', 'branch_id'])->map(fn ($c) => [
                'id' => $c->id,
                'type' => 'customer',
                'phone' => $c->phone,
                'name' => $c->name,
            ]);
        }

        if (in_array($this->recipient_type, ['user', 'both'])) {
            $query = User::query()->active()->where('opt_out', false);
            if (!empty($filters['recipient_ids'])) {
                $query->whereIn('id', $filters['recipient_ids']);
            }

            if ($isInApp) {
                $query->whereIn('role', [User::ROLE_ADMIN_TOKO, User::ROLE_SALES]);
                if ($this->created_by) {
                    $query->where('id', '!=', $this->created_by);
                }
                if ($this->creator) {
                    $query->whereIn('branch_id', $this->creator->accessible_branch_ids);
                }
                if ($this->target_type === 'sales') {
                    $query->where('role', User::ROLE_SALES);
                    if (!empty($filters['recipient_ids'])) {
                        $query->whereIn('id', $filters['recipient_ids']);
                    }
                }
            } elseif ($this->creator && !$this->creator->isSuperAdmin()) {
                $query->whereIn('branch_id', $this->creator->accessible_branch_ids)
                    ->whereIn('role', [User::ROLE_ADMIN_TOKO, User::ROLE_SALES]);
            }

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            if (!$isInApp && $this->target_type === 'sales') {
                $query->whereIn('role', [User::ROLE_ADMIN_TOKO, User::ROLE_SALES]);
            }

            if ($this->target_type === 'customer' && $this->recipient_type === 'both') {
                $selectedIds = $filters['recipient_ids'] ?? [];
                if ($selectedIds) {
                    $query->whereIn('id', $selectedIds);
                }
            }

            $users = $query->get(['id', 'name', 'phone', 'role', 'branch_id'])->map(fn ($u) => [
                'id' => $u->id,
                'type' => 'user',
                'phone' => $u->phone,
                'name' => $u->name,
            ]);
        }

        $allRecipients = $customers->merge($users)->values();

        $dailyLimit = (int) env('BROADCAST_DAILY_LIMIT', 50);
        if (!in_array('whatsapp', $this->channels ?? [], true)) {
            return $allRecipients;
        }
        $todaySent = BroadcastLog::where(fn ($q) => $q->whereNull('provider')->orWhere('provider', '!=', 'in_app'))
            ->whereIn('status', ['submitted', 'sent', 'delivered', 'read'])
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $remaining = max(0, $dailyLimit - $todaySent);

        return $allRecipients->take($remaining);
    }

    public function resolveMessage(): string
    {
        if ($this->message_template) {
            return $this->message_template;
        }

        if ($this->template_id && $this->template) {
            return $this->template->content ?? '';
        }

        $customMessage = $this->custom_message;

        if (is_array($customMessage)) {
            $nonEmpty = array_values(array_filter($customMessage, fn ($m) => is_string($m) && $m !== ''));
            if (count($nonEmpty) > 0) {
                return $nonEmpty[0];
            }
        }

        if (is_string($customMessage)) {
            return $customMessage;
        }

        return '';
    }

    public function resolveMessageForRecipient(array $recipient): string
    {
        $message = $this->resolveMessage();

        $replacements = [
            '{{name}}' => $recipient['name'] ?? '',
            '{{phone}}' => $recipient['phone'] ?? '',
            '{{sender_name}}' => $this->created_by ? (\App\Models\User::find($this->created_by)?->name ?? '') : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    public function renderTemplate(string $template, array $recipient): string
    {
        return $this->resolveMessageForRecipient($recipient);
    }

    public function checkCompletion(): void
    {
        $total = $this->logs()->count();
        $finished = $this->logs()->whereIn('status', ['sent'])->count();

        if ($finished > 0 && $finished === $total) {
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }
}
