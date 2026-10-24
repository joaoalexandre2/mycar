<?php

namespace App\Enums;

/**
 * Tipo do veículo cadastrado. Define em qual tabela da FIPE ele é consultado e
 * o que o app calcula: as estimativas de IPVA e licenciamento, o seguro de
 * referência e as fichas técnicas do catálogo são só de carros.
 */
enum TipoVeiculo: string
{
    case Carro = 'carro';
    case Moto = 'moto';
    case Caminhao = 'caminhao';

    /** Nome do tipo na API da FIPE (FipeService::TIPOS). */
    public function fipe(): string
    {
        return match ($this) {
            self::Carro => 'carros',
            self::Moto => 'motos',
            self::Caminhao => 'caminhoes',
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Carro => 'Carro',
            self::Moto => 'Moto',
            self::Caminhao => 'Caminhão',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $tipo) => $tipo->value, self::cases());
    }
}
