# Installer ePT

---

## Installer sur Ubuntu 26.04 LTS {#installing-on-ubuntu-2604-lts}

**Important :** utilisez Ubuntu 26.04 LTS pour un nouveau serveur. Les serveurs existants sous Ubuntu 22.04 ou 24.04 LTS continuent de fonctionner, et `ept update` les met toujours à jour. L'installation refuse les versions non LTS.

ePT demande PHP 8.4.27 ou ultérieur, ou PHP 8.5.12 ou ultérieur. Avec une version plus ancienne, ePT refuse de démarrer. `setup.sh` et `ept update` installent une version prise en charge.

### Étapes d'installation {#installation-steps}

Ouvrez votre terminal et exécutez ces commandes dans l'ordre :

```bash
cd ~;

sudo wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh

sudo chmod +x ept-setup.sh;

sudo ./ept-setup.sh;

sudo rm ept-setup.sh;

exit
```

**Essentiel :** quand l'installation vous le demande, saisissez avec exactitude le mot de passe MySQL et le nom de domaine.

**SSL (facultatif) :** si le serveur a une IP publique et que vous saisissez un vrai nom de domaine (par exemple `ept.example.org`), le script d'installation propose d'installer un certificat SSL gratuit avec Let's Encrypt (Certbot). Cette étape est facultative. La réponse par défaut est non.

Si vous avez un fichier SQL de base de données à importer, indiquez-le par un chemin local ou une URL :

```bash
# Local file (supports .sql, .gz, .zip)
sudo ./ept-setup.sh --db /path/to/ept-base.sql.gz

# URL (supports .sql, .gz, .zip)
sudo ./ept-setup.sh --db https://example.com/ept-base.sql.gz
```

### Après l'installation {#post-installation}

- Une fois l'installation terminée, ouvrez ePT dans votre navigateur à l'adresse `http://ept/admin` (ou `https://yourdomain/admin` si SSL a été configuré)
- Si aucun fichier SQL n'a été fourni, l'installation vous demande de créer un premier compte administrateur
- Changez le mot de passe administrateur par défaut dès la première connexion
- Exécutez `ept check` sur le serveur pour vérifier la base de données, les sauvegardes, le disque et le cron. Tapez `ept` seul pour afficher un menu des tâches de maintenance
- Configurez les paramètres de votre organisation, ajoutez des participants, créez des enquêtes PT et préparez les expéditions

### Mise à jour {#updating}

Pour mettre à jour une installation ePT existante :

```bash
sudo ept update
```

Voir [Mettre à jour une installation ePT](updating.md) pour les autres façons de l'exécuter, la vérification du résultat et le retour en arrière.

---

## Installer avec Docker {#installing-with-docker}

Docker est le moyen le plus simple de faire fonctionner ePT avec une seule commande. Vous n'avez pas besoin d'installer vous-même PHP, Apache, MySQL ou Node.js.

### Prérequis {#prerequisites}

- [Docker](https://docs.docker.com/get-docker/) et [Docker Compose](https://docs.docker.com/compose/install/) installés sur votre système

### Démarrage rapide {#quick-start}

```bash
git clone https://github.com/deforay/ept.git
cd ept
docker compose up --build -d
```

Une fois les conteneurs démarrés, créez le premier compte administrateur :

```bash
docker compose exec ept php bin/seed-admin.php
```

Ouvrez ensuite ePT à l'adresse [http://localhost/admin](http://localhost/admin).

Pour utiliser un port personnalisé (par exemple 3456) :

```bash
APP_PORT=3456 docker compose up --build -d
```

Ouvrez ensuite ePT à l'adresse `http://localhost:3456/admin`.

### Configuration {#configuration}

Copiez le fichier d'environnement d'exemple et adaptez-le si nécessaire :

```bash
cp .env.example .env
```

Variables d'environnement disponibles :

| Variable | Valeur par défaut | Description |
| --- | --- | --- |
| `DB_HOST` | `ept-db` | Nom d'hôte MySQL (utilisez le nom du service) |
| `DB_USER` | `root` | Utilisateur MySQL. Le conteneur `ept-db` fourni crée seulement `root` |
| `DB_PASSWORD` | `ept_secret` | Mot de passe MySQL |
| `DB_NAME` | `ept` | Nom de la base de données. Fixe. `sql/init.sql` nomme la base de données qu'il crée |
| `MYSQL_ROOT_PASSWORD` | `ept_secret` | Mot de passe root de MySQL. Doit être identique à `DB_PASSWORD` quand `DB_USER` vaut `root` |
| `APP_PORT` | `80` | Port de l'hôte sur lequel l'application est exposée |
| `APP_DOMAIN` | `http://localhost/` | URL de l'application |
| `APP_HOSTNAME` | `localhost` | Nom de domaine (utilisé par nginx pour SSL) |
| `HTTP_PORT` | `80` | Port de l'hôte sur lequel nginx écoute (`--profile ssl` seulement) |
| `HTTPS_PORT` | `443` | Port TLS de l'hôte sur lequel nginx écoute (`--profile ssl` seulement) |

> **Changez le mot de passe par défaut :** définissez `DB_PASSWORD` et `MYSQL_ROOT_PASSWORD` dans `.env` avant un déploiement en production. Les deux valent `ept_secret` par défaut. Définissez-les avant le premier démarrage. MySQL lit `MYSQL_ROOT_PASSWORD` seulement quand il initialise un répertoire de données vide. Si vous le changez après la création de `mysql_data`, l'ancien mot de passe reste en place.

### Contenu de l'installation {#whats-included}

L'installation Docker fait tout fonctionner dans deux conteneurs :

- **ept** : PHP 8.4, Apache, Node.js (pour le rendu des graphiques), les dépendances Composer et une tâche cron pour le planificateur
- **ept-db** : MySQL 8.0, initialisé à partir de `sql/init.sql`

Au premier démarrage, le script d'entrée effectue automatiquement ces actions :

1. Il génère `application.ini` et `.env` à partir des modèles dist
2. Il insère les identifiants de la base de données et le domaine à partir des variables d'environnement
3. Il attend que MySQL soit prêt
4. Il exécute les migrations de la base de données
5. Il démarre le planificateur cron

### Données persistantes {#persistent-data}

Les données suivantes sont stockées dans des volumes Docker et sont conservées après un redémarrage des conteneurs :

- `mysql_data` : fichiers de la base de données
- `uploads` : fichiers téléversés
- `logs` : journaux de l'application
- `backups` : sauvegardes de la base de données
- `downloads` : fichiers générés à télécharger

### Commandes courantes {#common-commands}

```bash
# Start in background
docker compose up --build -d

# View logs
docker compose logs -f ept

# Stop containers
docker compose down

# Stop and remove all data (including database)
docker compose down -v

# Run migrations manually
docker compose exec ept composer post-update

# Seed initial admin account (first-time setup)
docker compose exec ept php bin/seed-admin.php

# Access MySQL shell
docker compose exec ept-db mysql -u root -p ept

# Access app shell
docker compose exec ept bash
```

### Mise à jour (Docker) {#updating-docker}

Pour mettre à jour ePT, suivez [Mettre à jour une installation Docker](updating.md#update-a-docker-installation). Une simple reconstruction perd `application.ini` et `.env`. Copiez-les d'abord hors du conteneur, comme l'indique cette section.

Le script d'entrée exécute `composer post-update` (migrations et corrections de collation) à chaque démarrage du conteneur. Voir [Mettre à jour une installation ePT](updating.md) pour la vérification et le retour en arrière.

### Importer une base de données existante {#importing-an-existing-database}

Cette procédure concerne uniquement une **nouvelle** installation Docker. `docker compose down -v` supprime tous les volumes : la base de données, les fichiers téléversés, les rapports générés, les journaux et les sauvegardes. Ne l'exécutez pas sur une installation qui contient des données dont vous avez besoin. Pour charger une base de données dans une installation en service, exécutez plutôt `docker compose exec ept php vendor/bin/db-tools restore <archive>`. Voir [Sauvegarde et restauration](backup-and-migration.md).

Pour démarrer une nouvelle installation à partir d'un fichier SQL existant au lieu du fichier `init.sql` fourni :

```bash
# Stop containers and remove ALL data
docker compose down -v

# Replace the init file
cp /path/to/your-database.sql sql/init.sql

# Rebuild and start
docker compose up --build -d
```

### SSL avec Let's Encrypt (Docker) {#ssl-with-lets-encrypt-docker}

Sur un serveur de production avec une IP publique et un nom de domaine, vous pouvez activer HTTPS avec des certificats Let's Encrypt automatiques.

**Prérequis :**

- Un nom de domaine (par exemple `ept.example.org`) qui pointe vers l'IP publique de votre serveur
- Les ports 80 et 443 ouverts dans le pare-feu

**Première configuration :**

```bash
# Initialize certificates (run once)
sudo ./docker/init-letsencrypt.sh ept.example.org admin@example.org

# Start with SSL enabled
APP_PORT=8080 APP_HOSTNAME=ept.example.org APP_DOMAIN=https://ept.example.org/ docker compose --profile ssl up -d
```

Donnez à `APP_PORT` la valeur d'un port libre, ou placez-le dans `.env`. Avec ce profil, nginx publie les ports 80 et 443, et le conteneur de l'application ne peut pas publier le port 80 en même temps.

**Fonctionnement :**

L'option `--profile ssl` ajoute deux conteneurs :

- **nginx** : proxy inverse qui gère le SSL sur les ports 80/443 et transmet les requêtes à l'application
- **certbot** : obtient et renouvelle les certificats Let's Encrypt

**Renouvellement des certificats :**

Les certificats sont valables 90 jours. Pour les renouveler :

```bash
docker compose --profile ssl run --rm certbot renew
docker compose --profile ssl exec nginx nginx -s reload
```

> **Mode développement :** sans `--profile ssl`, l'application fonctionne uniquement en HTTP (sur `APP_PORT`, 80 par défaut). Aucun conteneur nginx ou certbot ne démarre. C'est la configuration recommandée pour le développement local.

---

## Installer sur Windows {#installing-on-windows}

### 0. Téléchargements {#0-download}

- [Notepad++](https://notepad-plus-plus.org/) ou [VS Code](https://code.visualstudio.com/)
- [WampServer](https://www.wampserver.com/en/) (choisissez 32 ou 64 bits selon votre système)
- [VC Packages](https://wampserver.aviatechno.net/files/vcpackages/all_vc_redist_x86_x64.zip)

### 1. Installer WampServer {#1-install-wamp-server}

- Vérifiez que toutes les mises à jour de Windows sont installées
- Installez les VC Packages (tous les paquets sur un système 64 bits, seulement les paquets 32 bits sur un système 32 bits)
- Redémarrez la machine
- Lancez WampServer et vérifiez que l'icône est verte

### 2. Configurer PHP {#2-configure-php}

- Téléchargez `cacert.pem` depuis https://curl.se/docs/caextract.html et placez-le dans `C:\wamp64\`
- Passez à PHP 8.4 : icône WampServer > PHP > version > 8.4.x. La version doit être 8.4.27 ou ultérieure. ePT refuse de démarrer avec une version 8.4 plus ancienne. Si WampServer ne propose pas cette version, installez un module complémentaire PHP plus récent depuis le site de WampServer.
- Ouvrez `php.ini` avec icône WampServer > PHP > php.ini et modifiez :
  - `memory_limit` = `2G` (ou plus si possible)
  - `post_max_size` = `500M`
  - `upload_max_filesize` = `500M`
  - `max_execution_time` = `1200`
  - `error_reporting` = `E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING`
  - `;openssl.cafile=` en `openssl.cafile='C:\wamp64\cacert.pem'`
  - `;curl.cainfo =` en `curl.cainfo ='C:\wamp64\cacert.pem'`
- Faites les mêmes modifications dans `C:\wamp64\bin\php\php8.4.x\php.ini`

### 3. Configurer MySQL {#3-configure-mysql}

Ouvrez icône WampServer > MySQL > my.ini, puis :

- Cherchez `sql_mode` et mettez la ligne en commentaire avec `;` en début de ligne
- Ajoutez ces lignes sous `[mysqld]` :
  ```ini
  sql_mode =
  innodb_strict_mode = 0
  ```

**Définir le mot de passe root de MySQL :**

- Icône WampServer > MySQL > MySQL Console
- Nom d'utilisateur : `root`, mot de passe : (vide, appuyez sur Entrée)
- Exécutez :
  ```sql
  ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'YOUR_PASSWORD';
  FLUSH PRIVILEGES;
  exit;
  ```

Redémarrez tous les services de WampServer. Téléchargez et installez ensuite [Composer](https://getcomposer.org/download/).

### 4. Installer ePT {#4-set-up-ept}

**Télécharger et extraire :**

- Téléchargez ePT depuis https://github.com/deforay/ept/releases (ou clonez le dépôt)
- Extrayez-le et placez-le dans `C:\wamp64\www\ept`

**Installer les dépendances :**

Ouvrez un terminal et exécutez :

```
cd C:\wamp64\www\ept
set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
composer install --no-dev
composer dump-autoload -o
```

**Préparer la base de données :**

- Ouvrez phpMyAdmin à l'adresse http://localhost/phpmyadmin
- Cliquez sur l'onglet SQL et exécutez :
  ```sql
  CREATE DATABASE `ept` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
  ```
- Importez le fichier SQL de base depuis la [page des versions](https://github.com/deforay/ept/releases) dans la base de données `ept`

**Configuration :**

- Renommez `application/configs/application.dist.ini` en `application/configs/application.ini`
- Modifiez `application.ini` avec :
  - Le domaine : `domain = http://ept/`
  - Les identifiants de la base de données (`resources.db.params.*`)
  - Les paramètres SMTP pour l'e-mail (ou définissez-les plus tard dans **Configurer → Paramètres globaux d'ePT**, voir [Maintenir l'envoi des e-mails](email.md))
  - Le sel de sécurité (une chaîne aléatoire)
- Après votre connexion, définissez le nom de votre organisation dans **Configurer → Paramètres globaux d'ePT** et les paramètres d'évaluation dans **Configurer → Configuration des programmes**

**Configurer l'hôte virtuel :**

- Ouvrez `C:\Windows\System32\drivers\etc\hosts` en tant qu'administrateur et ajoutez :
  ```
  127.0.0.1 ept
  ```
- Modifiez `C:\wamp64\bin\apache\apache2.x.x\conf\extra\httpd-vhosts.conf` et ajoutez :
  ```apache
  <VirtualHost *:80>
      ServerName ept
      DocumentRoot "C:/wamp64/www/ept/public"
      <Directory "C:/wamp64/www/ept/public/">
          AddDefaultCharset UTF-8
          Options -Indexes +FollowSymLinks +MultiViews
          AllowOverride All
          Require local
      </Directory>
  </VirtualHost>
  ```
- Redémarrez tous les services de WampServer

**Exécuter les migrations :**

```
cd C:\wamp64\www\ept
set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
php bin\migrate.php -yq
```

### 5. Planificateur de tâches (Windows) {#5-task-scheduler-windows}

- Ouvrez le Planificateur de tâches et créez une tâche nommée "ePT Task"
- Sélectionnez "Run whether user is logged on or not" (« Exécuter même si l'utilisateur n'est pas connecté » sur un Windows en français)
- Dans l'onglet **Triggers** (Déclencheurs) : créez un déclencheur
  - Sélectionnez Daily (Tous les jours)
  - Cochez "Repeat task every" (Répéter la tâche toutes les) et choisissez **1 minute**, sans limite de durée
- Dans l'onglet **Actions** : créez une action
  - Programme : `C:\wamp64\bin\php\php8.4.x\php.exe`
  - Arguments : `C:\wamp64\www\ept\vendor\bin\crunz schedule:run`
- Saisissez le mot de passe de l'utilisateur Windows quand il est demandé

### Après l'installation (Windows) {#post-installation-windows}

- Ouvrez ePT à l'adresse http://ept/admin
- Connectez-vous avec les identifiants administrateur fournis par l'import SQL
- Si l'import SQL ne contient aucun compte administrateur, créez le premier. Ouvrez une invite de commandes dans `C:\wamp64\www\ept` et exécutez `php bin\seed-admin.php`. Le script demande le nom, l'e-mail et le mot de passe de l'administrateur.
- Changez immédiatement le mot de passe administrateur par défaut

---

## Assistance {#support}

- **GitHub** : [deforay/ept](https://github.com/deforay/ept)
- **Contact** : amit@deforay.com
- **Licence** : [AGPL-3.0](https://github.com/deforay/ept/blob/master/LICENSE.md)
