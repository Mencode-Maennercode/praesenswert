#!/usr/bin/env bash
# curl-Tests gegen lokalen PHP-Server.
#   PHP_BIN=~/php/php.exe bash _test/run.sh
# Braucht für die Cache-Tests einen Mistral-Schlüssel in MISTRAL_API_KEY.
set -u
cd "$(dirname "$0")/.."
API_DIR="$(pwd -W 2>/dev/null || pwd)"
PHP="${PHP_BIN:-$HOME/php/php.exe}"
PHPDIR="$(dirname "$PHP")"
export OPENSSL_CONF="${OPENSSL_CONF:-$PHPDIR/extras/ssl/openssl.cnf}"

eval "$("$PHP" _test/mint.php)"
export LERNWELT_TEST_CERTS="$CERTS" LERNWELT_TEST_ADMINS="test-admin"

PASS=0; FAILN=0
check() { # name erwartet ist
  if [ "$2" = "$3" ]; then PASS=$((PASS+1)); echo "ok   $1 ($3)"; else FAILN=$((FAILN+1)); echo "FAIL $1: erwartet $2, ist $3"; fi
}
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

"$PHP" -S 127.0.0.1:8099 -t "$API_DIR" >/dev/null 2>&1 &
S1=$!
LERNWELT_USER_MAX=0 "$PHP" -S 127.0.0.1:8098 -t "$API_DIR" >/dev/null 2>&1 &
S2=$!
trap 'kill $S1 $S2 2>/dev/null' EXIT
for i in $(seq 1 60); do curl -s -o /dev/null http://127.0.0.1:8099/ && curl -s -o /dev/null http://127.0.0.1:8098/ && break; sleep 1; done

U=http://127.0.0.1:8099
EX='{"subject":"mathe","grade":2,"skillId":"mathe.zehneruebergang_add","misconception":"mathe.zehner_vergessen","task":"8 + 5","answer":"3","correct":"13"}'
J=(-H 'Content-Type: application/json')

# --- Token
check "ohne Token → 401"       401 "$(code -X POST "${J[@]}" -d "$EX" $U/explain.php)"
check "Müll-Token → 401"       401 "$(code -X POST "${J[@]}" -H 'Authorization: Bearer a.b.c' -d "$EX" $U/explain.php)"
check "abgelaufen → 401"       401 "$(code -X POST "${J[@]}" -H "Authorization: Bearer $TOK_EXPIRED" -d "$EX" $U/explain.php)"
check "falsche aud → 401"      401 "$(code -X POST "${J[@]}" -H "Authorization: Bearer $TOK_AUD" -d "$EX" $U/explain.php)"
check "falsche Signatur → 401" 401 "$(code -X POST "${J[@]}" -H "Authorization: Bearer $TOK_SIG" -d "$EX" $U/explain.php)"
check "echtes Google-kid fehlt → 401" 401 "$(code -X POST "${J[@]}" -H "Authorization: Bearer $(echo "$TOK_OK" | sed 's/^[^.]*/eyJhbGciOiJSUzI1NiIsImtpZCI6Im5vcGUifQ/')" -d "$EX" $U/explain.php)"

# --- CORS / Methode / Sperren
check "fremde Origin → 403"    403 "$(code -X POST -H 'Origin: https://evil.example' -d '{}' $U/explain.php)"
check "Preflight App → 204"    204 "$(code -X OPTIONS -H 'Origin: https://lernwelt-app.web.app' $U/explain.php)"
check "GET → 405"              405 "$(code $U/explain.php)"
check "Eingabe ungültig → 400" 400 "$(code -X POST "${J[@]}" -H "Authorization: Bearer $TOK_OK" -d '{"grade":9}' $U/explain.php)"
check "Nicht-Admin → 403"      403 "$(code -X POST "${J[@]}" -H "Authorization: Bearer $TOK_OK" -H 'X-Admin-Key: irgendwas-langes' -d '{}' $U/admin-ingest.php)"
ACAO=$(curl -s -D - -o /dev/null -X OPTIONS -H 'Origin: http://localhost:5173' $U/tts.php | tr -d '\r' | grep -i '^access-control-allow-origin' | cut -d' ' -f2)
check "CORS localhost erlaubt" "http://localhost:5173" "$ACAO"

# --- Budget → 429 mit retryAfter (Server 2: Tageskontingent 0)
R=$(curl -s -w '|%{http_code}' -X POST "${J[@]}" -H "Authorization: Bearer $TOK_OK" -d "${EX/8 + 5/7 + 6}" http://127.0.0.1:8098/explain.php)
check "Kontingent leer → 429" 429 "${R##*|}"
echo "$R" | grep -q '"retryAfter":[1-9]' && check "429 hat retryAfter" ja ja || check "429 hat retryAfter" ja nein

# --- Cache-Treffer (echter KI-Aufruf beim ersten Mal)
if [ -n "${MISTRAL_API_KEY:-}" ]; then
  H1=$(curl -s -D - -o _test/tmp/e1.json -X POST "${J[@]}" -H "Authorization: Bearer $TOK_OK" -d "$EX" $U/explain.php | tr -d '\r')
  H2=$(curl -s -D - -o _test/tmp/e2.json -X POST "${J[@]}" -H "Authorization: Bearer $TOK_OK" -d "$EX" $U/explain.php | tr -d '\r')
  echo "  explain: $(head -c 300 _test/tmp/e1.json)"
  check "explain 1. Aufruf 200" 200 "$(echo "$H1" | head -1 | cut -d' ' -f2)"
  check "explain 2. Aufruf Cache" "X-Cache: HIT" "$(echo "$H2" | grep -i '^x-cache')"
  # 2. Aufruf auch mit Kontingent 0 aus dem Cache (Cache zählt nicht)
  check "Cache trotz Kontingent 0" 200 "$(code -X POST "${J[@]}" -H "Authorization: Bearer $TOK_OK" -d "$EX" http://127.0.0.1:8098/explain.php)"

  TTS='{"text":"Super gemacht! Acht plus f\u00fcnf ist dreizehn.","style":"jubeln"}'
  T1=$(curl -s -D - -o _test/tmp/t1.bin -X POST "${J[@]}" -H "Authorization: Bearer $TOK_OK" -d "$TTS" $U/tts.php | tr -d '\r')
  echo "  tts: $(echo "$T1" | head -1) $(echo "$T1" | grep -i '^x-model') $(head -c 160 _test/tmp/t1.bin | tr -c '[:print:]' '.')"
  if echo "$T1" | grep -qi '^content-type: audio/'; then
    T2=$(curl -s -D - -o _test/tmp/t2.bin -X POST "${J[@]}" -H "Authorization: Bearer $TOK_OK" -d "$TTS" $U/tts.php | tr -d '\r')
    check "tts 2. Aufruf Cache" "X-Cache: HIT" "$(echo "$T2" | grep -i '^x-cache')"
    check "tts gleiche Datei" "$(md5sum < _test/tmp/t1.bin)" "$(md5sum < _test/tmp/t2.bin)"
  else
    # Ohne TTS im Tarif: sauberer Fehler im Vertragsformat, Client nimmt speechSynthesis.
    S=$(echo "$T1" | head -1 | cut -d' ' -f2)
    case "$S" in 429|502|503) check "tts ohne Tarif: sauberer Fehler" ja ja ;; *) check "tts ohne Tarif: sauberer Fehler" "429/502/503" "$S" ;; esac
  fi
else
  echo "(MISTRAL_API_KEY fehlt – Cache-Tests übersprungen)"
fi

echo "---- $PASS bestanden, $FAILN fehlgeschlagen"
[ "$FAILN" = 0 ]
