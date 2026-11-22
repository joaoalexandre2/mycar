<?php

namespace Tests\Feature;

use App\Mail\AlertaIpvaEmail;
use App\Mail\AlertaLicenciamentoEmail;
use App\Mail\AlertaManutencaoEmail;
use App\Mail\ConfirmeSeuEmail;
use App\Mail\ConviteMotoristaEmail;
use App\Mail\ConvitePessoaEmail;
use App\Mail\LembreteContaEmail;
use App\Mail\RedefinirSenhaEmail;
use App\Mail\ResumoSemanalEmail;
use App\Models\Cliente;
use App\Models\Conta;
use App\Models\Manutencao;
use App\Models\Oficina;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Tests\TestCase;

/**
 * Todos os e-mails abrem com "Olá" (com o nome quando há um destinatário
 * só) e fecham com "Obrigado, Equipe MyCar". Renderiza cada Mailable com
 * modelos em memória, sem enviar nada.
 */
class EmailsSaudacaoFechamentoTest extends TestCase
{
    use RefreshDatabase;

    private const FECHAMENTO = 'Obrigado,<br>';

    private function veiculo(): Veiculo
    {
        $veiculo = (new Veiculo)->forceFill([
            'marca' => 'Fiat',
            'modelo' => 'Uno',
            'placa' => 'ABC1D23',
        ]);
        $veiculo->setRelation('cliente', (new Cliente)->forceFill(['nome' => 'Marina']));

        return $veiculo;
    }

    private function usuario(): User
    {
        return (new User)->forceFill(['name' => 'Marina', 'email' => 'marina@example.test']);
    }

    private function assertSaudacaoEFechamento(Mailable $mail, string $saudacao): string
    {
        $html = $mail->render();

        $this->assertStringContainsString($saudacao, $html);
        $this->assertStringContainsString(self::FECHAMENTO, $html);
        $this->assertMatchesRegularExpression('/Obrigado,<br>\s*Equipe MyCar/', $html);
        // A saudação vem antes do fechamento.
        $this->assertLessThan(strrpos($html, 'Equipe MyCar'), strpos($html, $saudacao));

        return $html;
    }

    public function test_alertas_pessoais_saudam_pelo_nome_e_fecham_no_padrao(): void
    {
        $this->assertSaudacaoEFechamento(new AlertaIpvaEmail($this->veiculo(), '2026-01-31', false), 'Olá, Marina!');
        $this->assertSaudacaoEFechamento(new AlertaLicenciamentoEmail($this->veiculo(), '2026-07-31', true), 'Olá, Marina!');

        $manutencao = (new Manutencao)->forceFill(['tipo' => 'Troca de óleo', 'proxima_data' => '2026-12-01']);
        $manutencao->setRelation('veiculo', $this->veiculo());
        $this->assertSaudacaoEFechamento(new AlertaManutencaoEmail($manutencao, false), 'Olá, Marina!');
    }

    public function test_emails_de_conta_saudam_pelo_nome_e_fecham_no_padrao(): void
    {
        $this->assertSaudacaoEFechamento(new ConfirmeSeuEmail($this->usuario(), 'https://exemplo.test/c'), 'Olá, Marina!');
        $this->assertSaudacaoEFechamento(new RedefinirSenhaEmail($this->usuario(), 'https://exemplo.test/r'), 'Olá, Marina!');
    }

    public function test_convites_mantem_o_prazo_de_expiracao_e_fecham_no_padrao(): void
    {
        $html = $this->assertSaudacaoEFechamento(
            new ConviteMotoristaEmail($this->usuario(), 'https://exemplo.test/s', 'Ana', 60),
            'Olá, Marina!',
        );
        $this->assertStringContainsString('Este link expira em 60 minutos', $html);

        $html = $this->assertSaudacaoEFechamento(
            new ConvitePessoaEmail($this->usuario(), 'https://exemplo.test/s', 'Ana', 'gestor', 60),
            'Olá, Marina!',
        );
        $this->assertStringContainsString('Este link expira em 60 minutos', $html);
    }

    public function test_lembrete_da_conta_mantem_o_titulo_e_ganha_saudacao_e_fechamento(): void
    {
        $conta = (new Conta)->forceFill(['tipo' => Conta::TIPO_FROTA, 'nome' => 'Transportes Silva']);
        $linhas = [[
            'tipo' => 'ipva',
            'veiculo' => 'Van da entrega',
            'placa' => 'AAA1B23',
            'data' => '2026-01-31',
            'dias' => 26,
            'valor_estimado' => null,
        ]];

        $html = $this->assertSaudacaoEFechamento(new LembreteContaEmail($conta, $linhas), 'Olá!');

        $this->assertStringContainsString('Vencimentos da frota Transportes Silva', $html);
        $this->assertLessThan(strpos($html, 'Olá!'), strpos($html, 'Vencimentos da frota'));
        $this->assertStringContainsString('31/01/2026', $html);
    }

    public function test_resumo_semanal_mantem_o_titulo_e_ganha_saudacao_e_fechamento(): void
    {
        $oficina = (new Oficina)->forceFill(['nome' => 'Oficina do Teste']);
        $secao = ['total' => 0, 'itens' => [], 'restantes' => 0];
        $resumo = [
            'manutencoes_atrasadas' => $secao,
            'manutencoes_proximas' => $secao,
            'ipva' => $secao,
            'licenciamento' => $secao,
            'sem_email' => 0,
            'total' => 0,
        ];

        $html = $this->assertSaudacaoEFechamento(new ResumoSemanalEmail($oficina, $resumo), 'Olá!');

        $this->assertStringContainsString('Resumo da semana', $html);
        $this->assertLessThan(strpos($html, 'Olá!'), strpos($html, 'Resumo da semana'));
    }
}
