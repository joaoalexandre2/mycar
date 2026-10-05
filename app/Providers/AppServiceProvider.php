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

        $this->app->bind(\App\Services\Catalogo\CatalogoTecnicoService::class, function ($app) {
            return new \App\Services\Catalogo\CatalogoTecnicoService(
                array_map(fn (string $classe) => $app->make($classe), config('catalogo.fontes'))
            );
        });

        // Oficina do usuário autenticado na requisição atual. Fica no valor
        // sentinela 0 (nenhuma oficina real tem esse id) até o middleware
        // AuthenticateToken resolver o usuário e preencher isso; os models
        // com PertenceAOficina usam esse valor para se isolar.
        // (Não usar null aqui: Container::instance() com valor null não é
        // detectado por isset() em make(), e a resolução cai para tentar
        // instanciar uma classe chamada "oficina.atual".)
        $this->app->instance('oficina.atual', 0);

        // Mesma ideia para os perfis pessoa e frota: conta do usuário
        // autenticado, 0 enquanto não houver (models com PertenceAConta).
        $this->app->instance('conta.atual', 0);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}