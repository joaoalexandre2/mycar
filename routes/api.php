<?php

use App\Http\Controllers\AbastecimentoController;
use App\Http\Controllers\AdminConteudoController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoPecasController;
use App\Http\Controllers\CentroCustoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CodigoPecaController;
use App\Http\Controllers\ComparadorController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\ContaController;
use App\Http\Controllers\ContaVeiculoController;
use App\Http\Controllers\DespesaController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FichaTecnicaContaController;
use App\Http\Controllers\FichaTecnicaController;
use App\Http\Controllers\FipeController;
use App\Http\Controllers\FotoServirController;
use App\Http\Controllers\FotoVeiculoContaController;
use App\Http\Controllers\ImagemModeloController;
use App\Http\Controllers\ManutencaoController;
use App\Http\Controllers\MeusMycarsController;
use App\Http\Controllers\MotoristaController;
use App\Http\Controllers\OrdemServicoController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SeguroController;
use App\Http\Controllers\ServicoController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SugestaoController;
use App\Http\Controllers\UsoVeiculoController;
use App\Http\Controllers\UsoVeiculoImportController;
use App\Http\Controllers\VeiculoController;
use App\Http\Controllers\VeiculoPecaController;
use Illuminate\Support\Facades\Route;

// Autenticação
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Login com Google/Facebook: o redirect devolve a URL do provedor; o callback
// volta para o frontend com um código de uso único, trocado por token.
Route::get('/auth/{provedor}/redirect', [SocialAuthController::class, 'redirecionar'])
    ->where('provedor', 'google|facebook')->middleware('throttle:20,1');
Route::get('/auth/{provedor}/callback', [SocialAuthController::class, 'callback'])
    ->where('provedor', 'google|facebook')->middleware('throttle:20,1');
Route::post('/auth/social/trocar', [SocialAuthController::class, 'trocar'])->middleware('throttle:20,1');

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

// Foto do álbum de um veículo: link assinado e temporário (o <img> não manda token).
Route::get('/fotos/{foto}/{tipo}', [FotoServirController::class, 'mostrar'])
    ->middleware(['signed:relative', 'throttle:120,1'])
    ->name('fotos.mostrar');

// Foto livre de um modelo do catálogo (Wikimedia): pública, sem dado de usuário.
Route::get('/imagens-modelos/{slug}', [ImagemModeloController::class, 'mostrar'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:240,1');

// Foto do hodômetro de um uso da frota: também por link assinado e temporário.
Route::get('/hodometros/{uso}/{momento}', [FotoServirController::class, 'hodometro'])
    ->where('momento', 'saida|chegada')
    ->middleware(['signed:relative', 'throttle:120,1'])
    ->name('hodometros.mostrar');

// Imagem anexada a uma sugestão: também por link assinado e temporário.
Route::get('/sugestoes/anexos/{anexo}', [FotoServirController::class, 'anexoDeSugestao'])
    ->middleware(['signed:relative', 'throttle:120,1'])
    ->name('sugestoes.anexo');

// Painel do operador da plataforma: só quem foi promovido por `admin:promover`.
Route::middleware(['auth.token', 'super.admin'])->prefix('admin')->group(function () {
    Route::get('/sugestoes', [SugestaoController::class, 'todas']);
    Route::put('/sugestoes/{id}', [SugestaoController::class, 'atualizar']);
    Route::get('/resumo', [AdminController::class, 'resumo']);
    Route::get('/contas', [AdminController::class, 'contas']);
    Route::get('/contas/{id}/conteudo', [AdminConteudoController::class, 'mostrar'])->middleware('throttle:60,1');
    Route::get('/acessos', [AdminConteudoController::class, 'acessos']);
    Route::post('/contas/{id}/reenviar-confirmacao', [AdminController::class, 'reenviarConfirmacao'])
        ->middleware('throttle:10,1');
});

Route::middleware('auth.token')->group(function () {
    // Comum a todos os perfis
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [ConfiguracaoController::class, 'atualizarPerfil']);
    Route::put('/me/aparencia', [ConfiguracaoController::class, 'atualizarAparencia']);
    Route::put('/me/foto', [ConfiguracaoController::class, 'atualizarFoto'])->middleware('throttle:20,1');
    Route::delete('/me/foto', [ConfiguracaoController::class, 'removerFoto']);

    // Sugestões para a equipe do MyCar (todos os perfis)
    Route::get('/sugestoes', [SugestaoController::class, 'index']);
    Route::post('/sugestoes', [SugestaoController::class, 'store'])->middleware('throttle:10,1');
    Route::put('/me/senha', [ConfiguracaoController::class, 'alterarSenha'])->middleware('throttle:10,1');

    // Os vários MyCar do mesmo e-mail (oficina, pessoa, frota): listar, criar e trocar.
    Route::get('/meus-mycars', [MeusMycarsController::class, 'index']);
    Route::post('/meus-mycars', [MeusMycarsController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/meus-mycars/{id}/ativar', [MeusMycarsController::class, 'ativar'])
        ->where('id', 'principal|[0-9]+');

    // Tabela FIPE (consulta pública, sem dados de nenhuma conta)
    Route::get('/fipe/marcas', [FipeController::class, 'marcas']);
    Route::get('/fipe/marcas/{marca}/modelos', [FipeController::class, 'modelos']);
    // Ano antes do modelo: anos da marca e, escolhido o ano, só os modelos dele.
    Route::get('/fipe/marcas/{marca}/anos', [FipeController::class, 'anosDaMarca']);
    Route::get('/fipe/marcas/{marca}/anos/{ano}/modelos', [FipeController::class, 'modelosDoAno'])
        ->where('ano', '[0-9]{4,5}-[0-9]');
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

    // Perfis PESSOA e FROTA, qualquer papel (o motorista da frota só consulta os veículos)
    Route::middleware('perfil:pessoa,frota')->prefix('conta')->group(function () {
        Route::get('/veiculos', [ContaVeiculoController::class, 'index']);
        Route::get('/veiculos/{id}', [ContaVeiculoController::class, 'show']);
    });

    // Perfis PESSOA (Cuidados com seu carro) e FROTA (administrador e gestor): veículos da própria conta
    Route::middleware(['perfil:pessoa,frota', 'papel:administrador,gestor'])->prefix('conta')->group(function () {
        Route::get('/resumo', [ContaController::class, 'resumo']);
        Route::get('/preferencias', [ContaController::class, 'preferencias']);
        Route::put('/preferencias', [ContaController::class, 'atualizarPreferencias']);

        Route::post('/veiculos', [ContaVeiculoController::class, 'store']);
        Route::put('/veiculos/{id}', [ContaVeiculoController::class, 'update']);
        Route::delete('/veiculos/{id}', [ContaVeiculoController::class, 'destroy']);
        Route::post('/veiculos/{id}/fipe', [ContaVeiculoController::class, 'consultarFipe']);

        Route::get('/despesas', [DespesaController::class, 'index']);
        Route::get('/comparador', [ComparadorController::class, 'index'])->middleware('throttle:20,1');
        Route::get('/pecas-catalogo', [CatalogoPecasController::class, 'index']);
        Route::get('/veiculos/{id}/fotos', [FotoVeiculoContaController::class, 'index']);
        Route::post('/veiculos/{id}/fotos', [FotoVeiculoContaController::class, 'store'])->middleware('throttle:60,1');
        Route::delete('/veiculos/{id}/fotos/{fotoId}', [FotoVeiculoContaController::class, 'destroy']);
        Route::put('/veiculos/{id}/fotos/{fotoId}/capa', [FotoVeiculoContaController::class, 'capa']);

        Route::get('/veiculos/{id}/ficha-tecnica', [FichaTecnicaContaController::class, 'show']);
        Route::put('/veiculos/{id}/ficha-tecnica', [FichaTecnicaContaController::class, 'update']);
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
        Route::put('/veiculos/{id}/possui-seguro', [SeguroController::class, 'possui']);
        Route::delete('/veiculos/{id}/seguros/{seguroId}', [SeguroController::class, 'destroy']);

        Route::get('/veiculos/{id}/abastecimentos', [AbastecimentoController::class, 'index']);
        Route::post('/veiculos/{id}/abastecimentos', [AbastecimentoController::class, 'store']);
        Route::delete('/veiculos/{id}/abastecimentos/{abastecimentoId}', [AbastecimentoController::class, 'destroy']);
    });

    // FROTA, qualquer papel: o motorista só enxerga e opera as próprias reservas (filtrado no controller)
    Route::middleware('perfil:frota')->prefix('conta')->group(function () {
        Route::get('/usos', [UsoVeiculoController::class, 'index']);
        Route::post('/usos', [UsoVeiculoController::class, 'store']);
        Route::get('/usos/{id}', [UsoVeiculoController::class, 'show'])->whereNumber('id');
        Route::post('/usos/{id}/cancelar', [UsoVeiculoController::class, 'cancelar']);
        Route::post('/usos/{id}/saida', [UsoVeiculoController::class, 'saida'])->middleware('throttle:60,1');
        Route::post('/usos/{id}/chegada', [UsoVeiculoController::class, 'chegada'])->middleware('throttle:60,1');
        Route::post('/usos/{id}/foto/{momento}', [UsoVeiculoController::class, 'foto'])->middleware('throttle:60,1');
    });

    // FROTA, administrador e gestor: cadastros, edição, correções, importação e auditoria
    Route::middleware(['perfil:frota', 'papel:administrador,gestor'])->prefix('conta')->group(function () {
        Route::get('/centros-custo', [CentroCustoController::class, 'index']);
        Route::post('/centros-custo', [CentroCustoController::class, 'store']);
        Route::put('/centros-custo/{id}', [CentroCustoController::class, 'update']);
        Route::delete('/centros-custo/{id}', [CentroCustoController::class, 'destroy']);

        Route::get('/motoristas', [MotoristaController::class, 'index']);
        Route::post('/motoristas', [MotoristaController::class, 'store']);
        Route::put('/motoristas/{id}', [MotoristaController::class, 'update']);
        Route::delete('/motoristas/{id}', [MotoristaController::class, 'destroy']);
        // Convida o motorista a entrar no sistema (cria o login e envia o link para definir a senha)
        Route::post('/motoristas/{id}/acesso', [MotoristaController::class, 'convidar'])->middleware('throttle:20,1');

        // Planilha de utilização: rotas fixas antes das com {id}
        Route::get('/usos/planilha-modelo', [UsoVeiculoImportController::class, 'modelo']);
        Route::post('/usos/importar', [UsoVeiculoImportController::class, 'importar'])->middleware('throttle:10,1');
        Route::get('/usos/{id}/eventos', [UsoVeiculoController::class, 'eventos']);
        Route::put('/usos/{id}', [UsoVeiculoController::class, 'update']);
        Route::delete('/usos/{id}', [UsoVeiculoController::class, 'destroy']);
        Route::put('/veiculos/{id}/km', [UsoVeiculoController::class, 'definirKm']);
    });
});
