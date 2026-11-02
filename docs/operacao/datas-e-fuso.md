# Datas e fuso horário

## A regra

- O horário que a empresa digita é o **horário de Brasília** (`America/Sao_Paulo`, UTC-3, sem horário de verão).
- O banco e o servidor guardam em **UTC**. O Laravel roda com `timezone = UTC`.
- A resposta da API sai com o fuso de Brasília (ex.: `2026-11-10T08:00:00-03:00`).
- O "dia" de qualquer regra de negócio é o **dia de Brasília**.

## Como aplicar

- Use a constante `App\Support\FusoDaFrota::NOME` (`America/Sao_Paulo`) em toda conta de dia.
- Para "hoje": `Carbon::today(FusoDaFrota::NOME)` ou `now(FusoDaFrota::NOME)->toDateString()`.
- Para converter texto digitado: `FusoDaFrota::entrada($texto)` (texto sem fuso é Brasília).
- Para exibir: `FusoDaFrota::saida($data)`.
- Campos do tipo `date` (vencimentos, datas de manutenção) são comparados como texto `Y-m-d` no dia de Brasília.
- Nunca use `now()`, `Carbon::today()` ou `toISOString().slice(0, 10)` para decidir o dia sem informar o fuso.

## O que já foi corrigido

- Agenda, painel e importação da frota: horário digitado gravado em UTC sem conversão (corrigido, com comando de correção dos dados antigos).
- Alertas de CNH e de revisão da frota (`PainelFrotaController::alertas`).
- Vencimentos estimados de IPVA e licenciamento (`CalculaTributosVeiculo`), que viravam para o ano seguinte depois das 21h.
- Alertas de IPVA, licenciamento e manutenção da oficina (`veiculos:alertar-*`, `manutencoes:alertar`).

## Exceções conhecidas

- **Agenda no navegador:** o calendário usa o relógio do próprio navegador. Está certo quando o navegador está em Brasília. Um navegador em outro fuso mostra o dia errado depois das 21h.
- **`saida_em` e `chegada_em` da agenda:** foram gravados com `now()` (instante real, correto). Não são horário digitado.

## Como testar

Use `Carbon::setTestNow(Carbon::parse('2026-11-02 22:00:00', FusoDaFrota::NOME))`. Às 22h em Brasília já é o dia seguinte em UTC, e é esse caso que pega o erro. Exemplos: `AlertasDataTest`, `AlertasOficinaDataTest`, `FusoDaFrotaTest`.
