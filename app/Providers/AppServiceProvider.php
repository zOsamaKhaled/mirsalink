<?php

namespace App\Providers;

use App\Assistant\Brain;
use App\Assistant\SimulatedBrain;
use App\Models\User;
use App\WhatsApp\Gateway;
use App\WhatsApp\SimulatorGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The simulator now, Meta's Cloud API from phase 9; see config/whatsapp.php.
        $this->app->bind(Brain::class, fn ($app) => $app->make(
            config('assistant.drivers.'.config('assistant.driver'), SimulatedBrain::class),
        ));

        $this->app->bind(Gateway::class, fn ($app) => $app->make(
            config('whatsapp.gateways.'.config('whatsapp.gateway'), SimulatorGateway::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Who may do what beyond answering conversations. Two roles only, so
     * gates are enough; routes use them as `can:manage-team`.
     */
    protected function configureGates(): void
    {
        Gate::define('manage-team', fn (User $user): bool => $user->isSupervisor() && $user->isActive());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
