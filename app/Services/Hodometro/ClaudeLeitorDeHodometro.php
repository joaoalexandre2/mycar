<?php

namespace App\Services\Hodometro;

use Anthropic\Client;
use Illuminate\Http\UploadedFile;

/**
 * Lê o km da foto do painel com a Claude (visão). A resposta vem em JSON pelo
 * formato estruturado, então não depende de interpretar texto livre.
 */
class ClaudeLeitorDeHodometro implements LeitorDeHodometro
{
    /** Maior km plausível num painel; acima disso a leitura é tratada como errada. */
    private const KM_MAXIMO = 9_999_999;

    private const TIPOS_ACEITOS = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const INSTRUCAO = <<<'TEXTO'
        A imagem é a foto do painel de um carro. Leia o hodômetro total (a quilometragem
        acumulada do veículo), não o hodômetro parcial (trip), a velocidade, o relógio
        nem a autonomia. Devolva o número inteiro, sem pontos nem casas decimais.
        Se o hodômetro não estiver visível ou algum dígito estiver ilegível, devolva
        km = null em vez de chutar.
        TEXTO;

    public function disponivel(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public function ler(UploadedFile $foto): ?int
    {
        $tipo = $foto->getMimeType();

        if (!in_array($tipo, self::TIPOS_ACEITOS, true)) {
            return null;
        }

        $cliente = new Client(apiKey: config('services.anthropic.key'));

        $mensagem = $cliente->messages->create(
            model: config('services.anthropic.modelo_hodometro'),
            maxTokens: 1024,
            outputConfig: [
                // Ler um número na foto é tarefa simples: esforço baixo responde mais rápido.
                'effort' => 'low',
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'km' => ['type' => ['integer', 'null']],
                        ],
                        'required' => ['km'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            messages: [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'mediaType' => $tipo,
                            'data' => base64_encode((string) file_get_contents($foto->getRealPath())),
                        ],
                    ],
                    ['type' => 'text', 'text' => self::INSTRUCAO],
                ],
            ]],
        );

        if ($mensagem->stopReason !== 'end_turn') {
            return null;
        }

        foreach ($mensagem->content as $bloco) {
            if ($bloco->type === 'text') {
                return $this->kmValido(json_decode($bloco->text, true)['km'] ?? null);
            }
        }

        return null;
    }

    private function kmValido(mixed $km): ?int
    {
        if (!is_int($km) || $km < 0 || $km > self::KM_MAXIMO) {
            return null;
        }

        return $km;
    }
}
