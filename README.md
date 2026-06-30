# NoExcuses

Plateforme de pilotage du mode de vie sportif et nutritionnel.
Projet fil rouge — CDA Bachelor 3 FullStack · IPSSI · Dillon Azag

---

## Stack

| Couche | Technologie |
|--------|-------------|
| Backend API | Symfony 6.4 + PHP 8.3 |
| ORM | Doctrine + Migrations |
| Auth | LexikJWT (Bearer token) |
| Base de données | MySQL 8.0 |
| Frontend | React 18 SPA + React Router v6 |
| HTTP Client | Axios |
| Graphiques | Recharts |
| API externe | CalorieNinjas + IA Mistral |
| Infra | Docker Compose (nginx + php-fpm + mysql + node) |
| CI/CD | GitHub Actions (PHPUnit + npm build) |

---

## Démarrage rapide

### Prérequis
- PHP 8.3 + Composer
- Node 20 + npm
- MySQL 8.0
- Symfony CLI (optionnel)
- Git

> **Note :** Le déploiement Docker complet est prévu pour le Jalon 6. Les instructions ci-dessous permettent de lancer l'application en local sans Docker.

### 1. Cloner le projet

```bash
git clone https://github.com/dillon816/Noexcuses.git
cd Noexcuses
```

### 2. Configurer le backend

```bash
cd backend
composer install

# Créer le fichier d'environnement local
cp .env .env.local
# Éditer .env.local : renseigner DATABASE_URL, CALORIENINJAS_API_KEY, MISTRAL_API_KEY
```

### 3. Générer les clés JWT

```bash
mkdir -p config/jwt
openssl genrsa -out config/jwt/private.pem 2048
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
```

### 4. Initialiser la base de données

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Lancer le backend

```bash
symfony serve
# ou : php -S localhost:8000 -t public
```

API disponible sur `http://127.0.0.1:8000/api`

### 6. Lancer le frontend

```bash
cd ../frontend
npm install --legacy-peer-deps
npm start
```

Frontend disponible sur `http://localhost:3000`

### 7. Lancer les tests

```bash
cd backend
vendor/bin/phpunit --testdox
```

---

## Structure du projet

```
noexcuses/
├── backend/                  # API Symfony 6
│   ├── src/
│   │   ├── Controller/       # 6 contrôleurs REST
│   │   ├── Entity/           # 11 entités Doctrine
│   │   ├── Repository/       # 7 repositories
│   │   └── Service/          # 7 services métier
│   ├── tests/                # PHPUnit (unitaires + intégration)
│   └── config/               # JWT, Security, Doctrine, CORS
├── frontend/                 # React 18 SPA
│   └── src/
│       ├── api/              # Clients Axios par module
│       ├── context/          # AuthContext (JWT)
│       ├── components/       # Layout, MacroBar, CalorieCircle
│       └── pages/            # 8 pages (Login, Dashboard, …)
├── docker/                   # Dockerfiles + nginx.conf
├── docs/                     # Maquettes, PDFs, documentation
└── .github/workflows/        # CI GitHub Actions
```

---

## Modules MVP

| Module | Routes API | Page React |
|--------|-----------|------------|
| Auth JWT | `POST /api/register` `POST /api/login` | Login · Register |
| Profil | `GET/PUT /api/profil` | Profil |
| Nutrition | `GET/POST /api/nutrition/*` | Nutrition |
| Entraînement | `GET/POST /api/seances/*` | Entraînement |
| Recovery Budget | `GET/POST /api/recovery/*` | Recovery |
| Dashboard | `GET /api/dashboard` | Dashboard |
| Progression | `GET /api/progression/*` | Progression |

---

## Sécurité (OWASP)

| Vulnérabilité | Mesure |
|--------------|--------|
| Injection SQL | Doctrine ORM — requêtes paramétrées uniquement |
| XSS | React — échappement DOM natif |
| CSRF | SPA + JWT — pas de session serveur |
| Brute force | Prévu Jalon 6 (Symfony Rate Limiter) |
| Secrets | `.env.local` dans `.gitignore`, jamais committés |
| Headers sécurité | Prévus Jalon 6 (X-Frame-Options, CSP) |
| CORS | NelmioCorsBundle configuré explicitement |

---

## Jalons IPSSI

| Jalon | Livrable | Statut |
|-------|---------|--------|
| 1 — Janvier | CDCF | ✅ |
| 2 — Février | Méthodologie + UX/UI | ✅ |
| 3 — Mars | Modélisation BDD | ✅ |
| 4 — Avril | UML + Architecture | ✅ |
| 5 — Mai | **Code bêta + tests + sécurité** | ✅ |
| 6 — Juin | Déploiement + soutenance | ⏳ |
