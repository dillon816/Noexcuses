# Image de base : PHP 8.3 avec FPM (FastCGI Process Manager)
# FPM = le moteur qui exécute le PHP, nginx lui parle via le port 9000
FROM php:8.3-fpm

# Installation des paquets système + extensions PHP nécessaires à Symfony/Doctrine
# - intl : internationalisation  | pdo_mysql : connexion MySQL
# - zip/mbstring : manipulation fichiers/chaînes  | opcache : cache de bytecode (perf)
# - default-mysql-client : fournit "mysqladmin" pour attendre que la BDD soit prête
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    default-mysql-client \
    && docker-php-ext-install \
        intl \
        pdo \
        pdo_mysql \
        zip \
        mbstring \
        opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer (gestionnaire de dépendances PHP) copié depuis l'image officielle
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Xdebug : permet de générer la couverture de tests (utile pour le Jalon 6)
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Notre configuration PHP perso (memory_limit, opcache, timezone...)
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Script de démarrage : il prépare l'app avant de lancer PHP
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# PHP-FPM écoute sur le port 9000 (nginx s'y connecte)
EXPOSE 9000

# Au démarrage du conteneur : on exécute notre script, qui finit par lancer php-fpm
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
