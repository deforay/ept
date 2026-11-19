# Sauvegarde, restauration et migration

Cette page couvre trois opérations liées pour une installation ePT : faire des **sauvegardes**, les **restaurer** (reprise après sinistre) et **déplacer une installation vers une nouvelle machine**.

ePT est conçu pour fonctionner comme une **installation centrale unique**. Les outils décrits ici privilégient donc des procédures simples, menées par un opérateur, plutôt que l'automatisation d'un parc de serveurs. Les éléments de base sont :

| Outil | Rôle |
| --- | --- |
| `ept` | Commandes courantes : sauvegarder, lister, vérifier, restaurer, restaurer à un instant précis |
| `vendor/bin/db-tools` (`amitdugar/db-tools`) | Le moteur derrière `ept` : créer, restaurer, vérifier les sauvegardes de la base de données et les restaurer à un instant précis |
| `bin/setup.sh` | Nouvelle installation sur une nouvelle machine, avec import facultatif d'une base de données |
| `bin/upgrade.sh` (`ept update`) | Propose des sauvegardes de la base de données et du dossier avant la mise à jour |

> **Ce qui est automatisé et ce qui ne l'est pas :** db-tools gère la **base de données** de bout en bout. Le **code** de l'application est toujours téléchargé à neuf depuis GitHub par `setup.sh`. Les **fichiers téléversés** (`public/uploads/`), les **rapports générés** (`downloads/`), les **modèles de certificats** et les **secrets de configuration** ne sont jamais transférés automatiquement. Copiez-les à la main lors d'un déplacement. Voir [Migrer vers une nouvelle machine](#migrating-to-a-new-machine).

---

## Sauvegardes {#backups}

### Sauvegarde rapide {#quick-backup}

Depuis n'importe quel répertoire du serveur :

```bash
# Database + a snapshot of application.ini (config)
ept backup

# List archives, and check they are intact
ept backup list
ept backup verify
```

`ept backup` s'exécute avec le compte propriétaire de l'installation. Les archives restent ainsi lisibles par l'application. Les archives sont écrites dans `<ept>/backups`, avec une conservation de 15 archives par défaut.

> **Les sauvegardes sont chiffrées par défaut :** `db-tools backup` chiffre les archives (`.gpg`) avec un mot de passe de chiffrement, sauf si vous passez `--no-encrypt`. **Il faut le même mot de passe pour restaurer.** Pour une sauvegarde à restaurer sur une *autre* machine, notez le mot de passe de chiffrement ou créez une archive non chiffrée avec `--no-encrypt` (voir [Migrer](#migrating-to-a-new-machine)).

### Utiliser db-tools directement {#direct-db-tools-usage}

Utilisez db-tools directement pour les options que `ept backup` ne propose pas. Exécutez ces commandes depuis le répertoire d'ePT :

```bash
# Encrypted, compressed archive into the default backups dir
php vendor/bin/db-tools backup

# Choose compression backend and a filename label
php vendor/bin/db-tools backup --compression=zstd --label=nightly

# Unencrypted archive (e.g. for a same-day migration)
php vendor/bin/db-tools backup --no-encrypt

# List existing archives / check integrity
php vendor/bin/db-tools show
php vendor/bin/db-tools verify
```

Pour afficher le profil actif (hôte, base de données, répertoire de sortie, conservation, chiffrement) :

```bash
php vendor/bin/db-tools config:show
```

### Planification {#scheduling}

Le planificateur Crunz fourni définit une sauvegarde quotidienne de la base de données à 00 h 45, dans le fuseau horaire
de l'application. Il définit aussi un instantané de `application.ini` le dimanche à 01 h 00.
Ces tâches demandent un exécuteur cron/Crunz fonctionnel. Vérifiez qu'elles s'exécutent avant d'ajouter une autre
planification de sauvegarde. Les archives qui dépassent le nombre conservé sont supprimées automatiquement.

L'instantané de configuration écrit deux fichiers dans `backups/config/`. Le premier est une copie de `application.ini`. Le second est `templates.<timestamp>.zip`, qui contient les modèles de certificats, les en-têtes de rapports et les logos téléversés. L'instantané ne crée pas le zip si aucun modèle n'a changé. Restaurez le zip depuis le répertoire d'installation, car ses chemins sont relatifs à ce répertoire.

L'instantané ne couvre pas `application/configs/.env`, `public/uploads/` ni `downloads/`. La sauvegarde des fichiers et la copie hors de l'hôte demandent des dispositions séparées.
Voir [stockage et conservation](data-lifecycle.md) pour le périmètre et la conservation par défaut.

> **Les sauvegardes avant mise à jour sont facultatives :** `ept update` propose des sauvegardes de la base de données et du dossier.
> La réponse par défaut est non pour les deux questions. Les exports de la base de données vont dans `/var/ept-backup/db/` et les copies
> du dossier dans `/var/ept-backup/www/`. Vérifiez qu'une sauvegarde a réussi avant de compter sur elle pour une restauration.

---

## Restauration et reprise après sinistre {#restore-disaster-recovery}

Pour restaurer sur la **même machine** (ou pour reconstruire après une perte de données) :

```bash
# Interactive: pick an archive from the backups dir
ept restore

# Restore a specific archive
ept restore /path/to/ept-YYYYMMDD-HHMMSS.sql.zst.gpg

# Encrypted archive — supply the password
ept restore <archive> --encryption-password='...'
```

`ept restore` transmet toutes les options à `db-tools restore`.

Par défaut, db-tools fait une **sauvegarde de sécurité avant de restaurer** (désactivez-la avec `--skip-safety-backup`). Il demande une confirmation, sauf si vous passez `--force`.

### Restauration à un instant précis {#point-in-time-recovery}

Si la journalisation binaire est activée, db-tools peut rejouer les modifications faites après la dernière sauvegarde complète :

```bash
ept pitr                                          # inspect available binlogs
ept pitr restore                                  # apply binlogs to a target time
php vendor/bin/db-tools purge-binlogs             # housekeeping (from the ePT directory)
```

---

## Migrer vers une nouvelle machine {#migrating-to-a-new-machine}

Suivez ces étapes pour déplacer ePT d'un serveur Ubuntu vers un autre. Vous préparez un dossier sur l'ancien serveur, vous le copiez, puis vous le décompressez sur le nouveau serveur. Prévoyez une heure d'interruption.

### Prérequis {#prerequisites}

Réunissez tous ces éléments avant de commencer.

#### Le nouveau serveur {#the-new-server}

1. Une installation neuve d'Ubuntu 26.04 LTS, sans aucun autre logiciel installé. L'installation d'ePT installe elle-même Apache, MySQL 8 et PHP 8.4.
2. Au moins 2 Go de RAM si ePT est la seule application du serveur. 4 Go, c'est mieux.
3. Au moins 50 Go d'espace disque.
4. Un accès à Internet. L'installation télécharge des paquets et ePT depuis GitHub.
5. Les ports 22 (SSH), 80 (HTTP) et 443 (HTTPS) ouverts sur le pare-feu.
6. SSH activé, avec un compte utilisateur qui dispose des droits sudo.

#### Identifiants et mots de passe {#logins-and-passwords}

1. Le nom d'utilisateur et le mot de passe SSH du nouveau serveur.
2. Le nom d'utilisateur et le mot de passe SSH de l'ancien serveur, pour un utilisateur qui dispose des droits sudo.
3. Le mot de passe root MySQL de l'ancien serveur.

#### Autres éléments {#other}

1. Un accès aux réglages DNS du domaine, ou le contact de la personne qui les gère.
2. Une connexion SSH de l'ancien serveur vers le nouveau. À défaut, une clé USB assez grande pour contenir les données d'ePT.
3. Une fenêtre de maintenance d'une heure. Prévenez à l'avance les participants et les gestionnaires de données qu'ePT est indisponible pendant le déplacement.

Les commandes supposent qu'ePT est installé dans `/var/www/ept`. Si votre installation se trouve ailleurs, remplacez ce chemin dans chaque commande.

### Étape 1. Tout préparer sur l'ancien serveur {#step-1-pack-everything-on-the-old-server}

Exécutez ces commandes sur l'ancien serveur :

```bash
# Stop the website and scheduled jobs so nothing changes during the move.
# They stay stopped after a reboot.
sudo systemctl disable --now apache2 cron

# Create the folder that holds everything you move
sudo mkdir -p /var/ept-move

# Back up the database into that folder
cd /var/www/ept
sudo php vendor/bin/db-tools backup --no-encrypt --compression=gzip --output-dir=/var/ept-move

# Pack the settings, uploads, reports and certificate templates into that folder
sudo tar --ignore-failed-read -czf /var/ept-move/ept-files.tar.gz \
  application/configs/application.ini \
  application/configs/.env \
  public/uploads \
  downloads \
  scheduled-jobs/certificate-templates

# Let your own account copy the folder
sudo chown -R "$USER" /var/ept-move
```

Exécutez `ls -lh /var/ept-move`. Le dossier contient deux fichiers : une sauvegarde de la base de données qui se termine par `.sql.gz`, et `ept-files.tar.gz`.

### Étape 2. Copier le dossier sur le nouveau serveur {#step-2-copy-the-folder-to-the-new-server}

```bash
scp -r /var/ept-move USER@NEW-SERVER:/tmp/
```

Remplacez `USER` par votre identifiant sur le nouveau serveur et `NEW-SERVER` par son adresse IP. Si vous utilisez une clé USB, placez le dossier dans `/tmp/ept-move` sur le nouveau serveur.

### Étape 3. Installer ePT sur le nouveau serveur {#step-3-install-ept-on-the-new-server}

Exécutez ces commandes sur le nouveau serveur :

```bash
cd ~
wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh
chmod +x ept-setup.sh
sudo ./ept-setup.sh --db /tmp/ept-move/*.sql.gz
```

Si l'ancienne base de données ne s'appelle pas `ept`, ajoutez `--db-name ANCIEN_NOM` à la dernière commande, pour que la base garde son nom. L'ancien fichier de configuration l'attend. Vérifiez ce nom sur l'ancien serveur avec `grep '^resources.db.params.dbname' /var/www/ept/application/configs/application.ini`.

L'installation installe ePT et charge l'ancienne base de données. Répondez à ses questions ainsi :

| L'installation demande | Réponse |
| --- | --- |
| Chemin d'installation | Appuyez sur Entrée pour accepter `/var/www/ept`. |
| Nom de domaine | Saisissez le domaine de l'ancien serveur. |
| SSL (HTTPS) avec Let's Encrypt | Répondez non. Vous configurez le SSL à l'étape 6. |
| Mot de passe root MySQL | Saisissez le mot de passe root MySQL de l'ancien serveur. |

Utilisez l'ancien mot de passe root MySQL, car le fichier de configuration de l'ancien serveur le contient.

### Étape 4. Décompresser les fichiers sur le nouveau serveur {#step-4-unpack-the-files-on-the-new-server}

```bash
sudo tar -xzf /tmp/ept-move/ept-files.tar.gz -C /var/www/ept
cd /var/www/ept
sudo chown -R www-data:www-data application/configs public/uploads downloads scheduled-jobs
grep '^resources.db.params' application/configs/application.ini
```

Le fichier de configuration restauré contient encore les paramètres de base de données de l'ancien serveur. Vérifiez que `host`, `username`, `password` et `dbname` correspondent au nouveau serveur. Corrigez toute ligne différente. Exécutez ensuite :

```bash
ept post-update
ept check
```

Ne sautez pas cette étape. L'installation crée un nouveau sel de sécurité et un nouveau secret de formulaire. Les anciens, contenus dans `ept-files.tar.gz`, les remplacent. Sans l'ancien sel, les mots de passe temporaires et les liens de téléchargement déjà envoyés par ePT cessent de fonctionner.

`ept check` liste chaque problème trouvé. Corrigez-les tous avant de continuer.

### Étape 5. Faire pointer le domaine vers le nouveau serveur {#step-5-point-the-domain-to-the-new-server}

Remplacez l'enregistrement DNS A du domaine par l'adresse IP du nouveau serveur. Le changement peut prendre quelques heures pour atteindre tous les utilisateurs. Si les utilisateurs accèdent à ePT par une adresse IP, communiquez-leur la nouvelle adresse.

### Étape 6. Activer le HTTPS {#step-6-turn-on-https}

Une fois que le domaine pointe vers le nouveau serveur, exécutez :

```bash
sudo certbot --apache -d your-domain.org
```

Remplacez `your-domain.org` par votre domaine. Sautez cette étape si l'ancien serveur n'utilisait pas le HTTPS.

### Étape 7. Vérifier le déplacement {#step-7-confirm-the-move-worked}

1. Ouvrez `https://your-domain.org/admin` et connectez-vous avec votre mot de passe habituel.
2. Ouvrez la liste des participants et une expédition. Vérifiez que les données correspondent à l'ancien serveur.
3. Ouvrez un rapport finalisé sur l'ancien serveur.
4. Générez un nouveau rapport.

Si ces quatre vérifications réussissent, le déplacement est terminé. Conservez l'ancien serveur en l'état pendant deux semaines. Supprimez ensuite `/var/ept-move` sur l'ancien serveur et `/tmp/ept-move` sur le nouveau, car ces dossiers contiennent des mots de passe.

Si le déplacement échoue, exécutez `sudo systemctl enable --now apache2 cron` sur l'ancien serveur et refaites pointer le domaine vers lui. Rien n'a changé sur l'ancien serveur.

---

## Référence des commandes {#command-reference}

```bash
ept backup                         # database + config snapshot → <ept>/backups
ept db-backup                      # database backup only
ept backup list                    # list archives
ept backup verify                  # integrity check
ept restore                        # restore (interactive; safety backup first)
ept pitr [restore]                 # point-in-time recovery from binlogs
ept check                          # includes the age of the latest backup
php vendor/bin/db-tools export     # plain SQL export (from the ePT directory)
php vendor/bin/db-tools import     # plain SQL import
php vendor/bin/db-tools config:show   # active profile
sudo ./ept-setup.sh --db <archive>    # fresh install + DB import (new machine)
sudo ept update                       # update in place (optional pre-upgrade backup)
```
