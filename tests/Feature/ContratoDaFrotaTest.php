<?php

namespace Tests\Feature;

use App\Support\PermissoesFrota;
use Tests\TestCase;

/**
 * Contrato entre o backend e o frontend: as chaves de permissão e de menu são as que a tela
 * conhece (src/utils/modelosDeGrupo.ts no repositório do frontend). Se uma das pontas mudar
 * sem a outra, um dos dois testes quebra.
 */
class ContratoDaFrotaTest extends TestCase
{
    public function test_chaves_de_permissao_sao_as_que_o_frontend_conhece(): void
    {
        $esperadas = ['centros_custo', 'grupos', 'motoristas', 'usos_corrigir', 'usos_importar'];

        $this->assertEqualsCanonicalizing($esperadas, PermissoesFrota::chaves());
    }

    public function test_chaves_de_menu_sao_as_que_o_frontend_conhece(): void
    {
        $this->assertEqualsCanonicalizing(['cadastros', 'veiculos'], PermissoesFrota::chavesDeMenu());
    }
}
