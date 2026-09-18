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
  for url in "$SITE_URL/" "$SITE_URL/soutenir/"; do
    code="$(curl -LsS --max-time 20 -o /dev/null -w '%{http_code}' "$url" || true)"
    [[ "$code" == "200" ]] || fail "$url répond HTTP $code."
  done
  log "Contrôle HTTP: OK"
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
if [[ -f "$STATE" ]] && grep -Fqx "$COMMIT" "$STATE"; then
  check_http
  log "Aucun nouveau commit."
  exit 0
fi

STAGE="$(mktemp -d "$HOME/.cache/vevak-stage.XXXXXX")"
trap 'rm -rf "$STAGE"' EXIT

for path in index.html .nojekyll robots.txt sitemap.xml assets en soutenir test; do
  [[ -e "$REPO/$path" ]] || continue
  rsync -a "$REPO/$path" "$STAGE/"
done

STAMP="$(date '+%Y%m%d-%H%M%S')"
BACKUP_DIR="$BACKUP_ROOT/$STAMP"
mkdir -p "$BACKUP_DIR"

log "Déploiement sécurisé vers $LIVE..."
rsync -a --delete-delay   --backup --backup-dir="$BACKUP_DIR"   --exclude='.htaccess'   --exclude='.htpasswd'   --exclude='.well-known/'   --exclude='api/'   --exclude='cgi-bin/'   --exclude='test/files/'   "$STAGE/" "$LIVE/"

check_http

printf '%s\n' "$COMMIT" > "$STATE.tmp"
mv "$STATE.tmp" "$STATE"
chmod 600 "$STATE"

rmdir "$BACKUP_DIR" 2>/dev/null || true

log "VeVak déployé: ${COMMIT:0:12}"
