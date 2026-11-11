<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamCatalogVersion;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\Patient;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Policies\OrderExamPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->is_active) {
                return false;
            }

            return $user->checkPermissionTo($ability) ? true : null;
        });
        Patient::observe(AuditObserver::class);
        Exam::observe(AuditObserver::class);
        ExamSection::observe(AuditObserver::class);
        ExamParameter::observe(AuditObserver::class);
        ExamCatalogVersion::observe(AuditObserver::class);
        Order::observe(AuditObserver::class);
        OrderExam::observe(AuditObserver::class);
        User::observe(AuditObserver::class);
        Role::observe(AuditObserver::class);
        Gate::policy(OrderExam::class, OrderExamPolicy::class);

        foreach ([RoleAttachedEvent::class, RoleDetachedEvent::class, PermissionAttachedEvent::class, PermissionDetachedEvent::class] as $eventClass) {
            Event::listen($eventClass, function (object $event): void {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => class_basename($event),
                    'entity_type' => $event->model->getMorphClass(),
                    'entity_id' => $event->model->getKey(),
                    'metadata' => null,
                ]);
            });
        }
    }
}
