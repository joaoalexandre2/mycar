<?php

use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\ExigeMenu;
use App\Http\Middleware\ExigePapel;
use App\Http\Middleware\ExigePerfil;
use App\Http\Middleware\ExigePermissao;
use App\Http\Middleware\SomenteSuperAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => AuthenticateToken::class,
            'super.admin' => SomenteSuperAdmin::class,
            'perfil' => ExigePerfil::class,
            'papel' => ExigePapel::class,
            'permissao' => ExigePermissao::class,
            'menu' => ExigeMenu::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // O frontend mostra o "message" que a API devolve. Os erros que o próprio
        // framework monta saem em inglês e às vezes com detalhe interno ("No query
        // results for model [App\Models\...]", "The route ... could not be found.").
        // Aqui só o texto do "message" é trocado: status, cabeçalhos (Retry-After,
        // Allow) e as demais chaves ficam como estão. Mensagem escrita pelo próprio
        // app (abort(404, 'MyCar não encontrado.'), respostas JSON prontas) não é
        // tocada. Com APP_DEBUG=false o 500 continua sem trace, arquivo nem SQL.
        $exceptions->respond(function (Response $resposta, Throwable $e, Request $request) {
            $mensagens = [
                401 => 'Não autenticado.',
                403 => 'Você não tem permissão para esta ação.',
                404 => 'Não encontrado.',
                405 => 'Esta ação não é permitida neste endereço.',
                419 => 'Sua sessão expirou. Recarregue a página e tente de novo.',
                429 => 'Muitas tentativas. Aguarde um pouco e tente de novo.',
                500 => 'Erro interno no servidor. Tente de novo em instantes.',
            ];

            $status = $resposta->getStatusCode();

            if (!$resposta instanceof JsonResponse || !isset($mensagens[$status]) || $e instanceof HttpResponseException) {
                return $resposta;
            }

            $dados = $resposta->getData(true);

            if (!is_array($dados) || !array_key_exists('message', $dados)) {
                return $resposta;
            }

            $mensagem = (string) $dados['message'];
            $anterior = $e->getPrevious();

            $veioDoFramework = $mensagem === ''
                || $e instanceof AuthenticationException
                || $e instanceof ThrottleRequestsException
                || $e instanceof MethodNotAllowedHttpException
                || $e instanceof InvalidSignatureException
                || $anterior instanceof ModelNotFoundException
                || $anterior instanceof RecordNotFoundException
                || $anterior instanceof RecordsNotFoundException
                || $anterior instanceof BackedEnumCaseNotFoundException
                || $anterior instanceof TokenMismatchException
                || ($anterior instanceof AuthorizationException && $mensagem === 'This action is unauthorized.')
                || ($e instanceof NotFoundHttpException && $anterior === null && str_starts_with($mensagem, 'The route '))
                // Exceção não HTTP com APP_DEBUG=false: o framework já trocou o texto por "Server Error".
                || ($status === 500 && $mensagem === 'Server Error');

            if ($veioDoFramework) {
                $dados['message'] = $mensagens[$status];
                $resposta->setData($dados);
            }

            return $resposta;
        });
    })->create();
