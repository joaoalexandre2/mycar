# Backup e restauração do banco de produção

Produção roda em MySQL no VPS. Este documento vale para qualquer alteração de dados ou de schema em produção.

## Quando fazer backup

- Antes de qualquer correção de dados (um comando que grava, como `frota:corrigir-fuso-antigo --executar`).
- Antes de uma migration que altera ou remove colunas e tabelas.
- Antes de restaurar qualquer coisa.

## Como fazer

O dump é criado **dentro do servidor** e copiado com `scp`. Não use `>` no PowerShell para gravar o dump: ele regrava o binário como UTF-16 e o arquivo fica inválido.

```bash
ssh -i $HOME\.ssh\oracle_mycar ubuntu@136.248.114.50 'cd /var/www/mycar && set -a && . ./.env && set +a && mysqldump --no-tablespaces -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > /home/ubuntu/backup-AAAA-MM-DD.sql.gz'
```

```bash
scp -i $HOME\.ssh\oracle_mycar ubuntu@136.248.114.50:/home/ubuntu/backup-AAAA-MM-DD.sql.gz "$HOME\backup-AAAA-MM-DD.sql.gz"
```

Depois da cópia:

1. Confira que o arquivo é um gzip válido: `gzip -t backup-AAAA-MM-DD.sql.gz`.
2. Confira que as tabelas estão lá: `gzip -dc backup-AAAA-MM-DD.sql.gz | grep "CREATE TABLE"`.
3. Apague a cópia do servidor: `rm /home/ubuntu/backup-AAAA-MM-DD.sql.gz`. O dump tem dados de clientes.

`--no-tablespaces` é obrigatório: o usuário do banco não tem o privilégio PROCESS, e sem a opção o dump falha.

## Como restaurar

**Nunca restaure direto em produção.** Restaure primeiro num banco de teste e confira.

```bash
gunzip -c backup-AAAA-MM-DD.sql.gz | mysql -h HOST -u USUARIO -p BANCO_DE_TESTE
```

Depois da restauração de teste, confira:

- contagem de linhas nas tabelas principais (`usos_veiculo`, `multas`, `abastecimentos`, `veiculos_conta`);
- uma consulta conhecida (por exemplo, as reservas corrigidas pelo fuso);
- que a aplicação sobe apontando para o banco de teste.

Só então decida se a restauração em produção é necessária.

## Estado atual

- Backup de 2026-11-02 feito e validado (gzip íntegro, tabelas da frota presentes).
- **Teste de restauração ainda não feito.** Depende de um MySQL local (Docker Desktop) ou de um banco de homologação. Enquanto isso, o procedimento acima não está provado.
