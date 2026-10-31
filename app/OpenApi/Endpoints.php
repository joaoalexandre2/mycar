<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Documentação OpenAPI dos endpoints (espelha routes/api.php).
 * Ao criar ou alterar uma rota, atualize aqui e rode `php artisan l5-swagger:generate`.
 */
class Endpoints
{
    #[OA\Post(
        path: '/login',
        summary: 'Login (retorna token Bearer)',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['email', 'password'], properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op1(): void {}

    #[OA\Get(
        path: '/auth/{provedor}/redirect',
        summary: 'URL de login social (google|facebook)',
        tags: ['Autenticação'],
        parameters: [
            new OA\Parameter(name: 'provedor', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
        ],
    )]
    public function op2(): void {}

    #[OA\Get(
        path: '/auth/{provedor}/callback',
        summary: 'Callback do login social',
        tags: ['Autenticação'],
        parameters: [
            new OA\Parameter(name: 'provedor', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
        ],
    )]
    public function op3(): void {}

    #[OA\Post(
        path: '/auth/social/trocar',
        summary: 'Troca o código de uso único por token',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['codigo'], properties: [
                    new OA\Property(property: 'codigo', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op4(): void {}

    #[OA\Post(
        path: '/register',
        summary: 'Cadastra conta + usuário',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['name', 'email', 'password', 'password_confirmation'], properties: [
                    new OA\Property(property: 'perfil', type: 'string', enum: ['oficina', 'pessoa', 'frota']),
                    new OA\Property(property: 'nome_oficina', type: 'string'),
                    new OA\Property(property: 'nome_frota', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op5(): void {}

    #[OA\Post(
        path: '/email/reenviar',
        summary: 'Reenvia e-mail de confirmação',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['email'], properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op6(): void {}

    #[OA\Get(
        path: '/email/verificar/{id}/{hash}',
        summary: 'Confirma e-mail (link assinado)',
        tags: ['Autenticação'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'hash', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op7(): void {}

    #[OA\Post(
        path: '/password/esqueci',
        summary: 'Solicita redefinição de senha',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['email'], properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op8(): void {}

    #[OA\Post(
        path: '/password/redefinir',
        summary: 'Redefine a senha',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['token', 'email', 'password', 'password_confirmation'], properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op9(): void {}

    #[OA\Post(
        path: '/logout',
        summary: 'Logout (revoga token atual)',
        tags: ['Autenticação'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op10(): void {}

    #[OA\Get(
        path: '/me',
        summary: 'Usuário autenticado',
        tags: ['Autenticação'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op11(): void {}

    #[OA\Put(
        path: '/me',
        summary: 'Atualiza o perfil',
        tags: ['Configurações'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['name'], properties: [
                    new OA\Property(property: 'name', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op12(): void {}

    #[OA\Put(
        path: '/me/aparencia',
        summary: 'Atualiza tema e cor',
        tags: ['Configurações'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['tema', 'cor'], properties: [
                    new OA\Property(property: 'tema', type: 'string', enum: ['claro', 'escuro']),
                    new OA\Property(property: 'cor', type: 'string', enum: ['blue', 'green', 'purple', 'orange', 'red']),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op13(): void {}

    #[OA\Put(
        path: '/me/foto',
        summary: 'Atualiza a foto (data URI base64)',
        tags: ['Configurações'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['foto'], properties: [
                    new OA\Property(property: 'foto', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op14(): void {}

    #[OA\Delete(
        path: '/me/foto',
        summary: 'Remove a foto',
        tags: ['Configurações'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op15(): void {}

    #[OA\Put(
        path: '/me/senha',
        summary: 'Altera a senha',
        tags: ['Configurações'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['senha_atual', 'password', 'password_confirmation'], properties: [
                    new OA\Property(property: 'senha_atual', type: 'string', format: 'password'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op16(): void {}

    #[OA\Get(
        path: '/sugestoes',
        summary: 'Lista minhas sugestões',
        tags: ['Sugestões'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op17(): void {}

    #[OA\Post(
        path: '/sugestoes',
        summary: 'Envia sugestão (com imagens opcionais)',
        tags: ['Sugestões'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['categoria', 'titulo', 'descricao'], properties: [
                    new OA\Property(property: 'categoria', type: 'string'),
                    new OA\Property(property: 'titulo', type: 'string'),
                    new OA\Property(property: 'descricao', type: 'string'),
                    new OA\Property(property: 'imagens', type: 'array', items: new OA\Items()),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op18(): void {}

    #[OA\Get(
        path: '/meus-mycars',
        summary: 'Lista os MyCars do e-mail',
        tags: ['Meus MyCars'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op19(): void {}

    #[OA\Post(
        path: '/meus-mycars',
        summary: 'Cria outro MyCar',
        tags: ['Meus MyCars'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['perfil'], properties: [
                    new OA\Property(property: 'perfil', type: 'string', enum: ['oficina', 'pessoa', 'frota']),
                    new OA\Property(property: 'nome', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op20(): void {}

    #[OA\Post(
        path: '/meus-mycars/{id}/ativar',
        summary: 'Ativa/troca de MyCar (id numérico ou "principal")',
        tags: ['Meus MyCars'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op21(): void {}

    #[OA\Get(
        path: '/fipe/marcas',
        summary: 'Lista marcas',
        tags: ['FIPE'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'tipo', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op22(): void {}

    #[OA\Get(
        path: '/fipe/marcas/{marca}/modelos',
        summary: 'Modelos da marca',
        tags: ['FIPE'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'marca', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tipo', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op23(): void {}

    #[OA\Get(
        path: '/fipe/marcas/{marca}/anos',
        summary: 'Anos da marca',
        tags: ['FIPE'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'marca', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tipo', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op24(): void {}

    #[OA\Get(
        path: '/fipe/marcas/{marca}/anos/{ano}/modelos',
        summary: 'Modelos de marca e ano',
        tags: ['FIPE'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'marca', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'ano', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tipo', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op25(): void {}

    #[OA\Get(
        path: '/fipe/marcas/{marca}/modelos/{modelo}/anos',
        summary: 'Anos do modelo',
        tags: ['FIPE'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'marca', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'modelo', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tipo', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op26(): void {}

    #[OA\Get(
        path: '/fipe/marcas/{marca}/modelos/{modelo}/anos/{ano}',
        summary: 'Valor FIPE',
        tags: ['FIPE'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'marca', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'modelo', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'ano', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tipo', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op27(): void {}

    #[OA\Get(
        path: '/admin/sugestoes',
        summary: 'Todas as sugestões',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Somente super admin'),
        ],
    )]
    public function op28(): void {}

    #[OA\Put(
        path: '/admin/sugestoes/{id}',
        summary: 'Atualiza status/resposta da sugestão',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['status'], properties: [
                    new OA\Property(property: 'status', type: 'string'),
                    new OA\Property(property: 'resposta', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Somente super admin'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op29(): void {}

    #[OA\Get(
        path: '/admin/resumo',
        summary: 'Resumo da plataforma',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Somente super admin'),
        ],
    )]
    public function op30(): void {}

    #[OA\Get(
        path: '/admin/contas',
        summary: 'Lista contas',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Somente super admin'),
        ],
    )]
    public function op31(): void {}

    #[OA\Get(
        path: '/admin/contas/{id}/conteudo',
        summary: 'Conteúdo de uma conta',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Somente super admin'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op32(): void {}

    #[OA\Get(
        path: '/admin/acessos',
        summary: 'Auditoria de acessos',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Somente super admin'),
        ],
    )]
    public function op33(): void {}

    #[OA\Post(
        path: '/admin/contas/{id}/reenviar-confirmacao',
        summary: 'Reenvia confirmação de e-mail',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Somente super admin'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op34(): void {}

    #[OA\Get(
        path: '/oficina',
        summary: 'Dados da oficina',
        tags: ['Oficina'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op35(): void {}

    #[OA\Put(
        path: '/oficina',
        summary: 'Atualiza a oficina',
        tags: ['Oficina'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['nome'], properties: [
                    new OA\Property(property: 'nome', type: 'string'),
                    new OA\Property(property: 'cnpj', type: 'string'),
                    new OA\Property(property: 'telefone', type: 'string'),
                    new OA\Property(property: 'endereco', type: 'string'),
                    new OA\Property(property: 'resumo_semanal', type: 'boolean'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op36(): void {}

    #[OA\Get(
        path: '/clientes',
        summary: 'Lista clientes',
        tags: ['Clientes'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op37(): void {}

    #[OA\Post(
        path: '/clientes',
        summary: 'Cria cliente',
        tags: ['Clientes'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['nome'], properties: [
                    new OA\Property(property: 'nome', type: 'string'),
                    new OA\Property(property: 'cpf', type: 'string'),
                    new OA\Property(property: 'telefone', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'ativo', type: 'boolean'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op38(): void {}

    #[OA\Get(
        path: '/clientes/{id}',
        summary: 'Detalha cliente',
        tags: ['Clientes'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op39(): void {}

    #[OA\Put(
        path: '/clientes/{id}',
        summary: 'Atualiza cliente',
        tags: ['Clientes'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['nome'], properties: [
                    new OA\Property(property: 'nome', type: 'string'),
                    new OA\Property(property: 'cpf', type: 'string'),
                    new OA\Property(property: 'telefone', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'ativo', type: 'boolean'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op40(): void {}

    #[OA\Delete(
        path: '/clientes/{id}',
        summary: 'Remove cliente',
        tags: ['Clientes'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op41(): void {}

    #[OA\Get(
        path: '/veiculos',
        summary: 'Lista veículos',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op42(): void {}

    #[OA\Post(
        path: '/veiculos',
        summary: 'Cria veículo',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['cliente_id', 'placa', 'marca', 'modelo', 'ano'], properties: [
                    new OA\Property(property: 'cliente_id', type: 'integer'),
                    new OA\Property(property: 'placa', type: 'string'),
                    new OA\Property(property: 'tipo_veiculo', type: 'string'),
                    new OA\Property(property: 'marca', type: 'string'),
                    new OA\Property(property: 'modelo', type: 'string'),
                    new OA\Property(property: 'ano', type: 'integer'),
                    new OA\Property(property: 'ano_fabricacao', type: 'integer'),
                    new OA\Property(property: 'uf', type: 'string'),
                    new OA\Property(property: 'fipe_marca_id', type: 'integer'),
                    new OA\Property(property: 'fipe_modelo_id', type: 'integer'),
                    new OA\Property(property: 'fipe_ano', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op43(): void {}

    #[OA\Get(
        path: '/veiculos/{id}',
        summary: 'Detalha veículo',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op44(): void {}

    #[OA\Put(
        path: '/veiculos/{id}',
        summary: 'Atualiza veículo',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['cliente_id', 'placa', 'marca', 'modelo', 'ano'], properties: [
                    new OA\Property(property: 'cliente_id', type: 'integer'),
                    new OA\Property(property: 'placa', type: 'string'),
                    new OA\Property(property: 'tipo_veiculo', type: 'string'),
                    new OA\Property(property: 'marca', type: 'string'),
                    new OA\Property(property: 'modelo', type: 'string'),
                    new OA\Property(property: 'ano', type: 'integer'),
                    new OA\Property(property: 'ano_fabricacao', type: 'integer'),
                    new OA\Property(property: 'uf', type: 'string'),
                    new OA\Property(property: 'fipe_marca_id', type: 'integer'),
                    new OA\Property(property: 'fipe_modelo_id', type: 'integer'),
                    new OA\Property(property: 'fipe_ano', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op45(): void {}

    #[OA\Delete(
        path: '/veiculos/{id}',
        summary: 'Remove veículo',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op46(): void {}

    #[OA\Post(
        path: '/veiculos/{id}/fipe',
        summary: 'Consulta FIPE do veículo',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op47(): void {}

    #[OA\Get(
        path: '/veiculos/{id}/historico',
        summary: 'Histórico do veículo',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op48(): void {}

    #[OA\Get(
        path: '/veiculos/{id}/ficha-tecnica',
        summary: 'Ficha técnica',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op49(): void {}

    #[OA\Put(
        path: '/veiculos/{id}/ficha-tecnica',
        summary: 'Atualiza ficha técnica',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(properties: [
                    new OA\Property(property: 'oleo_viscosidade', type: 'string'),
                    new OA\Property(property: 'oleo_especificacao', type: 'string'),
                    new OA\Property(property: 'oleo_capacidade_litros', type: 'number'),
                    new OA\Property(property: 'filtro_oleo', type: 'string'),
                    new OA\Property(property: 'filtro_ar', type: 'string'),
                    new OA\Property(property: 'filtro_combustivel', type: 'string'),
                    new OA\Property(property: 'pneu_medida', type: 'string'),
                    new OA\Property(property: 'pneu_pressao_dianteira', type: 'integer'),
                    new OA\Property(property: 'pneu_pressao_traseira', type: 'integer'),
                    new OA\Property(property: 'observacoes', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op50(): void {}

    #[OA\Get(
        path: '/veiculos/{id}/pecas',
        summary: 'Peças do veículo',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op51(): void {}

    #[OA\Post(
        path: '/veiculos/{id}/pecas',
        summary: 'Registra peça',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['tipo', 'especificacao', 'fonte'], properties: [
                    new OA\Property(property: 'tipo', type: 'string'),
                    new OA\Property(property: 'especificacao', type: 'string'),
                    new OA\Property(property: 'marca', type: 'string'),
                    new OA\Property(property: 'fonte', type: 'string'),
                    new OA\Property(property: 'usado_em', type: 'string', format: 'date'),
                    new OA\Property(property: 'observacao', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op52(): void {}

    #[OA\Delete(
        path: '/veiculos/{id}/pecas/{pecaId}',
        summary: 'Remove peça',
        tags: ['Veículos (oficina)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pecaId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op53(): void {}

    #[OA\Get(
        path: '/ordens-servico',
        summary: 'Lista ordens',
        tags: ['Ordens de serviço'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op54(): void {}

    #[OA\Post(
        path: '/ordens-servico',
        summary: 'Cria ordem',
        tags: ['Ordens de serviço'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['veiculo_id', 'descricao'], properties: [
                    new OA\Property(property: 'veiculo_id', type: 'integer'),
                    new OA\Property(property: 'descricao', type: 'string'),
                    new OA\Property(property: 'status', type: 'string'),
                    new OA\Property(property: 'valor', type: 'number'),
                    new OA\Property(property: 'data_abertura', type: 'string', format: 'date'),
                    new OA\Property(property: 'data_fechamento', type: 'string', format: 'date'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op55(): void {}

    #[OA\Get(
        path: '/ordens-servico/{id}',
        summary: 'Detalha ordem',
        tags: ['Ordens de serviço'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op56(): void {}

    #[OA\Put(
        path: '/ordens-servico/{id}',
        summary: 'Atualiza ordem',
        tags: ['Ordens de serviço'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['veiculo_id', 'descricao'], properties: [
                    new OA\Property(property: 'veiculo_id', type: 'integer'),
                    new OA\Property(property: 'descricao', type: 'string'),
                    new OA\Property(property: 'status', type: 'string'),
                    new OA\Property(property: 'valor', type: 'number'),
                    new OA\Property(property: 'data_abertura', type: 'string', format: 'date'),
                    new OA\Property(property: 'data_fechamento', type: 'string', format: 'date'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op57(): void {}

    #[OA\Delete(
        path: '/ordens-servico/{id}',
        summary: 'Remove ordem',
        tags: ['Ordens de serviço'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op58(): void {}

    #[OA\Get(
        path: '/manutencoes',
        summary: 'Lista manutenções',
        tags: ['Manutenções'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op59(): void {}

    #[OA\Post(
        path: '/manutencoes',
        summary: 'Cria manutenção',
        tags: ['Manutenções'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['veiculo_id', 'tipo'], properties: [
                    new OA\Property(property: 'veiculo_id', type: 'integer'),
                    new OA\Property(property: 'tipo', type: 'string'),
                    new OA\Property(property: 'descricao', type: 'string'),
                    new OA\Property(property: 'valor', type: 'number'),
                    new OA\Property(property: 'data_manutencao', type: 'string', format: 'date'),
                    new OA\Property(property: 'quilometragem', type: 'integer'),
                    new OA\Property(property: 'proxima_data', type: 'string', format: 'date'),
                    new OA\Property(property: 'proxima_quilometragem', type: 'integer'),
                    new OA\Property(property: 'pecas', type: 'array', items: new OA\Items()),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op60(): void {}

    #[OA\Get(
        path: '/manutencoes/{id}',
        summary: 'Detalha manutenção',
        tags: ['Manutenções'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op61(): void {}

    #[OA\Put(
        path: '/manutencoes/{id}',
        summary: 'Atualiza manutenção',
        tags: ['Manutenções'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['veiculo_id', 'tipo'], properties: [
                    new OA\Property(property: 'veiculo_id', type: 'integer'),
                    new OA\Property(property: 'tipo', type: 'string'),
                    new OA\Property(property: 'descricao', type: 'string'),
                    new OA\Property(property: 'valor', type: 'number'),
                    new OA\Property(property: 'data_manutencao', type: 'string', format: 'date'),
                    new OA\Property(property: 'quilometragem', type: 'integer'),
                    new OA\Property(property: 'proxima_data', type: 'string', format: 'date'),
                    new OA\Property(property: 'proxima_quilometragem', type: 'integer'),
                    new OA\Property(property: 'pecas', type: 'array', items: new OA\Items()),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op62(): void {}

    #[OA\Delete(
        path: '/manutencoes/{id}',
        summary: 'Remove manutenção',
        tags: ['Manutenções'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op63(): void {}

    #[OA\Get(
        path: '/conta/veiculos',
        summary: 'Lista veículos da conta',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op64(): void {}

    #[OA\Get(
        path: '/conta/veiculos/{id}',
        summary: 'Detalha veículo da conta',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op65(): void {}

    #[OA\Get(
        path: '/conta/resumo',
        summary: 'Resumo da conta',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op66(): void {}

    #[OA\Get(
        path: '/conta/preferencias',
        summary: 'Preferências',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op67(): void {}

    #[OA\Put(
        path: '/conta/preferencias',
        summary: 'Atualiza preferências',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['lembretes_email'], properties: [
                    new OA\Property(property: 'lembretes_email', type: 'boolean'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op68(): void {}

    #[OA\Post(
        path: '/conta/veiculos',
        summary: 'Cria veículo da conta',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['marca', 'modelo', 'ano'], properties: [
                    new OA\Property(property: 'apelido', type: 'string'),
                    new OA\Property(property: 'placa', type: 'string'),
                    new OA\Property(property: 'tipo_veiculo', type: 'string'),
                    new OA\Property(property: 'marca', type: 'string'),
                    new OA\Property(property: 'modelo', type: 'string'),
                    new OA\Property(property: 'ano', type: 'integer'),
                    new OA\Property(property: 'ano_fabricacao', type: 'integer'),
                    new OA\Property(property: 'revisao_prevista_em', type: 'string', format: 'date'),
                    new OA\Property(property: 'uf', type: 'string'),
                    new OA\Property(property: 'fipe_marca_id', type: 'integer'),
                    new OA\Property(property: 'fipe_modelo_id', type: 'integer'),
                    new OA\Property(property: 'fipe_ano', type: 'string'),
                    new OA\Property(property: 'centro_custo_id', type: 'integer'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op69(): void {}

    #[OA\Put(
        path: '/conta/veiculos/{id}',
        summary: 'Atualiza veículo da conta',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['marca', 'modelo', 'ano'], properties: [
                    new OA\Property(property: 'apelido', type: 'string'),
                    new OA\Property(property: 'placa', type: 'string'),
                    new OA\Property(property: 'tipo_veiculo', type: 'string'),
                    new OA\Property(property: 'marca', type: 'string'),
                    new OA\Property(property: 'modelo', type: 'string'),
                    new OA\Property(property: 'ano', type: 'integer'),
                    new OA\Property(property: 'ano_fabricacao', type: 'integer'),
                    new OA\Property(property: 'revisao_prevista_em', type: 'string', format: 'date'),
                    new OA\Property(property: 'uf', type: 'string'),
                    new OA\Property(property: 'fipe_marca_id', type: 'integer'),
                    new OA\Property(property: 'fipe_modelo_id', type: 'integer'),
                    new OA\Property(property: 'fipe_ano', type: 'string'),
                    new OA\Property(property: 'centro_custo_id', type: 'integer'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op70(): void {}

    #[OA\Delete(
        path: '/conta/veiculos/{id}',
        summary: 'Remove veículo da conta',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op71(): void {}

    #[OA\Post(
        path: '/conta/veiculos/{id}/fipe',
        summary: 'Consulta FIPE',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op72(): void {}

    #[OA\Get(
        path: '/conta/despesas',
        summary: 'Despesas consolidadas',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'de', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'ate', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'veiculo_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op73(): void {}

    #[OA\Get(
        path: '/conta/comparador',
        summary: 'Comparador de preços de peças',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op74(): void {}

    #[OA\Get(
        path: '/conta/pecas-catalogo',
        summary: 'Catálogo de peças',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'veiculo_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sistema', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op75(): void {}

    #[OA\Get(
        path: '/conta/veiculos/{id}/fotos',
        summary: 'Fotos do veículo',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op76(): void {}

    #[OA\Post(
        path: '/conta/veiculos/{id}/fotos',
        summary: 'Envia foto',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['foto', 'miniatura'], properties: [
                    new OA\Property(property: 'foto', type: 'string', format: 'binary'),
                    new OA\Property(property: 'miniatura', type: 'string', format: 'binary'),
                    new OA\Property(property: 'legenda', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op77(): void {}

    #[OA\Delete(
        path: '/conta/veiculos/{id}/fotos/{fotoId}',
        summary: 'Remove foto',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'fotoId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op78(): void {}

    #[OA\Put(
        path: '/conta/veiculos/{id}/fotos/{fotoId}/capa',
        summary: 'Define foto de capa',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'fotoId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op79(): void {}

    #[OA\Get(
        path: '/conta/veiculos/{id}/ficha-tecnica',
        summary: 'Ficha técnica',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op80(): void {}

    #[OA\Put(
        path: '/conta/veiculos/{id}/ficha-tecnica',
        summary: 'Atualiza ficha técnica',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(properties: [
                    new OA\Property(property: 'oleo_viscosidade', type: 'string'),
                    new OA\Property(property: 'oleo_especificacao', type: 'string'),
                    new OA\Property(property: 'oleo_capacidade_litros', type: 'number'),
                    new OA\Property(property: 'filtro_oleo', type: 'string'),
                    new OA\Property(property: 'filtro_ar', type: 'string'),
                    new OA\Property(property: 'filtro_combustivel', type: 'string'),
                    new OA\Property(property: 'pneu_medida', type: 'string'),
                    new OA\Property(property: 'pneu_pressao_dianteira', type: 'integer'),
                    new OA\Property(property: 'pneu_pressao_traseira', type: 'integer'),
                    new OA\Property(property: 'observacoes', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op81(): void {}

    #[OA\Post(
        path: '/conta/veiculos/{id}/codigos-pecas',
        summary: 'Salva código de peça',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['peca_id', 'codigo'], properties: [
                    new OA\Property(property: 'peca_id', type: 'string'),
                    new OA\Property(property: 'marca', type: 'string'),
                    new OA\Property(property: 'codigo', type: 'string'),
                    new OA\Property(property: 'observacoes', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op82(): void {}

    #[OA\Delete(
        path: '/conta/veiculos/{id}/codigos-pecas/{codigoId}',
        summary: 'Remove código de peça',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'codigoId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op83(): void {}

    #[OA\Get(
        path: '/conta/servicos',
        summary: 'Lista serviços',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op84(): void {}

    #[OA\Post(
        path: '/conta/servicos',
        summary: 'Registra serviço',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['veiculo_conta_id', 'tipo', 'realizado_em'], properties: [
                    new OA\Property(property: 'veiculo_conta_id', type: 'integer'),
                    new OA\Property(property: 'tipo', type: 'string'),
                    new OA\Property(property: 'titulo', type: 'string'),
                    new OA\Property(property: 'realizado_em', type: 'string', format: 'date'),
                    new OA\Property(property: 'km', type: 'integer'),
                    new OA\Property(property: 'valor', type: 'number'),
                    new OA\Property(property: 'observacoes', type: 'string'),
                    new OA\Property(property: 'intervalo_meses', type: 'integer'),
                    new OA\Property(property: 'intervalo_km', type: 'integer'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op85(): void {}

    #[OA\Delete(
        path: '/conta/servicos/{id}',
        summary: 'Remove serviço',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op86(): void {}

    #[OA\Get(
        path: '/conta/veiculos/{id}/documentos',
        summary: 'Documentos do veículo',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op87(): void {}

    #[OA\Post(
        path: '/conta/veiculos/{id}/documentos',
        summary: 'Cadastra documento',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['tipo'], properties: [
                    new OA\Property(property: 'tipo', type: 'string'),
                    new OA\Property(property: 'titulo', type: 'string'),
                    new OA\Property(property: 'vencimento', type: 'string', format: 'date'),
                    new OA\Property(property: 'observacoes', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op88(): void {}

    #[OA\Delete(
        path: '/conta/veiculos/{id}/documentos/{documentoId}',
        summary: 'Remove documento',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'documentoId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op89(): void {}

    #[OA\Get(
        path: '/conta/veiculos/{id}/seguros',
        summary: 'Seguros do veículo',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op90(): void {}

    #[OA\Post(
        path: '/conta/veiculos/{id}/seguros',
        summary: 'Cadastra seguro',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['tipo', 'seguradora', 'valor_anual'], properties: [
                    new OA\Property(property: 'tipo', type: 'string'),
                    new OA\Property(property: 'seguradora', type: 'string'),
                    new OA\Property(property: 'valor_anual', type: 'number'),
                    new OA\Property(property: 'franquia', type: 'number'),
                    new OA\Property(property: 'vigencia_fim', type: 'string', format: 'date'),
                    new OA\Property(property: 'observacoes', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op91(): void {}

    #[OA\Put(
        path: '/conta/veiculos/{id}/possui-seguro',
        summary: 'Marca se possui seguro',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['possui'], properties: [
                    new OA\Property(property: 'possui', type: 'boolean'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op92(): void {}

    #[OA\Delete(
        path: '/conta/veiculos/{id}/seguros/{seguroId}',
        summary: 'Remove seguro',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'seguroId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op93(): void {}

    #[OA\Get(
        path: '/conta/veiculos/{id}/abastecimentos',
        summary: 'Abastecimentos',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op94(): void {}

    #[OA\Post(
        path: '/conta/veiculos/{id}/abastecimentos',
        summary: 'Registra abastecimento',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['data', 'km', 'litros', 'valor_total'], properties: [
                    new OA\Property(property: 'data', type: 'string', format: 'date'),
                    new OA\Property(property: 'km', type: 'integer'),
                    new OA\Property(property: 'litros', type: 'number'),
                    new OA\Property(property: 'valor_total', type: 'number'),
                    new OA\Property(property: 'tanque_cheio', type: 'boolean'),
                    new OA\Property(property: 'combustivel', type: 'string'),
                    new OA\Property(property: 'posto', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op95(): void {}

    #[OA\Delete(
        path: '/conta/veiculos/{id}/abastecimentos/{abastecimentoId}',
        summary: 'Remove abastecimento',
        tags: ['Conta (pessoa/frota)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'abastecimentoId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op96(): void {}

    #[OA\Get(
        path: '/conta/usos',
        summary: 'Lista usos (reservas)',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'de', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'ate', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'veiculo_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'motorista_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op97(): void {}

    #[OA\Post(
        path: '/conta/usos',
        summary: 'Cria reserva de uso',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['veiculo_conta_id', 'inicio_previsto', 'fim_previsto'], properties: [
                    new OA\Property(property: 'veiculo_conta_id', type: 'integer'),
                    new OA\Property(property: 'motorista_id', type: 'integer'),
                    new OA\Property(property: 'centro_custo_id', type: 'integer'),
                    new OA\Property(property: 'inicio_previsto', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'fim_previsto', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'finalidade', type: 'string'),
                    new OA\Property(property: 'observacoes', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op98(): void {}

    #[OA\Get(
        path: '/conta/usos/{id}',
        summary: 'Detalha uso',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op99(): void {}

    #[OA\Post(
        path: '/conta/usos/{id}/cancelar',
        summary: 'Cancela reserva',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op100(): void {}

    #[OA\Post(
        path: '/conta/usos/{id}/saida',
        summary: 'Registra saída',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op101(): void {}

    #[OA\Post(
        path: '/conta/usos/{id}/chegada',
        summary: 'Registra chegada',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op102(): void {}

    #[OA\Post(
        path: '/conta/usos/{id}/foto/{momento}',
        summary: 'Foto do hodômetro (saida|chegada)',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'momento', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op103(): void {}

    #[OA\Get(
        path: '/conta/usos/planilha-modelo',
        summary: 'Baixa planilha modelo',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op104(): void {}

    #[OA\Post(
        path: '/conta/usos/importar',
        summary: 'Importa planilha CSV',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['arquivo'], properties: [
                    new OA\Property(property: 'arquivo', type: 'string', format: 'binary'),
                    new OA\Property(property: 'simular', type: 'boolean'),
                    new OA\Property(property: 'criar_faltantes', type: 'boolean'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op105(): void {}

    #[OA\Get(
        path: '/conta/usos/{id}/eventos',
        summary: 'Eventos do uso',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op106(): void {}

    #[OA\Put(
        path: '/conta/usos/{id}',
        summary: 'Atualiza uso',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['veiculo_conta_id', 'inicio_previsto', 'fim_previsto'], properties: [
                    new OA\Property(property: 'veiculo_conta_id', type: 'integer'),
                    new OA\Property(property: 'motorista_id', type: 'integer'),
                    new OA\Property(property: 'centro_custo_id', type: 'integer'),
                    new OA\Property(property: 'inicio_previsto', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'fim_previsto', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'finalidade', type: 'string'),
                    new OA\Property(property: 'observacoes', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op107(): void {}

    #[OA\Delete(
        path: '/conta/usos/{id}',
        summary: 'Remove uso',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op108(): void {}

    #[OA\Put(
        path: '/conta/veiculos/{id}/km',
        summary: 'Corrige km do veículo',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['km', 'motivo'], properties: [
                    new OA\Property(property: 'km', type: 'integer'),
                    new OA\Property(property: 'motivo', type: 'string'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op109(): void {}

    #[OA\Get(
        path: '/conta/centros-custo',
        summary: 'Lista centros de custo',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op110(): void {}

    #[OA\Post(
        path: '/conta/centros-custo',
        summary: 'Cria centro de custo',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['nome'], properties: [
                    new OA\Property(property: 'nome', type: 'string'),
                    new OA\Property(property: 'codigo', type: 'string'),
                    new OA\Property(property: 'ativo', type: 'boolean'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op111(): void {}

    #[OA\Put(
        path: '/conta/centros-custo/{id}',
        summary: 'Atualiza centro de custo',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['nome'], properties: [
                    new OA\Property(property: 'nome', type: 'string'),
                    new OA\Property(property: 'codigo', type: 'string'),
                    new OA\Property(property: 'ativo', type: 'boolean'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op112(): void {}

    #[OA\Delete(
        path: '/conta/centros-custo/{id}',
        summary: 'Remove centro de custo',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op113(): void {}

    #[OA\Get(
        path: '/conta/motoristas',
        summary: 'Lista motoristas',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
        ],
    )]
    public function op114(): void {}

    #[OA\Post(
        path: '/conta/motoristas',
        summary: 'Cria motorista',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['nome'], properties: [
                    new OA\Property(property: 'nome', type: 'string'),
                    new OA\Property(property: 'telefone', type: 'string'),
                    new OA\Property(property: 'cnh_numero', type: 'string'),
                    new OA\Property(property: 'cnh_validade', type: 'string', format: 'date'),
                    new OA\Property(property: 'ativo', type: 'boolean'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op115(): void {}

    #[OA\Put(
        path: '/conta/motoristas/{id}',
        summary: 'Atualiza motorista',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['nome'], properties: [
                    new OA\Property(property: 'nome', type: 'string'),
                    new OA\Property(property: 'telefone', type: 'string'),
                    new OA\Property(property: 'cnh_numero', type: 'string'),
                    new OA\Property(property: 'cnh_validade', type: 'string', format: 'date'),
                    new OA\Property(property: 'ativo', type: 'boolean'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op116(): void {}

    #[OA\Delete(
        path: '/conta/motoristas/{id}',
        summary: 'Remove motorista',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op117(): void {}

    #[OA\Post(
        path: '/conta/motoristas/{id}/acesso',
        summary: 'Convida motorista (cria login)',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op118(): void {}

    #[OA\Get(
        path: '/fotos/{foto}/{tipo}',
        summary: 'Foto de veículo (link assinado)',
        tags: ['Mídia'],
        parameters: [
            new OA\Parameter(name: 'foto', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tipo', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
        ],
    )]
    public function op119(): void {}

    #[OA\Get(
        path: '/imagens-modelos/{slug}',
        summary: 'Foto de modelo do catálogo',
        tags: ['Mídia'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
        ],
    )]
    public function op120(): void {}

    #[OA\Get(
        path: '/hodometros/{uso}/{momento}',
        summary: 'Foto do hodômetro (link assinado)',
        tags: ['Mídia'],
        parameters: [
            new OA\Parameter(name: 'uso', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'momento', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
        ],
    )]
    public function op121(): void {}

    #[OA\Get(
        path: '/sugestoes/anexos/{anexo}',
        summary: 'Anexo de sugestão (link assinado)',
        tags: ['Mídia'],
        parameters: [
            new OA\Parameter(name: 'anexo', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
        ],
    )]
    public function op122(): void {}

    #[OA\Get(
        path: '/conta/centros-custo/historico',
        summary: 'Relatório das trocas de centro de custo',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'de', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'ate', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'centro_custo_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso (até 500 trocas, da mais recente)'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Sem permissão centros_custo'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op123(): void {}

    #[OA\Get(
        path: '/conta/grupos-usuarios',
        summary: 'Lista grupos de usuários, permissões disponíveis e usuários que podem entrar em grupos',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso: { grupos, permissoes, usuarios }'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Sem permissão grupos'),
        ],
    )]
    public function op124(): void {}

    #[OA\Post(
        path: '/conta/grupos-usuarios',
        summary: 'Cria grupo de usuários',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['nome', 'permissoes'], properties: [
                    new OA\Property(property: 'nome', type: 'string', maxLength: 80),
                    new OA\Property(property: 'descricao', type: 'string', maxLength: 255, nullable: true),
                    new OA\Property(property: 'permissoes', type: 'array', items: new OA\Items(type: 'string'), description: 'Chaves do catálogo de permissões (ver GET /conta/grupos-usuarios)'),
                    new OA\Property(property: 'membros', type: 'array', items: new OA\Items(type: 'integer'), description: 'IDs de usuários da frota'),
                ]))),
        responses: [
            new OA\Response(response: 201, description: 'Criado'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Sem permissão grupos'),
            new OA\Response(response: 422, description: 'Erro de validação (inclui membros de outra frota)', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op125(): void {}

    #[OA\Put(
        path: '/conta/grupos-usuarios/{id}',
        summary: 'Atualiza grupo de usuários',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['nome', 'permissoes'], properties: [
                    new OA\Property(property: 'nome', type: 'string', maxLength: 80),
                    new OA\Property(property: 'descricao', type: 'string', maxLength: 255, nullable: true),
                    new OA\Property(property: 'permissoes', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'membros', type: 'array', items: new OA\Items(type: 'integer'), description: 'Se enviado, substitui a lista de membros'),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Sem permissão grupos'),
            new OA\Response(response: 404, description: 'Grupo não encontrado'),
            new OA\Response(response: 422, description: 'Erro de validação', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op126(): void {}

    #[OA\Delete(
        path: '/conta/grupos-usuarios/{id}',
        summary: 'Remove grupo de usuários',
        tags: ['Frota - Cadastros'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Grupo removido'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Sem permissão grupos'),
            new OA\Response(response: 404, description: 'Grupo não encontrado'),
        ],
    )]
    public function op127(): void {}

    #[OA\Put(
        path: '/conta/usos/{id}/centro-custo',
        summary: 'Troca o centro de custo de um uso (inclusive concluído); a troca fica no histórico',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(required: ['centro_custo_id', 'motivo'], properties: [
                    new OA\Property(property: 'centro_custo_id', type: 'integer', description: 'Centro de custo ativo da frota'),
                    new OA\Property(property: 'motivo', type: 'string', minLength: 5, maxLength: 255),
                ]))),
        responses: [
            new OA\Response(response: 200, description: 'Sucesso (uso atualizado)'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Sem permissão usos_corrigir'),
            new OA\Response(response: 404, description: 'Não encontrado'),
            new OA\Response(response: 422, description: 'Erro de validação, centro inválido ou já é o centro atual', content: new OA\JsonContent(ref: '#/components/schemas/ErroValidacao')),
        ],
    )]
    public function op128(): void {}

    #[OA\Get(
        path: '/conta/usos/{id}/centros-historico',
        summary: 'Histórico de centros de custo de um uso (do mais antigo ao mais recente)',
        tags: ['Frota - Usos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucesso'),
            new OA\Response(response: 401, description: 'Token ausente ou inválido'),
            new OA\Response(response: 403, description: 'Sem permissão usos_corrigir'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ],
    )]
    public function op129(): void {}
}
