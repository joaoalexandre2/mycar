#!/usr/bin/env bash
# Checagem somente de leitura depois de um deploy. Não altera nada no servidor.
#   bash scripts/verificar-producao.sh
# Variáveis opcionais:
#   SITE=https://mycar.joaokirst.com.br   API=https://api.mycar.joaokirst.com.br/api
#   ESPERADO_BUNDLE=index-XXXX.js        (o nome do bundle que o build novo gerou)
set -uo pipefail

SITE="${SITE:-https://mycar.joaokirst.com.br}"
API="${API:-https://api.mycar.joaokirst.com.br/api}"
falhas=0

ok() { echo "OK    $1"; }
falha() { echo "FALHA $1"; falhas=$((falhas + 1)); }

codigo() { curl -s -o /dev/null -w "%{http_code}" --max-time 20 "$1"; }

# 1. Site responde e entrega o bundle esperado.
if [ "$(codigo "$SITE/")" = "200" ]; then ok "site responde 200"; else falha "site não responde 200"; fi

bundle="$(curl -s --max-time 20 "$SITE/" | grep -o 'assets/index-[^"]*\.js' | head -1)"
if [ -n "$bundle" ]; then ok "bundle atual: $bundle"; else falha "não achei o bundle no index.html"; fi

if [ -n "${ESPERADO_BUNDLE:-}" ]; then
  if [[ "$bundle" == *"$ESPERADO_BUNDLE"* ]]; then ok "bundle é o esperado"; else falha "bundle diferente do esperado ($ESPERADO_BUNDLE)"; fi
fi

# 2. Rotas protegidas exigem login (401 sem token). Se não exigir, o middleware foi removido.
for rota in /conta/pessoas /conta/acessos-frota /conta/grupos-usuarios; do
  c="$(codigo "$API$rota")"
  if [ "$c" = "401" ]; then ok "$rota exige login (401)"; else falha "$rota respondeu $c, esperado 401"; fi
done

# 3. Login existe (POST sem corpo tem de responder 422 de validação, não 404).
c="$(curl -s -o /dev/null -w "%{http_code}" --max-time 20 -X POST -H 'Accept: application/json' "$API/login")"
if [ "$c" = "422" ]; then ok "login responde (422 sem corpo)"; else falha "login respondeu $c, esperado 422"; fi

echo
if [ "$falhas" -eq 0 ]; then
  echo "Tudo certo."
else
  echo "$falhas checagem(ns) falharam."
  exit 1
fi
