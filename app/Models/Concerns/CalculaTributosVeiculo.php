<?php

namespace App\Models\Concerns;

use App\Enums\TipoVeiculo;

/**
 * Estimativas de IPVA e licenciamento de um veículo, a partir de colunas que
 * os dois tipos de veículo têm (placa, uf, fipe_valor). Compartilhado entre
 * "veiculos" (clientes de oficina) e "veiculos_conta" (pessoa e frota).
 */
trait CalculaTributosVeiculo
{
    /**
     * As estimativas de IPVA e licenciamento, a isenção por idade e o seguro de
     * referência foram montados para carros de passeio. Motos e caminhões têm
     * alíquotas e regras próprias que não estão cadastradas: para eles o app não
     * estima nem afirma isenção, em vez de mostrar um número que pode estar errado.
     */
    public function ehCarro(): bool
    {
        return ($this->tipo_veiculo ?? TipoVeiculo::Carro) === TipoVeiculo::Carro;
    }

    /** Tipo na API da FIPE ("carros", "motos" ou "caminhoes"). */
    public function tipoFipe(): string
    {
        return ($this->tipo_veiculo ?? TipoVeiculo::Carro)->fipe();
    }

    /**
     * Idade, em anos, pelo ano de FABRICAÇÃO (é a que os estados contam); sem ele,
     * usa o ano do modelo.
     */
    public function getIdadeAnosAttribute(): ?int
    {
        $base = $this->ano_fabricacao ?: $this->ano;

        return $base ? max(0, now()->year - (int) $base) : null;
    }

    /** Como no CRLV: "2018/2019" (fabricação/modelo); só "2018" quando é o mesmo ano ou sem fabricação. */
    public function getAnoCompletoAttribute(): ?string
    {
        if (!$this->ano) {
            return null;
        }

        return $this->ano_fabricacao && (int) $this->ano_fabricacao !== (int) $this->ano
            ? "{$this->ano_fabricacao}/{$this->ano}"
            : (string) $this->ano;
    }

    /**
     * Isento de IPVA em qualquer estado: mais de 20 anos de fabricação (EC 137/2025).
     * Sem ano de fabricação, vale o ano do modelo, que é igual ou posterior: então
     * nunca isentamos um carro que ainda não é.
     */
    public function getIsentoIpvaAttribute(): bool
    {
        return $this->ehCarro()
            && ($this->idade_anos ?? 0) > (int) config('tributos.idade_isencao_ipva_constitucional', 20);
    }

    /**
     * Aviso (não um cálculo): alguns estados isentam o IPVA de veículos mais novos que o
     * limite constitucional (10, 15 ou 18 anos). Não temos a regra de cada estado
     * verificada, então só sinalizamos que vale conferir na Sefaz. Quem já é isento
     * pela Constituição não recebe este aviso (ver isento_ipva).
     */
    public function getPossivelIsencaoIpvaAttribute(): bool
    {
        return $this->ehCarro()
            && !$this->isento_ipva
            && ($this->idade_anos ?? 0) >= (int) config('tributos.idade_aviso_isencao_ipva', 15);
    }

    /**
     * Alíquota usada na estimativa: a do estado (config/tributos.php) ou, nos
     * estados ainda sem alíquota confirmada, a média nacional aproximada.
     * Null sem estado informado.
     */
    private function aliquotaIpva(): ?float
    {
        if (!$this->uf) {
            return null;
        }

        $aliquota = config("tributos.estados.{$this->uf}.ipva");

        return $aliquota ?? config('tributos.aliquota_media_ipva');
    }

    /**
     * IPVA estimado = valor FIPE × alíquota. Valor APROXIMADO: null só quando
     * falta o estado ou o valor FIPE.
     */
    public function getIpvaEstimadoAttribute(): ?float
    {
        if (!$this->ehCarro() || $this->isento_ipva) {
            return null;
        }

        $aliquota = $this->aliquotaIpva();

        if ($this->fipe_valor === null || $aliquota === null) {
            return null;
        }

        return round((float) $this->fipe_valor * $aliquota / 100, 2);
    }

    /**
     * True quando o valor do IPVA usou a alíquota média por não termos a do
     * estado confirmada; o frontend avisa que é ainda mais aproximado.
     */
    public function getIpvaAliquotaMediaAttribute(): bool
    {
        return $this->ehCarro()
            && $this->uf
            && config("tributos.estados.{$this->uf}.ipva") === null;
    }

    public function getLicenciamentoValorAttribute(): ?float
    {
        return $this->ehCarro() ? config("tributos.estados.{$this->uf}.licenciamento") : null;
    }

    /**
     * Último dígito da placa, usado pelo calendário de licenciamento
     * (config/licenciamento.php). Formatos antigo e Mercosul sempre
     * terminam em número.
     */
    public function getFinalPlacaAttribute(): ?int
    {
        $ultimoCaractere = substr((string) $this->placa, -1);

        return ctype_digit($ultimoCaractere) ? (int) $ultimoCaractere : null;
    }

    /**
     * Próxima data de vencimento do licenciamento (CRLV), estimada a
     * partir do final da placa (config/licenciamento.php). É sempre o
     * último dia do mês de referência, no ano corrente ou no próximo caso
     * a data deste ano já tenha passado. ESTIMATIVA — ver aviso no config.
     */
    public function getProximoVencimentoLicenciamentoAttribute(): ?string
    {
        $mes = config("licenciamento.meses_por_final_placa.{$this->final_placa}");

        if ($mes === null) {
            return null;
        }

        $vencimento = now()->setDate(now()->year, $mes, 1)->endOfMonth();

        if ($vencimento->isPast()) {
            $vencimento = $vencimento->addYear();
        }

        return $vencimento->toDateString();
    }

    /**
     * Próximo vencimento estimado do IPVA: último dia de janeiro
     * (config/ipva.php), igual para todos os veículos. ESTIMATIVA — as datas
     * e o parcelamento variam por estado.
     */
    public function getProximoVencimentoIpvaAttribute(): ?string
    {
        // Veículo isento não tem vencimento: sem lembrete, sem e-mail, sem item no resumo.
        if ($this->isento_ipva) {
            return null;
        }

        $mes = (int) config('ipva.mes_vencimento', 1);

        $vencimento = now()->setDate(now()->year, $mes, 1)->endOfMonth();

        if ($vencimento->isPast()) {
            $vencimento = $vencimento->addYear();
        }

        return $vencimento->toDateString();
    }
}
