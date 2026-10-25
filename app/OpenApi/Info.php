<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'MyCar API',
    description: 'API do MyCar (oficina, pessoa e frota). Autenticação por token Bearer obtido em POST /login.',
)]
#[OA\Server(url: '/api', description: 'API')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', description: 'Token retornado por POST /login')]
#[OA\Schema(
    schema: 'ErroValidacao',
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'errors', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string'))),
    ],
)]
class Info {}
