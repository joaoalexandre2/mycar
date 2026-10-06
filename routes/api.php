<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\VeiculoController;
use App\Http\Controllers\OrdemServicoController;
use App\Http\Controllers\ManutencaoController;
use App\Http\Controllers\FipeController;
use App\Http\Controllers\FichaTecnicaController;
use App\Http\Controllers\VeiculoPecaController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AbastecimentoController;
use App\Http\Controllers\SeguroController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\ServicoController;
use App\Http\Controllers\DespesaController;
use App\Http\Controllers\CatalogoPecasController;
use App\Http\Controllers\CodigoPecaController;
use App\Http\Controllers\ContaController;
use App\Http\Controllers\ContaVeiculoController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;

// Autenticação
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Cadastro de uma nova conta + usuário dela: oficina (padrão), pessoa ou frota.
Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
Route::post('/email/reenviar', [RegisterController::class, 'reenviar'])->middleware('throttle:5,1');

// Link clicado a partir do e-mail de confirmação (assinado, sem precisar de token).
Route::get('/email/verificar/{id}/{hash}', [EmailVerificationController::class, 'confirmar'])
    ->middleware('signed')
    ->name('verificacao.confirmar');

// Esqueci minha senha
Route::post('/password/esqueci', [PasswordResetController::class, 'enviar'])->middleware('throttle:5,1');
Route::post('/password/redefinir', [PasswordResetController::class, 'redefinir'])->middleware('throttle:5,1');

// Painel do operador da plataforma: só quem foi promovido por `admin:promover`.
Route::middleware(['auth.token', 'super.admin'])->prefix('admin')->group(function () {
    Route::get('/resumo', [AdminController::class, 'resumo']);
    Route::get('/contas', [AdminController::class, 'contas']);
    Route::post('/contas/{id}/reenviar-confirmacao', [AdminController::class, 'reenviarConfirmacao'])
        ->middleware('throttle:10,1');
});

Route::middleware('auth.token')->group(function () {
    // Comum a todos os perfis
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [ConfiguracaoController::class, 'atualizarPerfil']);
    Route::put('/me/aparencia', [ConfiguracaoController::class, 'atualizarAparencia']);
    Route::put('/me/senha', [ConfiguracaoController::class, 'alterarSenha'])->middleware('throttle:10,1');

    // Tabela FIPE (consulta pública, sem dados de nenhuma conta)
    Route::get('/fipe/marcas', [FipeController::class, 'marcas']);
    Route::get('/fipe/marcas/{marca}/modelos', [FipeController::class, 'modelos']);
    Route::get('/fipe/marcas/{marca}/modelos/{modelo}/anos', [FipeController::class, 'anos']);
    Route::get('/fipe/marcas/{marca}/modelos/{modelo}/anos/{ano}', [FipeController::class, 'valor']);

    // Perfil OFICINA: gestão da oficina e dos clientes dela
    Route::middleware('perfil:oficina')->group(function () {
        Route::get('/oficina', [ConfiguracaoController::class, 'mostrarOficina']);
        Route::put('/oficina', [ConfiguracaoController::class, 'atualizarOficina']);

        // Clientes
        Route::post('/clientes', [ClienteController::class, 'store']);
        Route::get('/clientes', [ClienteController::class, 'index']);
        Route::get('/clientes/{id}', [ClienteController::class, 'show']);
        Route::put('/clientes/{id}', [ClienteController::class, 'update']);
        Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);

        // Veiculos
        Route::post('/veiculos', [VeiculoController::class, 'store']);
        Route::get('/veiculos', [VeiculoController::class, 'index']);
        Route::get('/veiculos/{id}', [VeiculoController::class, 'show']);
        Route::put('/veiculos/{id}', [VeiculoController::class, 'update']);
        Route::delete('/veiculos/{id}', [VeiculoController::class, 'destroy']);
        Route::post('/veiculos/{id}/fipe', [VeiculoController::class, 'consultarFipe']);
        Route::get('/veiculos/{id}/historico', [VeiculoController::class, 'historico']);
        Route::get('/veiculos/{id}/ficha-tecnica', [FichaTecnicaController::class, 'show']);
        Route::put('/veiculos/{id}/ficha-tecnica', [FichaTecnicaController::class, 'update']);
        Route::get('/veiculos/{id}/pecas', [VeiculoPecaController::class, 'index']);
        Route::post('/veiculos/{id}/pecas', [VeiculoPecaController::class, 'store']);
        Route::delete('/veiculos/{id}/pecas/{pecaId}', [VeiculoPecaController::class, 'destroy']);

        // Ordem de Serviços
        Route::post('/ordens-servico', [OrdemServicoController::class, 'store']);
        Route::get('/ordens-servico/{id}', [OrdemServicoController::class, 'show']);
        Route::get('/ordens-servico', [OrdemServicoController::class, 'index']);
        Route::put('/ordens-servico/{id}', [OrdemServicoController::class, 'update']);
        Route::delete('/ordens-servico/{id}', [OrdemServicoController::class, 'destroy']);

        // Manutenções
        Route::get('/manutencoes', [ManutencaoController::class, 'index']);
        Route::post('/manutencoes', [ManutencaoController::class, 'store']);
        Route::get('/manutencoes/{id}', [ManutencaoController::class, 'show']);
        Route::put('/manutencoes/{id}', [ManutencaoController::class, 'update']);
        Route::delete('/manutencoes/{id}', [ManutencaoController::class, 'destroy']);
    });

    // Perfis PESSOA (Cuidados com seu carro) e FROTA: veículos da própria conta
    Route::middleware('perfil:pessoa,frota')->prefix('conta')->group(function () {
        Route::get('/resumo', [ContaController::class, 'resumo']);
        Route::get('/preferencias', [ContaController::class, 'preferencias']);
        Route::put('/preferencias', [ContaController::class, 'atualizarPreferencias']);

        Route::get('/veiculos', [ContaVeiculoController::class, 'index']);
        Route::post('/veiculos', [ContaVeiculoController::class, 'store']);
        Route::get('/veiculos/{id}', [ContaVeiculoController::class, 'show']);
        Route::put('/veiculos/{id}', [ContaVeiculoController::class, 'update']);
        Route::delete('/veiculos/{id}', [ContaVeiculoController::class, 'destroy']);
        Route::post('/veiculos/{id}/fipe', [ContaVeiculoController::class, 'consultarFipe']);

        Route::get('/despesas', [DespesaController::class, 'index']);
        Route::get('/pecas-catalogo', [CatalogoPecasController::class, 'index']);
        Route::post('/veiculos/{id}/codigos-pecas', [CodigoPecaController::class, 'store']);
        Route::delete('/veiculos/{id}/codigos-pecas/{codigoId}', [CodigoPecaController::class, 'destroy']);

        Route::get('/servicos', [ServicoController::class, 'index']);
        Route::post('/servicos', [ServicoController::class, 'store']);
        Route::delete('/servicos/{id}', [ServicoController::class, 'destroy']);

        Route::get('/veiculos/{id}/documentos', [DocumentoController::class, 'index']);
        Route::post('/veiculos/{id}/documentos', [DocumentoController::class, 'store']);
        Route::delete('/veiculos/{id}/documentos/{documentoId}', [DocumentoController::class, 'destroy']);

        Route::get('/veiculos/{id}/seguros', [SeguroController::class, 'index']);
        Route::post('/veiculos/{id}/seguros', [SeguroController::class, 'store']);
        Route::delete('/veiculos/{id}/seguros/{seguroId}', [SeguroController::class, 'destroy']);

        Route::get('/veiculos/{id}/abastecimentos', [AbastecimentoController::class, 'index']);
        Route::post('/veiculos/{id}/abastecimentos', [AbastecimentoController::class, 'store']);
        Route::delete('/veiculos/{id}/abastecimentos/{abastecimentoId}', [AbastecimentoController::class, 'destroy']);
    });
});
