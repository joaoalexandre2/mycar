<?php

namespace App\Providers;

use App\Repositories\Interfaces\ManutencaoRepositoryInterface;
use App\Repositories\Interfaces\OrdemServicoRepositoryInterface;
use App\Repositories\ManutencaoRepository;
use App\Repositories\OrdemServicoRepository;
use App\Services\Catalogo\CatalogoTecnicoService;
use App\Services\Precos\FontePrecos;
use App\Services\Precos\MercadoLivreFonte;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fonte de preços do comparador (outras lojas entram trocando esta classe).
        $this->app->bind(
            FontePrecos::class,
            MercadoLivreFonte::class
        );

        $this->app->bind(
            OrdemServicoRepositoryInterface::class,
            OrdemServicoRepository::class
        );

        $this->app->bind(
            ManutencaoRepositoryInterface::class,
            ManutencaoRepository::class
        );

        $this->app->bind(CatalogoTecnicoService::class, function ($app) {
            return new CatalogoTecnicoService(
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
