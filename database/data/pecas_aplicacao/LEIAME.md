# Códigos de peça por modelo, ano e motor

Cada arquivo `{modelo}.json` guarda os códigos de fabricante de **um modelo do
catálogo** (`config/pecas_catalogo.php`). O app só **lê** estes arquivos; eles são
gerados a partir de planilhas de catálogos de aplicação dos fabricantes.

**Regra:** nenhum código é digitado de memória. Todo código vem de uma planilha
ou catálogo com fonte conhecida, gravada em cada linha (`fonte`).

## Como importar

1. Peça ao fabricante (Cofap, Tecfil, Fram, Nakata, Urba...) o catálogo de
   aplicação em Excel ou PDF.
2. Monte a planilha com as colunas de `_planilha-modelo.csv` (separador `;` ou `,`):

   | coluna | obrigatória | exemplo |
   |---|---|---|
   | modelo | sim | `Volkswagen Gol` (nome do catálogo) |
   | ano_de | não | `2008` |
   | ano_ate | não | `2012` |
   | motor | não | `1.0` |
   | peca | sim | `Filtro de óleo` (nome ou id do catálogo) |
   | fabricante | sim | `Tecfil` |
   | codigo | sim | o código do fabricante |
   | observacao | não | `dianteiro` |

   Sem `ano_de`/`ano_ate`, o código vale para qualquer ano; sem `motor`, para
   qualquer motor.
3. Confira sem gravar:

   ```
   php artisan pecas:importar planilha.csv --fonte="Catálogo Tecfil 2025" --simular
   ```
4. Importe de verdade (tudo ou nada: com qualquer linha inválida, nada é gravado):

   ```
   php artisan pecas:importar planilha.csv --fonte="Catálogo Tecfil 2025"
   ```
5. Veja o que falta: `php artisan pecas:cobertura` (ou `--sem-codigos`).
6. Revise o diff dos JSONs e faça o commit.

Importar a mesma planilha duas vezes não duplica códigos.
