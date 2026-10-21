<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Rental;
use App\Models\Notification;
use App\Models\Customer;
use App\Models\User;
use App\Models\Laundry;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'customer' => Customer::class,
            'user' => User::class,
            'rental' => Rental::class,
            'laundry' => Laundry::class,
        ]);

        View::composer('*', function ($view) {
            $user = auth()->user();

            if (!$user) {
                $view->with('overdueCount', 0);
                $view->with('unreadNotif', 0);
                return;
            }

            $overdueQuery = Rental::where('rental_status', 'overdue');

            if ($user->role === 'sales') {
                $overdueQuery->where('created_by', $user->id);
            } elseif (!$user->isSuperAdmin()) {
                $overdueQuery->whereIn('branch_id', $user->accessible_branch_ids);
            }

            $overdueCount = $overdueQuery->count();

            $unreadNotif = Notification::where('notifiable_type', 'user')
                ->where('notifiable_id', $user->id)
                ->where('is_read', false)
                ->count();

            $view->with('overdueCount', $overdueCount);
            $view->with('unreadNotif', $unreadNotif);
        });
    }
}
