# Métricas de produto do MyCar

> Documento de trabalho. Nenhuma consulta deste arquivo foi executada: todas são **ilustrativas e NÃO executadas**.
> Base: `origin/main` em `771c62a`. Cada fonte de dado cita a migration (nome do arquivo e linha) ou o arquivo de código e a linha.
> Metas são **hipóteses a validar**, não números de mercado.

Referências de método (só o roteiro, sem reproduzir conteúdo):

- HEART: Rodden, Hutchinson e Fu, *Measuring the User Experience on a Large Scale: User-Centered Metrics for Web Applications*, CHI 2010, DOI 10.1145/1753326.1753687 (<https://research.google/pubs/pub36299/>).
- AARRR: Dave McClure, *Startup Metrics for Pirates*, Ignite Seattle, 2007 (<https://www.slideshare.net/slideshow/startup-metrics-for-pirates-presentation/629833>).

O roteiro usado em cada dimensão é **meta, sinal, métrica**: o que queremos que aconteça (meta), o que no banco indica que aconteceu (sinal) e o número que acompanhamos (métrica).

---

## 1. Princípios

### 1.1 O que medir

- **Comportamento agregado por conta** (`contas.id`) ou por oficina (`oficinas.id`): quantas reservas, quantas saídas, quantos módulos em uso, se voltou na semana 4. A unidade de análise é a conta, nunca a pessoa.
- **Contagens, proporções e durações**, nunca o valor de um campo de texto.
- **Distribuições entre contas** (mediana, quartis, quantas contas acima de X) para o painel do operador; o número de uma conta específica só aparece onde o `/admin` já mostra números daquela conta (lista de contas).

### 1.2 O que NÃO medir

- **Conteúdo de oficina ou de cliente**: nada de `clientes`, placas, nomes de motorista, CNH, telefones, descrição de ocorrência, multa ou despesa, `observacoes`, `finalidade`, `motivo`, `posto`, `seguradora`, `cliente_nome`/`cliente_telefone` da estética. As consultas deste documento nunca selecionam essas colunas.
- **Colunas JSON e de texto livre** (`usos_veiculo_eventos.dados` e `.motivo`, `acessos_frota_eventos.detalhes`, `checklists_uso.itens`, `abastecimentos.alertas`, `sugestoes.descricao`): só se conta a linha, não se lê o conteúdo. Exceção: `checklists_uso.problemas`, que já é um número gravado justamente para não abrir o JSON (`2026_11_02_150000_add_problemas_to_checklists_uso.php:12-14`).
- **Nada cruza contas**: toda consulta agrupa por `conta_id` (ou `oficina_id`) antes de somar. Nenhuma junção liga uma conta a outra (a mesma placa pode existir em duas contas, `2026_10_06_000002_create_veiculos_conta_table.php:32-33`, e isso não pode virar métrica).
- **Sem dado pessoal no resultado**: nada de `users.name`, `users.email`, `ip`, `user_agent`, `dispositivo`. `user_id` só aparece dentro da consulta (para saber o papel), nunca na saída.
- **Nada de rastreio individual de motorista** (quantas horas fulano dirigiu). Isso é relatório da própria frota, dentro da conta, não métrica de produto.

### 1.3 LGPD

- **Finalidade**: melhorar o produto e decidir prioridades. Não serve para marketing nem para avaliar pessoas.
- **Minimização**: usar o que já existe; o que for instrumentado guarda só conta, perfil, papel, tipo de evento e dia (seção 5).
- **Conta pessoa é uma pessoa**: no perfil Pessoa (e em frota ou estética de um usuário só), o agregado "por conta" é na prática dado de uma pessoa natural. Isso continua sendo dado pessoal; vale o mesmo cuidado.
- **Base legal: a confirmar com o dono** (provavelmente legítimo interesse, com avaliação documentada, ou execução de contrato para o mínimo operacional). Não está decidido neste documento.
- **Transparência**: a política de privacidade precisa dizer que há métricas de uso agregadas. Verificar o texto atual antes de instrumentar.
- **Acesso ao conteúdo já é auditado**: abrir o conteúdo de um usuário grava em `acessos_admin` antes de mostrar (`AdminConteudoController.php:65-72`; tabela em `2026_10_19_000000_create_acessos_admin_table.php:13-21`). As métricas deste documento não precisam do `AdminConteudoController` e não devem passar por ele.

---

## 2. Fontes de dado que já existem (confirmadas nas migrations)

| Tabela | Colunas úteis para métricas | Migration (linha) |
|---|---|---|
| `users` | `created_at`, `email_verified_at` | `0001_01_01_000000_create_users_table.php:14-21` |
| `users` | `oficina_id` | `2026_09_28_100001_add_oficina_id_to_users_table.php:12` |
| `users` | `is_super_admin`, `ultimo_acesso_em` | `2026_10_04_100000_add_painel_operador_to_users_table.php:14,17` |
| `users` | `perfil`, `conta_id` | `2026_10_06_000001_add_perfil_e_conta_to_users_table.php:13,15` |
| `users` | `papel_frota` (administrador, gestor, motorista) | `2026_10_25_000000_frota_fase2_rastreio_e_papeis.php:14` |
| `contas` | `tipo`, `created_at` | `2026_10_06_000000_create_contas_table.php:14-18` |
| `contas` | `lembretes_email` | `2026_10_07_000000_add_lembretes_to_contas.php:14` |
| `contas` | `plano`, `limite_veiculos` | `2026_11_02_120000_add_plano_to_contas_table.php:13-14` |
| `oficinas` | `arquivada_em` | `2026_10_06_000003_add_arquivada_em_to_oficinas_table.php:15` |
| `acessos` (MyCar extra do mesmo e-mail) | `user_id`, `perfil`, `conta_id`, `oficina_id`, `papel_frota` | `2026_10_26_000000_create_acessos_table.php:15-25` |
| `tokens_acesso` | `user_id`, `ultimo_uso_em`, `created_at`, `acesso_id` | `2026_10_16_000000_create_tokens_acesso_table.php:14-22`; `acesso_id` em `2026_10_26_000000_create_acessos_table.php:31` |
| `contas_sociais` | `provedor`, `created_at` | `2026_10_23_000000_create_contas_sociais_table.php:11-16` |
| `veiculos_conta` | `conta_id`, `created_at` | `2026_10_06_000002_create_veiculos_conta_table.php:13-30` |
| `veiculos_conta` | `km_atual`, `km_atualizado_em`, `centro_custo_id` | `2026_10_24_000200_add_km_e_centro_custo_to_veiculos_conta.php:13-16` |
| `veiculos_conta` | `qr_token` | `2026_11_02_130000_create_frota_diferenciais_tables.php:13` |
| `veiculos_conta` | `possui_seguro` | `2026_10_27_000000_add_possui_seguro_to_veiculos_conta.php:14` |
| `motoristas` | `conta_id`, `user_id`, `ativo`, `cnh_validade`, `created_at` | `2026_10_24_000100_create_motoristas_table.php:11-23` |
| `motoristas` | `centro_custo_id` | `2026_10_31_130000_add_centro_custo_id_to_motoristas.php:13` |
| `usos_veiculo` | `inicio_previsto`, `fim_previsto`, `status`, `saida_em`, `chegada_em`, `origem`, `created_at` | `2026_10_24_000300_create_usos_veiculo_table.php:20-39` |
| `usos_veiculo` | `saida_por_user_id`, `chegada_por_user_id`, `km_saida_origem`, `km_chegada_origem` | `2026_10_25_000000_frota_fase2_rastreio_e_papeis.php:22-25` |
| `usos_veiculo_eventos` | `conta_id`, `uso_veiculo_id`, `user_id`, `acao`, `created_at` | `2026_10_25_000000_frota_fase2_rastreio_e_papeis.php:29-43` |
| `usos_veiculo_fotos` | `momento`, `substituida_em`, `apagada_em`, `created_at` | `2026_10_25_000000_frota_fase2_rastreio_e_papeis.php:46-60` |
| `usos_veiculo_centros` | `conta_id`, `created_at` | `2026_10_29_000100_create_usos_veiculo_centros_table.php:15-29` |
| `centros_custo` | `conta_id`, `ativo` | `2026_10_24_000000_create_centros_custo_table.php:11-21` |
| `grupos_usuarios`, `grupo_usuario_membros` | `conta_id`, `created_at` | `2026_10_29_000000_create_grupos_usuarios_table.php:13-30` |
| `acessos_frota_eventos` | `conta_id`, `alvo_tipo`, `acao`, `created_at` | `2026_11_02_100000_create_acessos_frota_eventos_table.php:12-24` |
| `checklists_uso` | `conta_id`, `uso_veiculo_id`, `momento`, `feito_por_user_id`, `created_at` | `2026_11_02_130000_create_frota_diferenciais_tables.php:17-27` |
| `checklists_uso` | `problemas` | `2026_11_02_150000_add_problemas_to_checklists_uso.php:14` |
| `checklists_uso` | `checklist_modelo_id` | `2026_11_03_130000_checklists_personalizaveis.php:39` |
| `checklist_modelos` | `conta_id`, `ativo` | `2026_11_03_130000_checklists_personalizaveis.php:12-20` |
| `multas` | `conta_id`, `uso_veiculo_id`, `motorista_id`, `situacao`, `data_hora`, `created_at` | `2026_11_02_130000_create_frota_diferenciais_tables.php:31-45` |
| `ocorrencias` | `conta_id`, `uso_veiculo_id`, `tipo`, `status`, `created_at` | `2026_11_03_110000_create_ocorrencias_frota_tables.php:12-31` |
| `despesas`, `categorias_despesa` | `conta_id`, `data`, `created_at` | `2026_11_03_100000_create_financeiro_frota_tables.php:12-38` |
| `abastecimentos` | `conta_id`, `data`, `created_at` | `2026_10_08_000000_create_abastecimentos_table.php:11-25` |
| `abastecimentos` | `motorista_id`, `alertas` (só "tem ou não tem") | `2026_11_03_120000_melhorias_no_abastecimento.php:13,20` |
| `servicos_conta` | `conta_id`, `realizado_em`, `created_at` | `2026_10_11_000000_create_servicos_conta_table.php:11-33` |
| `servicos_conta` | `natureza` | `2026_11_03_140000_evolucao_da_manutencao.php:13` |
| `seguros` | `conta_id`, `tipo`, `alertado_em` | `2026_10_09_000000_create_seguros_table.php:11-27` |
| `documentos_conta` | `conta_id`, `vencimento`, `alertado_em` | `2026_10_10_000000_create_documentos_conta_table.php:11-24` |
| `fotos_veiculo_conta` | `conta_id`, `created_at` | `2026_10_16_000100_create_fotos_veiculo_conta_table.php:13-24` |
| `sugestoes` | `user_id`, `categoria`, `perfil`, `status`, `created_at` | `2026_10_15_000000_create_sugestoes_table.php:13-25` |
| `servicos_estetica`, `pacotes_estetica` | `conta_id`, `ativo` | `2026_11_02_140000_create_estetica_tables.php:12-30` |
| `atendimentos_estetica` | `conta_id`, `etapa`, `pago`, `entrada_em`, `previsao_entrega`, `entregue_em`, `created_at` | `2026_11_02_140000_create_estetica_tables.php:40-60` |
| `fotos_estetica` | `conta_id`, `atendimento_id`, `momento` | `2026_11_02_140000_create_estetica_tables.php:73-82` |

Valores que vêm do código (não do esquema):

- `contas.tipo`: `pessoa`, `frota`, `estetica` (`app/Models/Conta.php:14-20`). O comentário da migration ainda diz só `pessoa | frota` (`2026_10_06_000000_create_contas_table.php:16`).
- `usos_veiculo.status`: `agendado`, `em_uso`, `concluido`, `cancelado` (`app/Models/UsoVeiculo.php:25`); origem do km `foto` ou `manual` (`app/Models/UsoVeiculo.php:33`; decidido em `UsoVeiculoController.php:371` e `:431`).
- `usos_veiculo.origem`: `agenda` ou `planilha` (`2026_10_24_000300_create_usos_veiculo_table.php:35-36`; importação em `app/Services/ImportadorUsosVeiculo.php:257-270`).
- `usos_veiculo_eventos.acao` gravadas hoje: `reservou` (`UsoVeiculoController.php:145`), `editou` (`:203`), `cancelou` (`:282`), `apagou` (`:302`), `saiu` (`:391`), `mudou_motivo` (`:394`), `chegou` (`:447`), `conflito_por_devolucao` (`:453`), `anexou_foto` (`:510`), `corrigiu_km` (`:538`), `trocou_centro` (`app/Models/UsoVeiculo.php:131`), `checklist_saida` / `checklist_devolucao` (`ChecklistUsoController.php:104`).
- `acessos_frota_eventos.acao`: `convidou` (`PessoaFrotaController.php:89`), `reenviou_convite` (`:134`), `mudou_papel` (`:169`), `ligou_motorista` (`:263`), e para grupos e modelos `criou`, `editou`, `removeu`, `salvou` (`GrupoUsuarioController.php:52,89,109`; `ModeloGrupoController.php:61,78`).
- Estado de acesso do motorista: `sem_acesso` (sem `user_id`), `convite_enviado` (`user_id` e `ultimo_acesso_em` vazio), `ativo` (já entrou) (`app/Models/Motorista.php:75-90`).
- `checklists_uso.momento`: `saida` e `devolucao` (`app/Models/ChecklistUso.php:16-20`). Atenção: as fotos usam `saida` e `chegada`.
- `ocorrencias.status` em aberto: `aberta`, `em_analise`, `aguardando_orcamento`, `em_reparo` (`app/Models/Ocorrencia.php:28-38`). `multas.situacao`: `aberta`, `paga`, `recorrida` (`app/Models/Multa.php:14-20`). `atendimentos_estetica.etapa`: `entrada`, `atendimento`, `entrega`, `entregue` (`app/Models/AtendimentoEstetica.php:18-26`).

### 2.1 O que o `/admin` já calcula

`GET /admin/resumo` (`AdminController.php:30-53`, rota em `routes/api.php:122-125`):

- oficinas não arquivadas; contas `pessoa` e `frota`; usuários; e-mails pendentes; cadastros em 7 e 30 dias; ativos em 7 dias (`users.ultimo_acesso_em`); totais de clientes, veículos, OS e manutenções das oficinas ativas.

`GET /admin/contas` (`AdminController.php:55-149`): lista paginada de usuários com perfil, e-mail confirmado, cadastro, último acesso e contagens (veículos da conta, ou clientes/veículos/OS/manutenções da oficina).

---

## 3. Achados que mudam a leitura dos números atuais

1. **"Ativos em 7 dias" subestima o uso.** `users.ultimo_acesso_em` só é atualizado ao abrir sessão (`app/Models/User.php:80`, dentro de `abrirSessao`), não a cada uso. Quem fica logado (o token vale até 90 dias sem uso, `app/Models/TokenAcesso.php:21`) usa o sistema todo dia e aparece como inativo. O que reflete uso real é `tokens_acesso.ultimo_uso_em`, atualizado no máximo a cada 5 minutos (`app/Http/Middleware/AuthenticateToken.php:15,51-52`), mas a linha é apagada no logout (`AuthController.php:54`) e na limpeza de 90 dias, então só mostra o **estado atual**, sem histórico.
2. **"Cadastros" mistura quem se cadastrou com quem foi convidado.** O convite da frota cria um `users` (`PessoaFrotaController.php:64-71`) e já marca o e-mail como confirmado (`:74`). Para aquisição, a contagem certa é `contas.created_at` por `tipo` (uma linha por cadastro de conta, `RegisterController.php:62-71`) mais `oficinas.created_at`.
3. **Estética não aparece no resumo.** `AdminController.php:38-41` conta só `pessoa` e `frota`; contas `estetica` (`Conta.php:18`) ficam fora.
4. **Uso importado de planilha não é uso do app.** A importação grava `status = concluido` com `saida_em` e `chegada_em` do passado (`ImportadorUsosVeiculo.php:264-269`). Toda métrica de engajamento em `usos_veiculo` precisa de `origem = 'agenda'`.
5. **Não há exclusão lógica.** Nenhuma migration usa `softDeletes`/`deleted_at`. Reservas agendadas e canceladas são apagadas de fato (`UsoVeiculoController.php:296-304`); a conta apagada leva tudo em cascata (`cascadeOnDelete` em quase todas as tabelas). As trilhas `usos_veiculo_eventos` e `acessos_frota_eventos` sobrevivem ao registro apagado, mas não à conta apagada. Coortes antigas têm viés de sobrevivência.
6. **Fuso.** O app grava em UTC (`config/app.php:71`; `app/Support/FusoDaFrota.php`, fuso de negócio `America/Sao_Paulo`). Datas da frota gravadas antes do corte do fuso foram (ou serão) corrigidas uma vez por `frota:corrigir-fuso-antigo` (`app/Console/Commands/CorrigirFusoAntigo.php:11-15`; marca em `correcoes_de_dados`). Para "dia" e "semana" de negócio, subtrair 3 horas antes de truncar.
7. **Não existe marcação de conta de teste.** Só `users.is_super_admin` distingue o operador. Contas de teste, demonstração e do próprio dono precisam de uma lista mantida à mão (ou de uma coluna nova, decisão do dono).
8. **Rejeições não deixam rastro.** Conflito de horário na reserva (`UsoVeiculoController.php:691-730`), limite de veículos do plano (`ContaVeiculoController.php:59-61`) e falha de leitura do hodômetro por IA (`LeituraHodometroController.php:23-37`) respondem erro e não gravam nada. Só o conflito causado por devolução atrasada vira evento (`:453`).
9. **Não há cobrança no código.** Existem planos e limite de veículos (`Conta.php:26-31`), definidos só pelo comando `conta:plano` (`app/Console/Commands/DefinirPlanoConta.php:14,31,43`). Não há preço, pagamento, fatura nem histórico de troca de plano.
10. **Não há instrumentação de frontend.** O frontend não tem biblioteca de analytics nem suporte offline (busca por gtag, posthog, mixpanel, amplitude, hotjar, plausible, sentry, serviceWorker, navigator.onLine em `mycar_frontend/src` sem resultado).

---

## 4. HEART

Legenda da fonte: **[existe]** dá para calcular hoje; **[parcial]** dá com ressalvas fortes; **[sem fonte]** precisa de instrumentação (seção 6).

### 4.1 Frota: gestor (papel `administrador` ou `gestor`)

| Dim. | Meta | Sinal | Métrica | Fonte |
|---|---|---|---|---|
| H | O gestor sente que o MyCar resolve a agenda da frota | Envia sugestão; o tipo da sugestão | H1. Sugestões por 100 contas frota ativas no mês, por `categoria` (só contagem) | [parcial] `sugestoes.perfil`, `.categoria`, `.created_at` (`2026_10_15_000000...:17,20`). Viés: quem reclama é quem escreve. |
| H | Idem | Resposta direta de satisfação | H2. Satisfação em escala curta após X semanas | [sem fonte] |
| E | O gestor usa o MyCar para operar a frota toda semana | Reservas criadas por gestor | E1. Contas frota com ≥ 1 `reservou` feito por gestor na semana (WAU de conta) | [existe] `usos_veiculo_eventos.acao`, `.user_id`, `.created_at` + `users.papel_frota` |
| E | Idem | Dias com atividade | E2. Mediana de dias distintos com evento por conta, por semana | [existe] `usos_veiculo_eventos.created_at` |
| E | Usa além da agenda | Registros nos módulos | E3. Módulos com registro no mês por conta (despesas, multas, ocorrências, abastecimentos, serviços, checklists) | [existe] `created_at` de cada tabela (seção 2) |
| A | Contas novas adotam o fluxo completo | Motoristas com login | A1. % contas frota com ≥ 1 motorista em estado `ativo` | [existe] `motoristas.user_id` + `users.ultimo_acesso_em` (`Motorista.php:81-90`) |
| A | Módulos avançados são adotados | Configuração feita | A2. % contas com centro de custo ativo / modelo de checklist próprio / grupo de usuários / QR gerado | [existe] `centros_custo.ativo`, `checklist_modelos.ativo`, `grupos_usuarios`, `veiculos_conta.qr_token` |
| A | Funcionalidade nova é experimentada | Primeiro uso após o lançamento | A3. Contas que usaram a função pela primeira vez, por semana desde o lançamento | [existe] mínimo de `created_at` por conta na tabela da função |
| R | A conta continua usando depois do começo | Atividade na semana N após o cadastro | R1. % contas da coorte com evento de uso nas semanas 4, 8 e 12 | [existe] `contas.created_at` + `usos_veiculo_eventos` |
| R | O gestor volta ao sistema | Sessão em uso | R2. Gestores com `tokens_acesso.ultimo_uso_em` nos últimos 7 e 28 dias | [parcial] só o estado atual (achado 1) |
| T | Reservar sem atrito | Reserva cancelada ou apagada | T1. % reservas `cancelou` + `apagou` sobre `reservou` | [existe] `usos_veiculo_eventos.acao` |
| T | Agenda sem choque | Conflito na devolução | T2. `conflito_por_devolucao` por 100 chegadas | [existe] idem; rejeição na reserva [sem fonte] (achado 8) |
| T | Dado certo de primeira | Correções depois do registro | T3. (`editou` + `corrigiu_km` + `trocou_centro`) por 100 usos com saída | [existe] idem |
| T | Ocorrências resolvidas | Ocorrência fechada | T4. Mediana de dias entre `ocorrencias.created_at` e o `updated_at` das concluídas | [parcial] `updated_at` muda em qualquer edição; não há `concluida_em` |
| T | Limite de plano não trava | Conta no limite | T5. Contas com `count(veiculos_conta) >= limite_veiculos` | [existe] `contas.limite_veiculos`; tentativas barradas [sem fonte] |

Total gestor: H 2 (1 sem fonte), E 3, A 3, R 2, T 5.

### 4.2 Frota: motorista (papel `motorista`)

| Dim. | Meta | Sinal | Métrica | Fonte |
|---|---|---|---|---|
| H | Registrar o uso é rápido e não incomoda | Sugestões de motoristas | H1. Sugestões de usuários com `papel_frota = 'motorista'` por mês, por categoria | [parcial] `sugestoes.user_id` + `users.papel_frota` (o papel é o atual, não o do envio) |
| H | Idem | Percepção de esforço | H2. Pergunta de uma nota após a chegada | [sem fonte] |
| E | O motorista registra a própria saída e chegada | Saída registrada por ele | E1. % saídas em que `saida_por_user_id` é o `user_id` do motorista do uso | [existe] `usos_veiculo.saida_por_user_id`, `motoristas.user_id` |
| E | Idem | Usos por motorista ativo | E2. Mediana de usos concluídos por motorista com acesso, por semana, por conta | [existe] `usos_veiculo.motorista_id`, `.chegada_em` |
| A | Convite vira uso | Estado do acesso | A1. Funil por conta: motoristas `sem_acesso` → `convite_enviado` → `ativo` | [existe] `Motorista.php:81-90` |
| A | Idem | Tempo do convite ao primeiro acesso | A2. Dias entre `convidou` e a primeira saída registrada pelo motorista | [parcial] `acessos_frota_eventos` (`acao = 'convidou'`, `alvo_id` = user) + `usos_veiculo.saida_em`; o primeiro login em si não fica registrado |
| A | Foto do hodômetro é adotada | km por foto | A3. % saídas e chegadas com `km_*_origem = 'foto'` | [existe] `2026_10_25_000000...:24-25` |
| R | O motorista continua usando o app | Saída registrada por ele na semana N | R1. % motoristas com primeira saída na semana S que registram saída em S+4 | [existe] `usos_veiculo.saida_por_user_id`, `.saida_em` |
| T | Saída completa | Checklist feito | T1. % usos com saída (`origem = 'agenda'`) que têm `checklists_uso` de `saida` | [existe] |
| T | Devolução registrada | Chegada não esquecida | T2. Usos `em_uso` com `fim_previsto` vencido há mais de 24 h | [existe] `usos_veiculo.status`, `.fim_previsto` |
| T | Devolução no horário | Atraso | T3. Mediana de (`chegada_em` - `fim_previsto`) nos concluídos da agenda | [existe] (mede pontualidade da operação, não o esforço da tela) |
| T | Reserva vira uso | Reserva esquecida | T4. Usos `agendado` com `inicio_previsto` passado há mais de 24 h sem saída | [existe] |
| T | Leitura do hodômetro por IA ajuda | Sugestão aceita | T5. % leituras por IA aceitas sem edição | [sem fonte] (`LeituraHodometroController.php` não grava nada) |
| T | Tempo para registrar saída | Duração do formulário | T6. Segundos entre abrir a saída e confirmar | [sem fonte] |

Total motorista: H 2 (1 sem fonte), E 2, A 3, R 1, T 6 (2 sem fonte).

### 4.3 Pessoa (cuidados com o carro), resumo

| Dim. | Métrica | Fonte |
|---|---|---|
| H | Sugestões de `perfil = 'pessoa'` por 100 contas ativas | [parcial] `sugestoes` |
| E | Registros por conta e mês em `abastecimentos`, `servicos_conta`, `documentos_conta`, `seguros`, `fotos_veiculo_conta` | [existe] `created_at` de cada tabela (todas com `conta_id`) |
| A | % contas pessoa com ≥ 1 veículo; % com ≥ 1 documento com `vencimento`; % com `lembretes_email` ligado | [existe] `veiculos_conta`, `documentos_conta.vencimento`, `contas.lembretes_email` |
| R | % contas com registro novo no mês 2 e no mês 3 após o cadastro | [existe] |
| T | Lembrete útil: % lembretes enviados (`alertado_em`) seguidos de registro do mesmo tipo em 30 dias | [parcial] `documentos_conta.alertado_em`, `seguros.alertado_em`, `servicos_conta.alerta_data_em`; não sabemos se o e-mail foi aberto |

Atenção: `veiculos_conta`, `abastecimentos` etc. servem a pessoa e frota (e estética para `veiculos_conta`); filtrar sempre por `contas.tipo`.

### 4.4 Estética, resumo

| Dim. | Métrica | Fonte |
|---|---|---|
| H | Sugestões de `perfil = 'estetica'` | [parcial] `sugestoes.perfil` (o comentário da migration não lista estética; confirmar o valor gravado) |
| E | Atendimentos por conta e semana (`entrada_em`) | [existe] `atendimentos_estetica` |
| A | % contas com catálogo (`servicos_estetica` ativo, `pacotes_estetica`); % atendimentos com foto de check-in | [existe] `fotos_estetica.momento` |
| R | % contas com atendimento nas semanas 4 e 8 após o cadastro | [existe] |
| T | % atendimentos `entregue`; % entregues até `previsao_entrega`; % `pago` entre os entregues | [existe] `etapa`, `entregue_em`, `previsao_entrega`, `pago` |

Nunca selecionar `placa`, `modelo`, `cliente_nome`, `cliente_telefone`, `valor` individual (`2026_11_02_140000...:45-50`).

### 4.5 Oficina

Fora do foco deste documento. O `/admin` já mostra os totais. Uma métrica de retenção simples é OS abertas por oficina não arquivada por mês (`ordens_servico.data_abertura`, `2026_08_13_002102_create_ordens_servico_table.php:29`, com `oficina_id` adicionado em `2026_09_28_100004`).

---

## 5. AARRR (funil)

### Aquisição

- **Cadastros por perfil e mês**: `contas.created_at` por `contas.tipo` (pessoa, frota, estetica) e `oficinas.created_at` (oficina). Não usar `users.created_at` (achado 2).
- **Canal**: só é possível separar o cadastro social (`contas_sociais.provedor`; o login social cria conta `pessoa`, `SocialAuthController.php:117-122`). Não há UTM, origem nem página de entrada: **[sem fonte]**.
- **Confirmação de e-mail**: % das contas cujo usuário criador tem `email_verified_at`. Limite: o criador é o `users` mais antigo da conta (não há coluna "dono").

### Ativação (definição operacional, frota)

Proposta, **a validar com o dono**: uma conta frota **ativou** quando, em até 14 dias de `contas.created_at`, tem:

1. ≥ 1 linha em `veiculos_conta`;
2. ≥ 1 linha em `motoristas`;
3. ≥ 1 `usos_veiculo` com `origem = 'agenda'` (primeira reserva);
4. ≥ 1 `usos_veiculo` com `origem = 'agenda'` e `saida_em` preenchido (primeiro registro de saída).

**Ativação plena** (opcional): além disso, ≥ 1 saída registrada por um motorista com login (`saida_por_user_id = motoristas.user_id`).

- Pessoa ativou: ≥ 1 veículo e ≥ 1 registro (abastecimento, serviço, documento ou seguro) em 14 dias.
- Estética ativou: ≥ 1 serviço no catálogo e ≥ 1 atendimento `entregue` em 14 dias.

### Retenção

- **Conta retida na semana N**: tem ≥ 1 evento de uso real (`usos_veiculo_eventos.acao IN ('reservou','saiu','chegou')`, ou registro nas tabelas do perfil) entre os dias 7(N-1) e 7N após `contas.created_at`. Medir N = 4, 8, 12.
- Usar eventos, não `ultimo_acesso_em` (achado 1).

### Receita

- Hoje: **não há cobrança no código** (achado 9). Dá para medir **distribuição por plano** (`contas.plano`, nulo = sem limite) e **pressão de limite** (contas com veículos ≥ `limite_veiculos`, ou a 1 veículo do limite).
- Não dá para medir: receita, conversão de teste para pago, troca de plano (não há histórico), cancelamento.

### Indicação

- **Não existe** mecanismo de indicação, código de convite entre contas nem campo de origem. O convite de motorista e gestor (`acessos_frota_eventos.acao = 'convidou'`) é **expansão dentro da conta**, não indicação: pode ser medido como "pessoas convidadas por conta" (expansão), mas não deve ser chamado de referral.

---

## 6. Consultas ilustrativas (NÃO executadas)

Regras comuns:

- Escritas em SQLite (o padrão do projeto, `config/database.php:20`); diferenças para MySQL anotadas. Há também uma migration só para PostgreSQL (`2026_10_25_000100_exclusao_de_reservas_sobrepostas_postgres.php`); o banco de produção deve ser confirmado com o dono.
- Datas em UTC. Para dia/semana de Brasília: SQLite `datetime(col, '-3 hours')`; MySQL `col - INTERVAL 3 HOUR` (ou `CONVERT_TZ` se as tabelas de fuso estiverem carregadas).
- Semana: SQLite `strftime('%Y-%W', x)`; MySQL `DATE_FORMAT(x, '%x-%v')` ou `YEARWEEK(x, 3)`. Mês: SQLite `strftime('%Y-%m', x)`; MySQL `DATE_FORMAT(x, '%Y-%m')`.
- Diferença em dias: SQLite `julianday(b) - julianday(a)`; MySQL `TIMESTAMPDIFF(HOUR, a, b) / 24.0`.
- Excluir contas de teste: todas as consultas assumem uma CTE `contas_validas` como abaixo, com a lista de teste mantida pelo dono.

```sql
-- Base comum (NÃO executada). Exclui contas de quem é operador e uma lista manual de teste.
WITH contas_validas AS (
  SELECT c.id, c.tipo, c.created_at
  FROM contas c
  WHERE c.id NOT IN (SELECT u.conta_id FROM users u WHERE u.is_super_admin = 1 AND u.conta_id IS NOT NULL)
    AND c.id NOT IN (/* ids de contas de teste, lista do dono */ 0)
)
SELECT 1;
```

### Q1. Cadastros por perfil e mês (aquisição)

```sql
-- NÃO executada.
WITH contas_validas AS (/* ver base */ SELECT id, tipo, created_at FROM contas)
SELECT strftime('%Y-%m', datetime(created_at, '-3 hours')) AS mes, tipo, COUNT(*) AS cadastros
FROM contas_validas
GROUP BY mes, tipo
UNION ALL
SELECT strftime('%Y-%m', datetime(created_at, '-3 hours')), 'oficina', COUNT(*)
FROM oficinas
GROUP BY 1
ORDER BY 1, 2;
```

Mede entrada de contas novas. Limites: contas apagadas somem (cascata); oficina convertida de perfil continua contada no mês original e também gera uma conta nova (`ConverterPerfil.php:112-139`); não há canal.

### Q2. Ativação de contas frota em 14 dias

```sql
-- NÃO executada.
WITH contas_validas AS (SELECT id, created_at FROM contas WHERE tipo = 'frota'),
passos AS (
  SELECT c.id,
    EXISTS (SELECT 1 FROM veiculos_conta v WHERE v.conta_id = c.id
            AND julianday(v.created_at) - julianday(c.created_at) <= 14) AS tem_veiculo,
    EXISTS (SELECT 1 FROM motoristas m WHERE m.conta_id = c.id
            AND julianday(m.created_at) - julianday(c.created_at) <= 14) AS tem_motorista,
    EXISTS (SELECT 1 FROM usos_veiculo u WHERE u.conta_id = c.id AND u.origem = 'agenda'
            AND julianday(u.created_at) - julianday(c.created_at) <= 14) AS reservou,
    EXISTS (SELECT 1 FROM usos_veiculo u WHERE u.conta_id = c.id AND u.origem = 'agenda'
            AND u.saida_em IS NOT NULL
            AND julianday(u.saida_em) - julianday(c.created_at) <= 14) AS registrou_saida
  FROM contas_validas c
  WHERE julianday('now') - julianday(c.created_at) >= 14   -- só coortes completas
)
SELECT COUNT(*) AS contas,
       SUM(tem_veiculo) AS p1_veiculo,
       SUM(tem_veiculo AND tem_motorista) AS p2_motorista,
       SUM(tem_veiculo AND tem_motorista AND reservou) AS p3_reserva,
       SUM(tem_veiculo AND tem_motorista AND reservou AND registrou_saida) AS ativadas
FROM passos;
```

Mede o funil de ativação, devolvendo só totais. MySQL: trocar `julianday` por `TIMESTAMPDIFF(HOUR, c.created_at, x) <= 336` e `julianday('now')` por `NOW()`. Armadilhas: reserva agendada e depois apagada não conta (achado 5); veículo apagado também; uso de planilha está excluído de propósito.

### Q3. Contas frota ativas por semana (WAU de conta)

```sql
-- NÃO executada.
SELECT strftime('%Y-%W', datetime(e.created_at, '-3 hours')) AS semana,
       COUNT(DISTINCT e.conta_id) AS contas_ativas
FROM usos_veiculo_eventos e
JOIN contas c ON c.id = e.conta_id AND c.tipo = 'frota'
WHERE e.acao IN ('reservou', 'saiu', 'chegou')
GROUP BY semana
ORDER BY semana;
```

Mede contas que operaram a frota no app. Limites: a trilha começou com a fase 2 (`2026_10_25_000000`), então semanas anteriores não têm eventos; `corrigiu_km` grava `uso_veiculo_id` nulo (`UsoVeiculoController.php:538`), por isso não entra.

### Q4. Retenção por coorte mensal (semanas 4, 8 e 12)

```sql
-- NÃO executada.
WITH coorte AS (
  SELECT id, created_at, strftime('%Y-%m', datetime(created_at, '-3 hours')) AS mes
  FROM contas WHERE tipo = 'frota'
),
atividade AS (
  SELECT c.id, c.mes,
    CAST((julianday(e.created_at) - julianday(c.created_at)) / 7 AS INTEGER) + 1 AS semana_n
  FROM coorte c
  JOIN usos_veiculo_eventos e ON e.conta_id = c.id
  WHERE e.acao IN ('reservou', 'saiu', 'chegou')
)
SELECT c.mes,
       COUNT(DISTINCT c.id) AS contas,
       COUNT(DISTINCT CASE WHEN a.semana_n = 4  THEN a.id END) AS ativas_s4,
       COUNT(DISTINCT CASE WHEN a.semana_n = 8  THEN a.id END) AS ativas_s8,
       COUNT(DISTINCT CASE WHEN a.semana_n = 12 THEN a.id END) AS ativas_s12
FROM coorte c
LEFT JOIN atividade a ON a.id = c.id
GROUP BY c.mes
ORDER BY c.mes;
```

Limites: coortes recentes ainda não chegaram à semana 12 (mostrar só a partir de `created_at` + 12 semanas); contas apagadas somem (viés de sobrevivência). Para pessoa e estética, trocar a fonte de atividade pelas tabelas do perfil.

### Q5. Funil de acesso dos motoristas (distribuição entre contas)

```sql
-- NÃO executada.
WITH por_conta AS (
  SELECT m.conta_id,
    SUM(CASE WHEN u.id IS NULL THEN 1 ELSE 0 END) AS sem_acesso,
    SUM(CASE WHEN u.id IS NOT NULL AND u.ultimo_acesso_em IS NULL THEN 1 ELSE 0 END) AS convite_enviado,
    SUM(CASE WHEN u.ultimo_acesso_em IS NOT NULL THEN 1 ELSE 0 END) AS ativo
  FROM motoristas m
  LEFT JOIN users u ON u.id = m.user_id AND u.conta_id = m.conta_id   -- mesma regra de Motorista::loginDaConta
  WHERE m.ativo = 1
  GROUP BY m.conta_id
)
SELECT COUNT(*) AS contas,
       SUM(CASE WHEN ativo > 0 THEN 1 ELSE 0 END) AS contas_com_motorista_ativo,
       SUM(sem_acesso) AS total_sem_acesso,
       SUM(convite_enviado) AS total_convite_enviado,
       SUM(ativo) AS total_ativo
FROM por_conta;
```

Replica `Motorista::estadoDeAcesso` (`Motorista.php:68-90`). Limite: quem acessa a conta pelo `acessos` (MyCar extra) e não pelo `users.conta_id` cai como `sem_acesso` aqui e no app. `ultimo_acesso_em` preenchido quer dizer "entrou pelo menos uma vez", não "ativo agora".

### Q6. Quem registra a saída: o motorista ou o gestor

```sql
-- NÃO executada.
SELECT strftime('%Y-%m', datetime(u.saida_em, '-3 hours')) AS mes,
       COUNT(*) AS saidas,
       SUM(CASE WHEN m.user_id IS NOT NULL AND u.saida_por_user_id = m.user_id THEN 1 ELSE 0 END) AS pelo_motorista,
       SUM(CASE WHEN u.km_saida_origem = 'foto' THEN 1 ELSE 0 END) AS km_por_foto
FROM usos_veiculo u
LEFT JOIN motoristas m ON m.id = u.motorista_id AND m.conta_id = u.conta_id
WHERE u.origem = 'agenda' AND u.saida_em IS NOT NULL
GROUP BY mes;
```

Mede adoção do registro pelo próprio motorista e da foto do hodômetro. Limite: `saida_por_user_id` e `km_saida_origem` são nulos nos usos anteriores à fase 2 (`2026_10_25_000000...:22-25`); filtrar meses a partir dela.

### Q7. Qualidade do registro: cancelamentos, correções e conflitos

```sql
-- NÃO executada.
SELECT strftime('%Y-%m', datetime(e.created_at, '-3 hours')) AS mes,
       SUM(e.acao = 'reservou') AS reservas,
       SUM(e.acao IN ('cancelou', 'apagou')) AS canceladas_ou_apagadas,
       SUM(e.acao = 'chegou') AS chegadas,
       SUM(e.acao = 'conflito_por_devolucao') AS conflitos_devolucao,
       SUM(e.acao IN ('editou', 'corrigiu_km', 'trocou_centro', 'mudou_motivo')) AS correcoes
FROM usos_veiculo_eventos e
GROUP BY mes;
```

Mede atrito no fluxo (T1-T3 do gestor). Em MySQL, `SUM(cond)` funciona; em PostgreSQL usar `COUNT(*) FILTER (WHERE ...)`. Armadilha: `apagou` também desfaz importação de planilha (`UsoVeiculoController.php:296`), o que infla "apagadas" em meses de importação. Não abrir `dados` nem `motivo`.

### Q8. Chegadas esquecidas e reservas esquecidas (agora)

```sql
-- NÃO executada.
SELECT
  COUNT(DISTINCT CASE WHEN status = 'em_uso' AND julianday('now') - julianday(fim_previsto) > 1 THEN conta_id END) AS contas_com_chegada_esquecida,
  SUM(CASE WHEN status = 'em_uso' AND julianday('now') - julianday(fim_previsto) > 1 THEN 1 ELSE 0 END) AS usos_sem_chegada,
  SUM(CASE WHEN status = 'agendado' AND julianday('now') - julianday(inicio_previsto) > 1 THEN 1 ELSE 0 END) AS reservas_sem_saida
FROM usos_veiculo
WHERE origem = 'agenda';
```

Fotografia do momento, não série. Limite: a frota pode usar reserva longa de propósito; ler como sinal, não como erro.

### Q9. Checklist de saída feito

```sql
-- NÃO executada.
SELECT strftime('%Y-%m', datetime(u.saida_em, '-3 hours')) AS mes,
       COUNT(*) AS saidas,
       SUM(CASE WHEN ck.id IS NOT NULL THEN 1 ELSE 0 END) AS com_checklist,
       SUM(CASE WHEN ck.problemas > 0 THEN 1 ELSE 0 END) AS checklist_com_problema
FROM usos_veiculo u
LEFT JOIN checklists_uso ck ON ck.uso_veiculo_id = u.id AND ck.momento = 'saida' AND ck.conta_id = u.conta_id
WHERE u.origem = 'agenda' AND u.saida_em IS NOT NULL
GROUP BY mes;
```

Limite: checklists só existem desde `2026_11_02_130000`; uma conta pode não exigir checklist (comparar só contas com modelo ativo ou com algum checklist).

### Q10. Adoção de módulos por conta frota (distribuição)

```sql
-- NÃO executada.
WITH m AS (
  SELECT c.id,
    (SELECT COUNT(*) > 0 FROM despesas d WHERE d.conta_id = c.id) +
    (SELECT COUNT(*) > 0 FROM multas x WHERE x.conta_id = c.id) +
    (SELECT COUNT(*) > 0 FROM ocorrencias o WHERE o.conta_id = c.id) +
    (SELECT COUNT(*) > 0 FROM abastecimentos a WHERE a.conta_id = c.id) +
    (SELECT COUNT(*) > 0 FROM servicos_conta s WHERE s.conta_id = c.id) +
    (SELECT COUNT(*) > 0 FROM checklists_uso k WHERE k.conta_id = c.id) +
    (SELECT COUNT(*) > 0 FROM centros_custo cc WHERE cc.conta_id = c.id AND cc.ativo = 1) +
    (SELECT COUNT(*) > 0 FROM grupos_usuarios g WHERE g.conta_id = c.id) AS modulos
  FROM contas c WHERE c.tipo = 'frota'
)
SELECT modulos, COUNT(*) AS contas FROM m GROUP BY modulos ORDER BY modulos;
```

Devolve um histograma (quantas contas usam 0, 1, ... 8 módulos), sem identificar contas. Em MySQL, `COUNT(*) > 0` devolve 0/1 e soma igual; em PostgreSQL somar `CASE`. Categorias de despesa criadas por ocorrência (`Ocorrencia.php:41`) podem inflar "despesas".

### Q11. Planos e pressão de limite (receita possível)

```sql
-- NÃO executada.
SELECT COALESCE(c.plano, 'sem_limite') AS plano,
       COUNT(*) AS contas,
       SUM(CASE WHEN c.limite_veiculos IS NOT NULL AND v.qtd >= c.limite_veiculos THEN 1 ELSE 0 END) AS no_limite,
       SUM(CASE WHEN c.limite_veiculos IS NOT NULL AND v.qtd = c.limite_veiculos - 1 THEN 1 ELSE 0 END) AS a_um_do_limite
FROM contas c
LEFT JOIN (SELECT conta_id, COUNT(*) AS qtd FROM veiculos_conta GROUP BY conta_id) v ON v.conta_id = c.id
WHERE c.tipo = 'frota'
GROUP BY COALESCE(c.plano, 'sem_limite');
```

Limite: não há histórico de plano; "no limite" é o estado de hoje. As tentativas barradas não são gravadas (achado 8).

### Q12. Estética: atendimentos entregues no prazo

```sql
-- NÃO executada.
SELECT strftime('%Y-%m', datetime(entrada_em, '-3 hours')) AS mes,
       COUNT(DISTINCT conta_id) AS contas_ativas,
       COUNT(*) AS atendimentos,
       SUM(CASE WHEN etapa = 'entregue' THEN 1 ELSE 0 END) AS entregues,
       SUM(CASE WHEN etapa = 'entregue' AND previsao_entrega IS NOT NULL AND entregue_em <= previsao_entrega THEN 1 ELSE 0 END) AS no_prazo,
       SUM(CASE WHEN etapa = 'entregue' AND pago = 1 THEN 1 ELSE 0 END) AS entregues_pagos
FROM atendimentos_estetica
GROUP BY mes;
```

Limite: `AtendimentoEsteticaController.php` não usa `FusoDaFrota`, então confirmar em que fuso `entrada_em` e `previsao_entrega` chegam ao banco antes de subtrair 3 horas; não selecionar `placa`, `cliente_*` nem `valor`.

---

## 7. Lacunas e proposta mínima de instrumentação

### 7.1 O que não dá para medir hoje

| Lacuna | Por quê |
|---|---|
| Atividade histórica por usuário ou conta (DAU/WAU real) | `ultimo_acesso_em` só no login; `tokens_acesso` sem histórico (achado 1) |
| Tempo para registrar saída/chegada, tempo para reservar | Nenhuma marca de início do formulário |
| Abandono de formulário (cadastro, reserva, saída, importação) | Só o envio bem-sucedido grava algo |
| Erros de validação vistos pelo usuário (422), conflitos de reserva (409), limite de plano | Respondem e não gravam (achado 8) |
| Leitura de hodômetro por IA: tentativas, falhas, aceitação | `LeituraHodometroController.php` não persiste; `km_*_origem` só distingue `foto`/`manual` |
| Uso offline | O frontend não tem modo offline (achado 10) |
| Cliques, telas vistas, navegação, busca | Sem instrumentação de frontend |
| Canal de aquisição, indicação | Sem UTM, origem ou programa de indicação |
| Satisfação (nota, NPS) | Só sugestões em texto livre |
| Abertura e clique dos e-mails de lembrete | Só se sabe que foi enviado (`alertado_em`) |
| Receita e churn de pagamento | Sem cobrança no código (achado 9) |
| Primeiro login do motorista convidado (data) | Só o "já entrou" (`ultimo_acesso_em` vira a data do último login) |

### 7.2 Proposta mínima (sem ferramenta de terceiros)

**Uma tabela de contadores diários agregados**, gravada pelo backend:

```
metricas_produto_diarias
  dia          date          -- dia de Brasília
  conta_id     bigint null   -- conta (pessoa, frota, estética)
  oficina_id   bigint null   -- ou oficina; exatamente um dos dois preenchido
  perfil       varchar(10)   -- oficina | pessoa | frota | estetica
  papel        varchar(15) null -- administrador | gestor | motorista (só frota)
  tipo         varchar(40)   -- lista fechada no código (allowlist)
  faixa        varchar(10) null -- só para durações: '<30s', '30-60s', '1-3min', '>3min'
  quantidade   int
  unique (dia, conta_id, oficina_id, papel, tipo, faixa)
```

- **Sem** `user_id`, IP, user agent, URL, texto livre, valor digitado ou identificador de registro. Cada evento só incrementa um contador (upsert).
- **Tipos iniciais** (poucos e úteis): `sessao_usada` (substitui o `ultimo_acesso_em` como sinal diário), `reserva_rejeitada_conflito`, `veiculo_rejeitado_limite`, `validacao_falhou:<formulario>`, `hodometro_ia_lido`, `hodometro_ia_falhou`, `hodometro_ia_aceito_sem_edicao`, `saida_iniciada`, `saida_concluida`, `saida_duracao` (com `faixa`).
- **Do frontend**: um endpoint autenticado que aceita só tipos da allowlist, com `throttle`, e ignora qualquer campo extra. Duração calculada no navegador e enviada já em faixa.
- **Retenção**: proposta de 13 meses e depois apagar (decisão do dono).

Riscos e cuidados:

- **Reidentificação**: conta com uma pessoa só (todo perfil Pessoa) torna o contador por conta um dado pessoal. Mitigação: o painel mostra apenas totais e distribuições entre contas, com corte mínimo (ex.: não exibir recorte com menos de 5 contas).
- **Papel + conta pequena** pode identificar o único motorista; mesma regra de corte.
- **Desvio de finalidade**: proibir uso dos contadores para avaliar pessoas ou vender dados. Registrar a finalidade.
- **Isolamento**: a gravação usa `app('conta.atual')` (ou a oficina atual), nunca um id vindo do cliente.

Custo:

- Uma migration, um serviço de incremento, um endpoint e chamadas pontuais nos controllers e telas. Volume pequeno: no máximo (contas × tipos × faixas) linhas por dia. Sem custo de terceiros.

Quem decide: o dono (finalidade, base legal, retenção, lista de tipos, quem vê). Implementação só depois dessas decisões, em PR separado.

---

## 8. Painel no `/admin`

### O que já há

Resumo (`AdminController.php:30-53`) e lista de contas (`:55-149`), mostrados em `mycar_frontend/src/pages/Admin/Admin.tsx` via `mycar_frontend/src/services/admin.ts:143-145`.

### O que acrescentar (só números agregados)

1. **Corrigir o resumo**: incluir `contas.estetica` (achado 3); separar "contas novas" (`contas.created_at` + `oficinas.created_at`) de "usuários novos", e "convidados" à parte.
2. **Ativos com fonte melhor**: "contas com sessão usada nos últimos 7 dias" a partir de `tokens_acesso.ultimo_uso_em` (estado atual), rotulado assim, e depois pelo contador `sessao_usada`.
3. **Bloco Funil (frota)**: totais da Q2 dos últimos 90 dias (contas, com veículo, com motorista, com reserva, ativadas).
4. **Bloco Retenção**: tabela da Q4 com coortes de pelo menos 5 contas; células menores aparecem como "< 5".
5. **Bloco Uso semanal**: série da Q3 por perfil.
6. **Bloco Qualidade**: proporções da Q7 e Q9 no mês.
7. **Bloco Planos**: Q11.

Regras do painel:

- Um endpoint novo (ex.: `GET /admin/metricas`) com `DB::table`, como o resumo atual, atrás de `super.admin`, devolvendo **apenas números**: sem nome de conta, e-mail, placa ou id.
- Nada do painel de métricas passa pelo `AdminConteudoController`.
- Cache curto (as consultas varrem tabelas inteiras); recalcular no máximo de hora em hora.

---

## 9. Metas de exemplo (hipóteses a validar)

Todas são **hipóteses a validar** com a primeira leitura dos dados. Nenhum número vem de referência de mercado. A primeira tarefa é medir o ponto de partida; só então fixar a meta.

| Métrica | Hipótese (a validar) |
|---|---|
| Ativação frota em 14 dias (Q2) | Medir a linha de base no primeiro mês; meta inicial: subir a proporção mês a mês, com o maior passo perdido identificado |
| Retenção semana 4 (Q4) | Meta é não cair entre coortes seguidas enquanto o produto muda |
| Saídas registradas pelo próprio motorista (Q6) | Tendência de alta nas contas com motorista `ativo` |
| km por foto (Q6) | Tendência de alta depois da leitura por IA; acompanhar junto com `corrigiu_km` |
| Correções por 100 usos (Q7) | Tendência de queda |
| Chegadas esquecidas (Q8) | Tendência de queda |
| Checklist de saída feito (Q9) | Alta nas contas com modelo ativo |

---

## 10. Decisões que dependem do dono

1. **Base legal e finalidade** das métricas (legítimo interesse com avaliação, ou outra) e o texto na política de privacidade.
2. **Se cria a tabela de contadores** da seção 7.2, com quais tipos, e se o frontend envia eventos.
3. **Retenção** dos contadores (proposta: 13 meses) e das trilhas existentes (`usos_veiculo_eventos`, `acessos_frota_eventos` hoje não têm prazo).
4. **Quem vê o quê**: só o dono no `/admin`? A própria conta vê os seus números? Corte mínimo de 5 contas por recorte?
5. **Como marcar contas de teste** (lista manual ou coluna nova).
6. **Definição oficial de "ativou"** por perfil e a janela (14 dias?).
7. **Definição de "conta ativa"**: evento de uso real (recomendado) ou sessão aberta.
8. **O que é meta**: quais 3 a 5 métricas viram objetivo do trimestre, depois da linha de base.
9. **Banco de produção** (SQLite, MySQL ou PostgreSQL) para ajustar as consultas.
10. **Cobrança**: se e quando entra; sem isso, a parte "Receita" do funil fica só em planos e limite.
11. **Indicação**: se vale criar um mecanismo (hoje não há nada).
