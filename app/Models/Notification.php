<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Notification extends Model
{
    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'branch_id',
        'type',
        'title',
        'message',
        'icon',
        'color',
        'action_url',
        'meta',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'meta'    => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function notifiable(): MorphMany
    {
        return $this->morphTo();
    }

    public function scopeUnread($q)        { return $q->where('is_read', false); }
    public function scopeForUser($q, $uid) { return $q->where('notifiable_type', 'user')->where('notifiable_id', $uid); }
    public function scopeByType($q, $type) { return $q->where('type', $type); }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true, 'read_at' => now()]);
    }

    public function getIconNameAttribute(): string
    {
        return match ($this->type) {
            'rental_new'    => '🧥',
            'rental_return' => '↩️',
            'rental_late'   => '⚠️',
            'payment'       => '💰',
            'reminder'      => '🔔',
            default         => '🔔',
        };
    }

    public function getIconClassAttribute(): string
    {
        return match ($this->type) {
            'rental_new'    => 'ni-blue',
            'rental_return' => 'ni-green',
            'rental_late'   => 'ni-red',
            'payment'       => 'ni-emerald',
            'reminder'      => 'ni-amber',
            default         => 'ni-slate',
        };
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}
