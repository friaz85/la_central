#!/usr/bin/env bash
# =================================================================
# deploy.sh — La Central "Clásicos de la Fe"
# Deploy al servidor SiteGround vía rsync + SSH
# Usage: ./deploy.sh [full|api|admin]
# =================================================================

set -euo pipefail

# ── Configuración ─────────────────────────────────────────────────
SSH_KEY="$HOME/.ssh/id_rsa_siteground"
SSH_HOST="ssh.qrewards.com.mx"
SSH_PORT="18765"
SSH_USER="u6-pu9mvhpmgwh1"
REMOTE_BASE="/home/customer/www/clasicoslafe.qrewards.com.mx/public_html"
LOCAL_BACKEND="$(dirname "$0")/backend"
LOCAL_FRONTEND="$(dirname "$0")/frontend"

SSH_CMD="ssh -p $SSH_PORT -i $SSH_KEY -o StrictHostKeyChecking=no"
RSYNC_OPTS="-az --delete --progress -e \"ssh -p $SSH_PORT -i $SSH_KEY -o StrictHostKeyChecking=no\""

MODE="${1:-full}"

log()  { echo -e "\033[1;34m[DEPLOY]\033[0m $1"; }
ok()   { echo -e "\033[1;32m[  OK  ]\033[0m $1"; }
warn() { echo -e "\033[1;33m[ WARN ]\033[0m $1"; }
err()  { echo -e "\033[1;31m[ ERR  ]\033[0m $1"; exit 1; }

# ── Git commit + push ──────────────────────────────────────────────
git_sync() {
  local ROOT
  ROOT="$(cd "$(dirname "$0")" && pwd)"
  cd "$ROOT"

  if [ -z "$(git status --porcelain)" ]; then
    ok "Git: sin cambios pendientes, nada que commitear."
    return 0
  fi

  local MSG="deploy($MODE): $(date '+%Y-%m-%d %H:%M') — cambios auto-commiteados por deploy.sh"
  log "Git: commiteando y pusheando..."
  git add -A
  git commit -m "$MSG" || true
  git push origin HEAD || warn "Git push falló — el deploy continúa."
  ok "Git: push completado."
}

# ── Test SSH ───────────────────────────────────────────────────────
log "Verificando conexión SSH con SiteGround..."
$SSH_CMD "$SSH_USER@$SSH_HOST" "echo '✅ Conexión SSH Exitosa'" || err "No se pudo conectar por SSH a SiteGround"

# ── Deploy Backend (PHP) ───────────────────────────────────────────
deploy_backend() {
  log "Desplegando backend PHP..."
  
  # Asegurar que existan los directorios en el servidor
  $SSH_CMD "$SSH_USER@$SSH_HOST" "
    mkdir -p $REMOTE_BASE/backend/uploads
    chmod -R 775 $REMOTE_BASE/backend/uploads 2>/dev/null || true
  "

  # Sincronizar backend excluyendo uploads locales y logs locales
  rsync -az --delete --progress \
    --exclude='uploads/' \
    --exclude='*.log' \
    --exclude='.DS_Store' \
    -e "ssh -p $SSH_PORT -i $SSH_KEY -o StrictHostKeyChecking=no" \
    "$LOCAL_BACKEND/" \
    "$SSH_USER@$SSH_HOST:$REMOTE_BASE/backend/"

  ok "Backend desplegado en $REMOTE_BASE/backend/"
}

# ── Deploy Frontend (Angular 18) ───────────────────────────────────
deploy_frontend() {
  log "Compilando Angular Frontend (npm run build)..."
  cd "$LOCAL_FRONTEND"
  npm run build
  cd - > /dev/null

  log "Sincronizando Frontend (Angular dist) a la raíz del servidor..."
  
  # Subir los archivos generados del build de Angular
  rsync -az --delete --progress \
    --exclude='backend/' \
    -e "ssh -p $SSH_PORT -i $SSH_KEY -o StrictHostKeyChecking=no" \
    "$LOCAL_FRONTEND/dist/frontend/browser/" \
    "$SSH_USER@$SSH_HOST:$REMOTE_BASE/"

  # Crear/subir .htaccess para habilitar enrutador de Angular (HTML5 routing)
  log "Creando .htaccess para redirecciones de Angular..."
  $SSH_CMD "$SSH_USER@$SSH_HOST" "cat > $REMOTE_BASE/.htaccess << 'HTEOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Permitir que las llamadas al backend pasen de largo
    RewriteRule ^backend/ - [L]

    # Si es un archivo o directorio real, servirlo directamente
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d

    # De lo contrario, redirigir al index.html para que Angular maneje la ruta
    RewriteRule . index.html [L]
</IfModule>
HTEOF"

  ok "Frontend Angular desplegado exitosamente."
}

# ── Main ───────────────────────────────────────────────────────────
echo ""
echo "╔════════════════════════════════════════╗"
echo "║      La Central — Deploy Script        ║"
echo "╚════════════════════════════════════════╝"
echo ""
log "Modo: $MODE"
echo ""

case $MODE in
  full)
    deploy_backend
    deploy_frontend
    ;;
  api|backend)
    deploy_backend
    ;;
  admin|frontend)
    deploy_frontend
    ;;
  *)
    echo "Uso: $0 [full|backend|frontend]"
    exit 1
    ;;
esac

git_sync

echo ""
echo "╔════════════════════════════════════════╗"
ok "Despliegue completado exitosamente! 🚀"
echo "  → URL:      https://clasicoslafe.qrewards.com.mx/"
echo "  → Backend:  https://clasicoslafe.qrewards.com.mx/backend/"
echo "╚════════════════════════════════════════╝"
echo ""
