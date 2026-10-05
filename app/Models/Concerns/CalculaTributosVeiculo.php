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
