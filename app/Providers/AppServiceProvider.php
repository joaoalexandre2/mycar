<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Interfaces\OrdemServicoRepositoryInterface;
use App\Repositories\OrdemServicoRepository;
use App\Repositories\Interfaces\ManutencaoRepositoryInterface;
use App\Repositories\ManutencaoRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            OrdemServicoRepositoryInterface::class,
            OrdemServicoRepository::class
        );

        $this->app->bind(
            ManutencaoRepositoryInterface::class,
            ManutencaoRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}