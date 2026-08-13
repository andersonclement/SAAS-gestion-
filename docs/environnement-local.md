# Monter l'environnement de développement

L'application tourne sur **MySQL 8** ou **MariaDB 10.5.2+**, en développement
comme en production. SQLite n'est plus utilisé par défaut : il acceptait des
requêtes que MySQL refuse — notamment l'arithmétique sur colonnes non signées,
qui faisait tomber le tableau de bord en erreur 500 dès la première vente à
perte. Voir `App\Support\CalculMarge`.

## 1. Base de données

```bash
sudo apt-get install -y mariadb-server   # ou mysql-server
sudo service mariadb start
```

Deux bases : celle de développement, et celle que la suite de tests vide entre
chaque test. Les garder distinctes évite qu'un `php artisan test` n'efface le
jeu de démonstration.

```sql
CREATE DATABASE gestion_stock       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE gestion_stock_test  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'gestion_stock'@'127.0.0.1' IDENTIFIED BY 'votre-mot-de-passe';
GRANT ALL PRIVILEGES ON gestion_stock.*      TO 'gestion_stock'@'127.0.0.1';
GRANT ALL PRIVILEGES ON gestion_stock_test.* TO 'gestion_stock'@'127.0.0.1';
FLUSH PRIVILEGES;
```

## 2. Application

```bash
cd app
composer install
cp .env.example .env
php artisan key:generate
# renseigner DB_PASSWORD dans .env
php artisan migrate --seed
php artisan serve
```

Le seeder crée un jeu de démonstration : un patron, deux boutiques, leurs
gérants, un vendeur, un comptable et un superadmin. **Mot de passe commun :
`password`** — raison pour laquelle il ne doit jamais tourner en production.

Les identifiants sont affichés en fin de `db:seed`.

## 3. Tests

```bash
php artisan test
```

La suite vise `gestion_stock_test` (voir `phpunit.xml`) et la vide entre chaque
test. Sans serveur MySQL sous la main, un repli existe — plus rapide, mais il
ne détecte pas les écarts propres à MySQL :

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
```

## 4. Avant de proposer une modification

```bash
./vendor/bin/pint      # formatage
php artisan test       # suite complète
composer audit         # vulnérabilités des dépendances
```

## 5. Pièges connus

**Arithmétique signée.** Les prix et les quantités sont stockés en entiers non
signés. Toute expression SQL dont le résultat peut être négatif — une marge,
un écart d'inventaire — doit ramener ses opérandes en entiers signés avec
`CAST(... AS SIGNED)`, y compris les facteurs d'une multiplication. Sinon
MySQL renvoie « BIGINT UNSIGNED value is out of range » et la page tombe en
500. SQLite, lui, ne dit rien.

**Fonctions de date.** S'en tenir à ce que MySQL et SQLite comprennent tous
deux (`DATE()`), ou agréger à la journée en SQL et regrouper en PHP — c'est ce
que fait la courbe du tableau de bord.

**Portée multi-tenant.** Les modèles portant `BelongsToTenant` filtrent
automatiquement sur le tenant connecté. En console (commandes planifiées), il
n'y a pas de session : la portée ne s'applique pas et chaque requête doit
borner explicitement son périmètre.
