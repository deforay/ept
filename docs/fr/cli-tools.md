# Référence des outils en ligne de commande

Ce guide présente les outils en ligne de commande du répertoire `bin/`. Ils sont destinés à l'**équipe de support technique**. Ils servent à résoudre les problèmes d'accès, à gérer les comptes administrateur, à appliquer les mises à jour de la base de données et à maintenir les installations ePT.

Toutes les commandes ci-dessous supposent que vous êtes dans le répertoire d'installation d'ePT (par exemple `/var/www/ept`).

---

## La commande `ept` {#the-ept-command}

L'installation et la mise à jour installent `ept` (aussi disponible sous le nom `runner`) dans `/usr/local/bin`. Exécutez-la sans argument pour afficher un menu numéroté, ou utilisez l'une de ces commandes :

```bash
ept check             # database, schema version, latest backup, disk, writable dirs, cron
ept update            # fetch the latest ept-update and run it for this installation
ept backup            # back up the database and config now
ept backup list       # list backup archives
ept backup verify     # check backup archives are intact
ept restore           # restore the database from a backup (takes a safety backup first)
ept pitr [restore]    # inspect or apply binlogs for point-in-time recovery
ept migrate           # apply pending database migrations
ept run-once          # run pending one-time scripts
ept scripts           # the interactive launcher below
```

`ept` demande les droits d'administrateur uniquement pour `update`. Les commandes qui démarrent l'application s'exécutent sous le compte propriétaire de l'installation (généralement `www-data`). Ainsi, les exécuter avec `sudo` ne laisse pas de fichiers que le serveur web ne peut pas remplacer.

Tout autre mot est transmis à composer, par exemple `ept tasks` ou `ept phpstan`. `ept update` met à jour ePT, pas les paquets composer. Pour lancer la mise à jour propre à composer, utilisez `ept composer update`.

---

## Lanceur interactif {#interactive-launcher}

Si vous ne savez plus quel script fait quoi, exécutez le lanceur. Il liste chaque script exécutable de `bin/` avec une courte description. Il affiche le bloc d'utilisation du script choisi, puis l'exécute avec les arguments que vous saisissez.

```bash
# Interactive TUI — pick a script, see its usage, run it
php bin/console.php

# Plain listing (no prompts) — handy over non-interactive SSH or pipes
php bin/console.php list

# Show the usage block for one script (partial match works)
php bin/console.php info reset-password
php bin/console.php info reset          # ambiguous — lists all matches
```

En mode TUI, le script enfant hérite de votre terminal. Ses propres invites interactives (saisie du mot de passe, confirmations, etc.) fonctionnent donc normalement.

---

## Réinitialisation du mot de passe {#password-reset}

### Réinitialiser le mot de passe d'un gestionnaire de données {#reset-data-manager-password}

Réinitialise le mot de passe d'un gestionnaire de données (utilisateur côté participant). Vous pouvez rechercher l'utilisateur par e-mail ou par code participant.

```bash
# Interactive — prompts for email or participant code, then password
php bin/reset-password.php

# By email
php bin/reset-password.php -e user@example.com

# By participant code
php bin/reset-password.php --input PART-12345

# Generate a random password
php bin/reset-password.php -e user@example.com --generate

# Set a specific password and force reset on next login
php bin/reset-password.php -e user@example.com -p "NewPassword123" --force-reset
```

| Option | Description |
|------|-------------|
| `-e` / `--email` / `--input` | Adresse e-mail ou code participant |
| `-p` / `--password` | Définit un mot de passe précis (6 caractères minimum) |
| `-g` / `--generate` | Génère un mot de passe aléatoire de 12 caractères |
| `--force-reset` | Oblige l'utilisateur à changer de mot de passe à la prochaine connexion |

### Réinitialiser le mot de passe d'un administrateur {#reset-admin-password}

Réinitialise le mot de passe d'un administrateur système (utilisateur de l'interface d'administration). Si aucun e-mail n'est fourni, le script liste tous les administrateurs pour que vous en choisissiez un.

```bash
# Interactive — lists all admins, pick one, then set password
php bin/reset-admin-password.php

# By email
php bin/reset-admin-password.php -e admin@example.com

# Generate a random password
php bin/reset-admin-password.php -e admin@example.com --generate

# Set a specific password and force reset on next login
php bin/reset-admin-password.php -e admin@example.com -p "NewPassword123" --force-reset
```

| Option | Description |
|------|-------------|
| `-e` / `--email` | Adresse e-mail de l'administrateur |
| `-p` / `--password` | Définit un mot de passe précis (6 caractères minimum) |
| `-g` / `--generate` | Génère un mot de passe aléatoire de 12 caractères |
| `--force-reset` | Oblige l'administrateur à changer de mot de passe à la prochaine connexion |

---

## Gestion des comptes administrateur {#admin-account-management}

### Créer un administrateur {#seed-admin}

Crée un nouveau compte administrateur de manière interactive. Le nouvel administrateur reçoit tous les privilèges. Il devra changer son mot de passe à la première connexion.

```bash
php bin/seed-admin.php
```

Le script demande le prénom, le nom, l'e-mail et le mot de passe.

> **Remarque :** pendant `setup.sh`, ce script est appelé automatiquement uniquement s'il n'existe aucun compte administrateur. Exécuté manuellement, il peut servir autant de fois que nécessaire pour créer d'autres administrateurs.

---

## Base de données {#database}

### Exécuter les migrations {#run-migrations}

Applique les migrations de `database/migrations/` dans l'ordre des versions. L'exécution inclut
la version actuelle de `system_config.app_version` et les versions plus récentes. La réexécution
de la version actuelle exige des migrations idempotentes.

```bash
# Standard run — prompts on errors
php bin/migrate.php

# Auto-continue on errors (non-interactive)
php bin/migrate.php -y

# Quiet mode (suppress output)
php bin/migrate.php -q

# Auto-continue and quiet (used by setup.sh)
php bin/migrate.php -yq

# Dry run — show what would be executed without making changes
php bin/migrate.php -d

# Start from a specific version
php bin/migrate.php -v 7.2.0
```

| Option | Description |
|------|-------------|
| `-y` | Continue automatiquement en cas d'erreur (sans invite) |
| `-q` | Mode silencieux : masque la progression |
| `-d` | Simulation : affiche le SQL sans l'exécuter |
| `-v VERSION` | Remplace la version de départ (exécute les migrations à partir de cette version) |

### Scripts à exécution unique {#run-once-scripts}

Exécute les scripts PHP à usage unique du répertoire `run-once/`. Chaque script s'exécute une seule fois. L'exécution est enregistrée dans la table `run_once_scripts`.

```bash
php bin/run-once.php
```

Cette commande est appelée automatiquement pendant l'installation et les mises à jour. En général, vous n'avez pas besoin de l'exécuter manuellement, sauf instruction contraire.

### Actualiser les fichiers de traduction {#refresh-translation-files}

Actualise les catalogues gettext de l'installation ePT courante. Les mainteneurs doivent exécuter cette commande avant d'ouvrir les traductions dans Poedit.

```bash
# Refresh all locale files for the current instance
php bin/refresh-translations.php

# Refresh one locale only
php bin/refresh-translations.php --locale=fr_FR

# Refresh only one DB lookup table while debugging
php bin/refresh-translations.php --table=r_possibleresult

# Skip optional AI prefill even if AI credentials are configured
php bin/refresh-translations.php --skip-ai
```

Ce que fait la commande :

- elle régénère les chaînes à traduire issues des tables `r_*` configurées de la base de données
- elle met à jour les fichiers `.po` avec les nouvelles chaînes source
- elle recompile les fichiers `.mo`
- elle préremplit éventuellement les traductions vides si les variables d'environnement d'IA sont configurées

Procédure habituelle d'un mainteneur :

1. Exécutez `php bin/refresh-translations.php`
2. Ouvrez `application/languages/{locale}/{locale}.po` dans Poedit
3. Traduisez les nouvelles entrées et enregistrez

#### Les chaînes issues de la base de données s'accumulent {#db-backed-strings-accumulate}

`application/languages/db-translation-strings.php` contient les valeurs de référence des
tables `r_*` pour que `xgettext` puisse les trouver. Chaque installation a ses propres données
de référence. Le fichier est donc fusionné à chaque exécution, pas remplacé. Les chaînes absentes
de votre base de données sont conservées.

Ainsi, exécuter la commande sur une installation ne supprime jamais les valeurs d'une autre
installation, et le fichier peut être commité sans risque. Des exécutions répétées sur la même
base de données ne produisent aucune différence.

Pour reconstruire le fichier à partir de la seule base de données courante, en supprimant tout le reste :

```bash
php bin/generate-db-translation-strings.php --prune
```

Utilisez `--prune` uniquement pour un nettoyage délibéré. Cette option supprime les chaînes des
autres installations, qui ne sont alors récupérables que depuis l'historique git.

---

## Installation et mises à jour {#installation-updates}

### Installation (nouvelle installation) {#setup-fresh-install}

Script d'installation automatisée pour Ubuntu. Il télécharge ePT, installe la pile LAMP, configure Apache, MySQL et les tâches cron, et configure SSL en option.

```bash
sudo wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh
sudo chmod u+x ept-setup.sh
sudo ./ept-setup.sh
```

```bash
# With a local database SQL file
sudo ./ept-setup.sh --db /path/to/ept-base.sql

# With a URL to a database SQL file (supports .sql, .gz, .zip)
sudo ./ept-setup.sh --db https://example.com/ept-base.sql.gz

# With a custom database name
sudo ./ept-setup.sh --db-name myept

# With a specific database strategy for existing databases
sudo ./ept-setup.sh --db-strategy rename
```

| Option | Description |
|------|-------------|
| `--db FILE_OR_URL` | Chemin local ou URL d'un fichier SQL (.sql, .gz, .zip) à importer |
| `--db-name NAME` | Nom de la base de données (par défaut : `ept`) |
| `--db-strategy` | Action si la base de données existe déjà : `drop`, `rename` (par défaut) ou `use` |

Pendant l'installation, si le serveur a une adresse IP publique et que le nom de domaine ressemble à un vrai domaine (par exemple `ept.example.org`), le script propose de configurer SSL avec Let's Encrypt. C'est facultatif, et la réponse par défaut est non.

Consultez le [guide d'installation](setup.md) pour les instructions complètes.

### Configuration SSL (après l'installation) {#ssl-setup-post-install}

Si vous avez ignoré SSL pendant l'installation ou souhaitez l'ajouter plus tard, configurez-le manuellement avec Certbot :

```bash
# Install Certbot
sudo apt-get install -y certbot python3-certbot-apache

# Request a certificate (replace with your domain)
sudo certbot --apache -d yourdomain.example.org

# Verify auto-renewal is active
sudo certbot renew --dry-run
```

Après l'installation du certificat, mettez à jour `application/configs/application.ini` :

```ini
domain = https://yourdomain.example.org/
```

### Mise à jour (installation existante) {#update-existing-install}

Met à jour une installation ePT existante vers la dernière version.

> **Procédure pas à pas :** cette section liste les options. Pour la procédure de mise à jour complète, avec la commande en une ligne depuis GitHub, la vérification et le retour arrière, consultez [Mettre à jour une installation ePT](updating.md).

Sur une installation unique, `sudo ept update` est la forme la plus simple. Elle actualise `ept-update` et l'exécute avec `-p` défini sur cette installation. Les options placées après `update` sont transmises, par exemple `sudo ept update -s -b`.

La commande `ept-update` est installée automatiquement pendant l'installation, et `ept update` l'actualise à chaque exécution. Si elle n'est pas disponible, installez-la manuellement :

```bash
sudo wget -O /usr/local/bin/ept-update https://raw.githubusercontent.com/deforay/ept/master/bin/upgrade.sh
sudo chmod +x /usr/local/bin/ept-update
```

```bash
# Default — prompts for the installation path
sudo ept-update

# Specific path
sudo ept-update -p /var/www/ept

# Update all ePT instances in /var/www
sudo ept-update -A

# Auto-detect instances, pick which to update
sudo ept-update -A -i

# Non-interactive — update all, skip system updates and backup prompts
sudo ept-update -A -s -b
```

| Option | Description |
|------|-------------|
| `-p PATH` | Indique le chemin de l'installation ePT |
| `-A` | Détecte et met à jour automatiquement toutes les installations ePT de `/var/www` |
| `-i` | Sélection interactive des installations (avec `-A`) |
| `-s` | Ignore les mises à jour système d'Ubuntu |
| `-b` | Ignore les invites de sauvegarde |
