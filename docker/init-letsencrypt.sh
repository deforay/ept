#!/bin/bash
#
# Initialize Let's Encrypt certificates for ePT Docker deployment.
# Run this ONCE before starting with --profile ssl.
#
# Usage: sudo ./docker/init-letsencrypt.sh <domain> [email]
#
# Example:
#   sudo ./docker/init-letsencrypt.sh ept.example.org admin@example.org
#

set -e

DOMAIN=${1:?Usage: $0 <domain> [email]}
EMAIL=${2:-""}

DATA_PATH="./docker/certbot"

if [ -d "$DATA_PATH/conf/live/$DOMAIN" ]; then
    echo "Certificates already exist for $DOMAIN."
    read -p "Replace existing certificates? (y/N) " decision
    if [ "$decision" != "Y" ] && [ "$decision" != "y" ]; then
        exit 0
    fi
fi

echo "### Creating required directories..."
mkdir -p "$DATA_PATH/conf" "$DATA_PATH/www"

echo "### Downloading recommended TLS parameters..."
curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf \
    > "$DATA_PATH/conf/options-ssl-nginx.conf"
curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot/certbot/ssl-dhparams.pem \
    > "$DATA_PATH/conf/ssl-dhparams.pem"

echo "### Creating temporary self-signed certificate for $DOMAIN..."
mkdir -p "$DATA_PATH/conf/live/$DOMAIN"
openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
    -keyout "$DATA_PATH/conf/live/$DOMAIN/privkey.pem" \
    -out "$DATA_PATH/conf/live/$DOMAIN/fullchain.pem" \
    -subj "/CN=$DOMAIN" 2>/dev/null

echo "### Starting nginx with temporary certificate..."
APP_HOSTNAME="$DOMAIN" docker compose --profile ssl up -d nginx

echo "### Requesting Let's Encrypt certificate for $DOMAIN..."

# Build certbot command
CERTBOT_CMD="certbot certonly --webroot -w /var/www/certbot --agree-tos --no-eff-email -d $DOMAIN"
if [ -n "$EMAIL" ]; then
    CERTBOT_CMD="$CERTBOT_CMD --email $EMAIL"
else
    CERTBOT_CMD="$CERTBOT_CMD --register-unsafely-without-email"
fi

# Remove temporary certificate
rm -rf "$DATA_PATH/conf/live/$DOMAIN"

# Request real certificate
docker compose --profile ssl run --rm certbot $CERTBOT_CMD

echo "### Reloading nginx..."
docker compose --profile ssl exec nginx nginx -s reload

echo ""
echo "### Done! Certificates installed for $DOMAIN"
echo ""
echo "Start ePT with SSL:"
echo "  APP_HOSTNAME=$DOMAIN docker compose --profile ssl up -d"
