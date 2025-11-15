<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\DTOs\Finanzas\BalanceGeneralRepositoryInterface;
use App\Repositories\Finanzas\BalanceGeneralRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
         $this->app->bind(BalanceGeneralRepositoryInterface::class, BalanceGeneralRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!class_exists(\App\Domains\AuthenticationSessions\Models\User::class)) {
            class_alias(
                \App\Models\User::class,
                \App\Domains\AuthenticationSessions\Models\User::class
            );
        }
    }
}
