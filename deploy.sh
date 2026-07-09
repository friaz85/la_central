#!/bin/bash
set -e

REMOTE="u6-pu9mvhpmgwh1@ssh.qrewards.com.mx"
SSH_KEY="$HOME/.ssh/id_rsa_siteground"
PORT=18765
REMOTE_ROOT="/home/customer/www/g15k.qrewards.com.mx/public_html"

# Git commit y push
echo "▶ Guardando cambios en Git..."
cd "$(dirname "$0")"
git add -A
git commit -m "deploy: $(date '+%Y-%m-%d %H:%M:%S')" || echo "Sin cambios nuevos para commit"
git push || echo "Sin cambios para push"

echo "▶ Building frontend..."
cd "$(dirname "$0")/frontend"
npm run build

echo "▶ Deploying frontend → $REMOTE_ROOT/"
rsync -avz --delete \
  --exclude 'backend/' \
  -e "ssh -p $PORT -i $SSH_KEY -o StrictHostKeyChecking=no" \
  dist/frontend/browser/ \
  "$REMOTE:$REMOTE_ROOT/"

echo "▶ Deploying backend → $REMOTE_ROOT/backend/"
cd "$(dirname "$0")"
rsync -avz \
  -e "ssh -p $PORT -i $SSH_KEY -o StrictHostKeyChecking=no" \
  backend/ \
  "$REMOTE:$REMOTE_ROOT/backend/"

echo "✅ Deploy completo → g15k.qrewards.com.mx"
