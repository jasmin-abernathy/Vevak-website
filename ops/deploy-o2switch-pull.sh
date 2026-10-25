#!/usr/bin/env bash
set -Eeuo pipefail

HOME="${HOME:-/home/sc1leja3715}"
REPO="${VEVAK_REPO:-$HOME/repositories/Vevak-website}"
LIVE="${VEVAK_LIVE:-$HOME/public_html/VeVak}"
SITE_URL="${VEVAK_SITE_URL:-https://vevak.lepotager.org}"
LOG="${VEVAK_DEPLOY_LOG:-$HOME/logs/vevak-deploy.log}"
LOCK="${VEVAK_DEPLOY_LOCK:-$HOME/.cache/vevak-deploy.lock}"
STATE="${VEVAK_DEPLOY_STATE:-$HOME/.cache/vevak-deployed.state}"
BACKUP_ROOT="${VEVAK_BACKUP_ROOT:-$HOME/backups/vevak-site}"

export HOME
export PATH="/usr/local/bin:/usr/bin:/bin:${PATH:-}"

mkdir -p "$(dirname "$LOG")" "$(dirname "$LOCK")" "$(dirname "$STATE")" "$BACKUP_ROOT"
touch "$LOG"
chmod 600 "$LOG"

exec 9>"$LOCK"
if ! flock -n 9; then
  exit 0
fi

log() {
  printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" | tee -a "$LOG"
}

fail() {
  log "ERREUR: $*"
  exit 1
}

check_http() {
  command -v curl >/dev/null 2>&1 || return 0
  for url in "$SITE_URL/" "$SITE_URL/soutenir/" "$SITE_URL/retours/" "$SITE_URL/presse/"; do
    code="$(curl -LsS --max-time 20 -o /dev/null -w '%{http_code}' "$url" || true)"
    [[ "$code" == "200" ]] || fail "$url répond HTTP $code."
  done
  log "Contrôle HTTP: OK"
}

check_press_pdf() {
  # Vérifier la copie locale ET le PDF effectivement servi, pas seulement un HTTP 200.
  local filename="vevak-communique-presse-2026-10-11-v2.pdf"
  local expected="$REPO/assets/$filename"
  local published="$LIVE/assets/$filename"
  [[ -s "$expected" ]] || fail "PDF source absent : $expected"
  [[ -s "$published" ]] || fail "PDF public absent : $published"
  cmp -s "$expected" "$published" || fail "Le PDF sur le DocumentRoot diffère du PDF GitHub ($filename)."
  if command -v curl >/dev/null 2>&1; then
    local tmp
    tmp="$(mktemp "$HOME/.cache/vevak-pdf-XXXXXX")"
    if ! curl -fsSL --max-time 30 -o "$tmp" "$SITE_URL/assets/$filename"; then
      rm -f "$tmp"
      fail "Impossible de lire le PDF public : $SITE_URL/assets/$filename"
    fi
    if ! cmp -s "$expected" "$tmp"; then
      rm -f "$tmp"
      fail "PDF public différent de GitHub : possible cache HTTP, réécriture ou mauvais DocumentRoot ($filename)."
    fi
    rm -f "$tmp"
  fi
  log "Contrôle PDF public : mêmes octets que le dépôt."
}

[[ -d "$REPO/.git" ]] || fail "Dépôt Git introuvable: $REPO"
[[ -d "$LIVE" ]] || fail "DocumentRoot introuvable: $LIVE"
[[ "$LIVE" == "$HOME/public_html/VeVak" ]] || fail "Destination inattendue: $LIVE"

if [[ -n "$(git -C "$REPO" status --porcelain --untracked-files=no)" ]]; then
  fail "Le clone contient des modifications locales suivies."
fi

log "Vérification de origin/main..."
git -C "$REPO" fetch --prune origin main

LOCAL="$(git -C "$REPO" rev-parse HEAD)"
REMOTE="$(git -C "$REPO" rev-parse origin/main)"

if [[ "$LOCAL" != "$REMOTE" ]]; then
  if ! git -C "$REPO" merge-base --is-ancestor "$LOCAL" "$REMOTE"; then
    fail "La branche locale ne peut pas avancer en fast-forward vers origin/main."
  fi
  log "Mise à jour du clone: ${LOCAL:0:12} -> ${REMOTE:0:12}"
  git -C "$REPO" merge --ff-only origin/main
fi

COMMIT="$(git -C "$REPO" rev-parse HEAD)"

log "Contrôles locaux avant déploiement..."
for php_file in \
  "$REPO/assets/tester-storage.php" \
  "$REPO/assets/tester-submit.php" \
  "$REPO/assets/tester-receipts.php" \
  "$REPO/test/auth.php" \
  "$REPO/test/login.php" \
  "$REPO/test/index.php" \
  "$REPO/test/admin-testers.php" \
  "$REPO/ops/test-admin-auth.php" \
  "$REPO/ops/test-tester-receipts.php" \
  "$REPO/retours/index.php"; do
  [[ -f "$php_file" ]] || fail "Fichier PHP attendu introuvable: $php_file"
  php -l "$php_file" >/dev/null || fail "Syntaxe PHP invalide: $php_file"
done

if command -v node >/dev/null 2>&1; then
  node --check "$REPO/assets/tester-form.js" >/dev/null || fail "Syntaxe JS invalide: assets/tester-form.js"
  node --check "$REPO/retours/retours.js" >/dev/null || fail "Syntaxe JS invalide: retours/retours.js"
fi

SELFTEST_DIR="$(mktemp -d "$HOME/.cache/vevak-feedback-selftest.XXXXXX")"
chmod 700 "$SELFTEST_DIR"
if ! VEVAK_TESTERS_STORAGE_DIR="$SELFTEST_DIR" php "$REPO/ops/test-feedback-code.php"; then
  rm -rf "$SELFTEST_DIR"
  fail "Auto-test du portail testeurs en échec."
fi
rm -rf "$SELFTEST_DIR"

AUTH_SELFTEST_DIR="$(mktemp -d "$HOME/.cache/vevak-admin-selftest.XXXXXX")"
chmod 700 "$AUTH_SELFTEST_DIR"
if ! VEVAK_TESTERS_STORAGE_DIR="$AUTH_SELFTEST_DIR" php "$REPO/ops/test-admin-auth.php"; then
  rm -rf "$AUTH_SELFTEST_DIR"
  fail "Auto-test de la double authentification administrateur en échec."
fi
rm -rf "$AUTH_SELFTEST_DIR"

RECEIPT_SELFTEST_DIR="$(mktemp -d "$HOME/.cache/vevak-receipt-selftest.XXXXXX")"
chmod 700 "$RECEIPT_SELFTEST_DIR"
if ! VEVAK_TESTERS_STORAGE_DIR="$RECEIPT_SELFTEST_DIR" php "$REPO/ops/test-tester-receipts.php"; then
  rm -rf "$RECEIPT_SELFTEST_DIR"
  fail "Auto-test des confirmations d'inscription en échec."
fi
rm -rf "$RECEIPT_SELFTEST_DIR"
log "Contrôles locaux: OK"

if [[ -f "$STATE" ]] && grep -Fqx "$COMMIT" "$STATE"; then
  check_http
  check_press_pdf
  log "Aucun nouveau commit."
  exit 0
fi

STAGE="$(mktemp -d "$HOME/.cache/vevak-stage.XXXXXX")"
chmod 755 "$STAGE"
trap 'rm -rf "$STAGE"' EXIT

for path in index.html .nojekyll robots.txt sitemap.xml assets en confidentialite soutenir test retours presse; do
  [[ -e "$REPO/$path" ]] || continue
  rsync -a "$REPO/$path" "$STAGE/"
done

STAMP="$(date '+%Y%m%d-%H%M%S')"
BACKUP_DIR="$BACKUP_ROOT/$STAMP"
mkdir -p "$BACKUP_DIR"

log "Déploiement sécurisé vers $LIVE..."
rsync -a --delete-delay   --backup --backup-dir="$BACKUP_DIR"   --exclude='.htaccess'   --exclude='.htpasswd'   --exclude='.well-known/'   --exclude='api/'   --exclude='cgi-bin/'   --exclude='test/files/'   "$STAGE/" "$LIVE/"

check_http
check_press_pdf

printf '%s\n' "$COMMIT" > "$STATE.tmp"
mv "$STATE.tmp" "$STATE"
chmod 600 "$STATE"

rmdir "$BACKUP_DIR" 2>/dev/null || true

log "VeVak déployé: ${COMMIT:0:12}"
