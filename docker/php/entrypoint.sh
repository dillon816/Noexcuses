#!/bin/bash
# Script lancé automatiquement au démarrage du conteneur Symfony.
# But : préparer l'application avant de lancer le serveur PHP.
set -e

cd /var/www/html

# 1. Installer les dépendances PHP si elles ne sont pas présentes
#    (on teste autoload.php car vendor/ peut exister vide via un volume Docker)
if [ ! -f "vendor/autoload.php" ]; then
  echo "[entrypoint] Installation des dependances Composer..."
  composer install --no-interaction --prefer-dist
fi

# 2. Générer les clés JWT si elles n'existent pas (pour signer les tokens)
if [ ! -f "config/jwt/private.pem" ]; then
  echo "[entrypoint] Generation des cles JWT..."
  mkdir -p config/jwt
  openssl genrsa -out config/jwt/private.pem 2048
  openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
fi

# 3. Attendre que le conteneur MySQL soit prêt à accepter des connexions
#    (le conteneur PHP démarre souvent avant que MySQL ait fini de booter)
#    --skip-ssl : MySQL 8 active SSL avec un certificat auto-signé que le client refuse
echo "[entrypoint] Attente de MySQL..."
until mysqladmin ping -h mysql -unoexcuses -pnoexcuses --skip-ssl --silent 2>/dev/null; do
  sleep 2
done
echo "[entrypoint] MySQL est pret."

# 4. Appliquer les migrations (créer les tables dans la base)
echo "[entrypoint] Execution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction || true

# 5. Lancer la commande finale passée par le Dockerfile (php-fpm)
echo "[entrypoint] Demarrage de PHP-FPM..."
exec "$@"
