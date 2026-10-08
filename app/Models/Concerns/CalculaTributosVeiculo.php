<?php

namespace App\Models\Concerns;

/**
 * Estimativas de IPVA e licenciamento de um veículo, a partir de colunas que
 * os dois tipos de veículo têm (placa, uf, fipe_valor). Compartilhado entre
 * "veiculos" (clientes de oficina) e "veiculos_conta" (pessoa e frota).
 */
trait CalculaTributosVeiculo
{
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
     * Aviso (não um cálculo): alguns estados isentam o IPVA de veículos antigos, contando
     * pelo ano de fabricação. Não temos a regra de cada estado verificada, então só
     * sinalizamos que vale conferir na Sefaz. A estimativa de IPVA NÃO muda por isso.
     */
    public function getPossivelIsencaoIpvaAttribute(): bool
    {
        return ($this->idade_anos ?? 0) >= (int) config('tributos.idade_aviso_isencao_ipva', 15);
    }

    /**
     * IPVA estimado = valor FIPE × alíquota do estado (config/tributos.php).
     * Null quando falta o estado, o valor FIPE ou a alíquota do estado.
     */
    public function getIpvaEstimadoAttribute(): ?float
    {
        $aliquota = config("tributos.estados.{$this->uf}.ipva");

        if ($this->fipe_valor === null || $aliquota === null) {
            return null;
        }

        return round((float) $this->fipe_valor * $aliquota / 100, 2);
    }

    public function getLicenciamentoValorAttribute(): ?float
    {
        return config("tributos.estados.{$this->uf}.licenciamento");
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
     * Próximo vencimento estimado do IPVA pelo final da placa
     * (config/ipva.php). ESTIMATIVA genérica — varia por estado.
     */
    public function getProximoVencimentoIpvaAttribute(): ?string
    {
        $mes = config("ipva.meses_por_final_placa.{$this->final_placa}");

        if ($mes === null) {
            return null;
        }

        $vencimento = now()->setDate(now()->year, $mes, 1)->endOfMonth();

        if ($vencimento->isPast()) {
            $vencimento = $vencimento->addYear();
        }

        return $vencimento->toDateString();
    }
}
