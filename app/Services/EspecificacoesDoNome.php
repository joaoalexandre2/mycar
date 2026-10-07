<?php

namespace App\Services;

/**
 * Lê, do NOME da versão (como a tabela FIPE escreve), o que está escrito nele:
 * motor, válvulas, turbo, combustível, câmbio, portas e tração. Só devolve o
 * que realmente aparece no texto; não deduz nada que não esteja lá.
 *
 * Ex.: "Onix Hatch LTZ 1.4 8V FlexPower 5p Mec." -> 1.4, 8 válvulas, flex,
 * 5 portas, manual.
 */
class EspecificacoesDoNome
{
    /**
     * @return array<int, array{chave: string, rotulo: string, valor: string}>
     */
    public function extrair(?string $nome): array
    {
        $nome = (string) $nome;
        $itens = [];

        $adicionar = function (string $chave, string $rotulo, string $valor) use (&$itens) {
            $itens[] = ['chave' => $chave, 'rotulo' => $rotulo, 'valor' => $valor];
        };

        if (preg_match('/(?<![\d.])(\d\.\d)(?!\d)/', $nome, $m)) {
            $adicionar('motor', 'Motor (cilindrada)', $m[1].' litros');
        }

        if (preg_match('/\b(\d{1,2})V\b/i', $nome, $m)) {
            $adicionar('valvulas', 'Válvulas', $m[1]);
        }

        if (preg_match('/\b(TB|TSI|TFSI|Turbo|T-?Jet|Biturbo)\b/i', $nome)) {
            $adicionar('turbo', 'Alimentação', 'Turbo');
        }

        if (preg_match('/\b(Dies\.?|Diesel|TDI|CDI|CRDi)\b/i', $nome)) {
            $adicionar('combustivel', 'Combustível (pelo nome)', 'Diesel');
        } elseif (preg_match('/flex/i', $nome)) {
            $adicionar('combustivel', 'Combustível (pelo nome)', 'Flex (gasolina e etanol)');
        } elseif (preg_match('/\b(Hybrid|Híbrido|Hibrido|HEV|PHEV|MHEV)\b/i', $nome)) {
            $adicionar('combustivel', 'Combustível (pelo nome)', 'Híbrido');
        } elseif (preg_match('/\b(El[eé]trico|Electric|BEV)\b/i', $nome)) {
            $adicionar('combustivel', 'Combustível (pelo nome)', 'Elétrico');
        }

        if (preg_match('/\bCVT\b/i', $nome)) {
            $adicionar('cambio', 'Câmbio', 'Automático (CVT)');
        } elseif (preg_match('/\b(Aut\.?|Automático|Automatico|DSG|Tiptronic|S ?tronic|Automatizado)\b/i', $nome)) {
            $adicionar('cambio', 'Câmbio', 'Automático');
        } elseif (preg_match('/\b(Mec\.?|Manual)\b/i', $nome)) {
            $adicionar('cambio', 'Câmbio', 'Manual');
        }

        if (preg_match('/\b([2-5])p\b/i', $nome, $m)) {
            $adicionar('portas', 'Portas', $m[1]);
        }

        if (preg_match('/\b(4x4|4x2|AWD|4WD|quattro|xDrive|4Motion)\b/i', $nome, $m)) {
            $adicionar('tracao', 'Tração', strtoupper($m[1]) === '4X4' ? '4x4' : $m[1]);
        }

        return $itens;
    }
}
