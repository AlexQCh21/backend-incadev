<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\DTOs\Finanzas\BalanceGeneralRepositoryInterface;
use App\Repositories\Finanzas\BalanceGeneralRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(BalanceGeneralRepositoryInterface::class, BalanceGeneralRepository::class);
    }
}