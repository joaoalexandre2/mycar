<?php

namespace App\Support;

/**
 * Permissões da frota que um grupo de usuários pode conceder. O papel dá o
 * ponto de partida; o grupo só acrescenta.
 */
class PermissoesFrota
{
    public const CENTROS_CUSTO = 'centros_custo';

    public const MOTORISTAS = 'motoristas';

    public const USOS_CORRIGIR = 'usos_corrigir';

    public const USOS_IMPORTAR = 'usos_importar';

    public const GRUPOS = 'grupos';

    /** @return array<string, string> chave => descrição */
    public static function catalogo(): array
    {
        return [
            self::CENTROS_CUSTO => 'Gerenciar centros de custo',
            self::MOTORISTAS => 'Gerenciar motoristas e convites',
            self::USOS_CORRIGIR => 'Corrigir ou apagar usos, ver auditoria e ajustar o km dos veículos',
            self::USOS_IMPORTAR => 'Importar planilha de utilização',
            self::GRUPOS => 'Gerenciar grupos de usuários',
        ];
    }

    /** @return list<string> */
    public static function chaves(): array
    {
        return array_keys(self::catalogo());
    }

    /**
     * Permissões que cada papel já tem sem grupo nenhum. O administrador tem
     * tudo, sempre (assim ninguém tranca a própria conta).
     *
     * @return list<string>
     */
    public static function doPapel(?string $papel): array
    {
        return match ($papel) {
            'administrador' => self::chaves(),
            'gestor' => array_values(array_diff(self::chaves(), [self::GRUPOS])),
            default => [],
        };
    }
}
