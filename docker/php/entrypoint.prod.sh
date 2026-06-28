#!/bin/bash
# Script de démarrage du conteneur backend en PRODUCTION.
set -e

cd /var/www/html

# Générer les clés JWT si absentes, CHIFFRÉES avec la passphrase de prod
# (les clés doivent correspondre à JWT_PASSPHRASE configuré, sinon la signature échoue)
if [ ! -f "config/jwt/private.pem" ]; then
  echo "[entrypoint] Generation des cles JWT (chiffrees avec la passphrase prod)..."
  mkdir -p config/jwt
  openssl genrsa -aes256 -passout pass:"$JWT_PASSPHRASE" -out config/jwt/private.pem 2048
  openssl rsa -pubout -in config/jwt/private.pem -passin pass:"$JWT_PASSPHRASE" -out config/jwt/public.pem
fi

# php-fpm tourne en www-data : il doit pouvoir lire les clés
chmod 644 config/jwt/private.pem config/jwt/public.pem

# Attendre que MySQL soit prêt
echo "[entrypoint] Attente de MySQL..."
until mysqladmin ping -h mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" --skip-ssl --silent 2>/dev/null; do
  sleep 2
done
echo "[entrypoint] MySQL est pret."

# Migrations
echo "[entrypoint] Execution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

# Chauffe le cache Symfony en mode production (plus rapide à l'exécution)
echo "[entrypoint] Prechauffage du cache prod..."
php bin/console cache:clear --no-debug
php bin/console cache:warmup --no-debug

echo "[entrypoint] Demarrage de PHP-FPM..."
exec "$@"
