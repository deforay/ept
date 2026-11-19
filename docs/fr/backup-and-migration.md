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

`setup.sh` installe un code neuf et **importe votre base de données**. Il ne transfère **pas** les fichiers téléversés ni la configuration de l'ancienne machine. Un déplacement complet comporte quatre parties : la base de données, les fichiers téléversés, les rapports générés et les secrets de configuration.

> **Conservez le sel et le secret de formulaire :** deux valeurs doivent survivre au déplacement. Sinon, les données hachées ou chiffrées existantes et les sessions ouvertes ne fonctionneront plus :
>
> - `application/configs/application.ini` → `security.salt`
> - `application/configs/.env` → `FORM_SECRET`
>
> Une nouvelle exécution de `setup.sh` **régénère les deux**. Copiez les originaux de l'ancienne machine *après* la fin de l'installation.

### 1. Sur l'ancienne machine {#1-on-the-old-machine}

```bash
# Database: unencrypted and gzip-compressed, because setup.sh reads only .sql, .gz and .zip
php vendor/bin/db-tools backup --no-encrypt --compression=gzip

# Uploaded files and config secrets — copy these off the box
#   application/configs/application.ini   (contains security.salt + DB creds)
#   application/configs/.env              (FORM_SECRET)
#   public/uploads/                       (participant uploads, report formats, logos)
#   downloads/                            (generated reports and participant files)
#   scheduled-jobs/certificate-templates/ (certificate designs)
```

Transférez l'archive, ces fichiers et ces dossiers vers le nouveau serveur avec scp, rsync ou une clé USB.

### 2. Sur la nouvelle machine {#2-on-the-new-machine}

Exécutez l'installation en indiquant l'archive transférée avec `--db` (chemin local **ou** URL ; formats `.sql`, `.gz` et `.zip` pris en charge) :

```bash
cd ~
sudo wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh
sudo chmod +x ept-setup.sh
sudo ./ept-setup.sh --db /path/to/ept-backup.sql.gz
```

Cette commande installe la pile LAMP, télécharge ePT et importe votre base de données à la place du fichier `sql/init.sql` fourni.

### 3. Restaurer les fichiers téléversés et la configuration, puis vérifier {#3-restore-uploads-and-config-then-verify}

```bash
# Put the uploaded files back
rsync -a /path/to/old/public/uploads/  /var/www/ept/public/uploads/
rsync -a /path/to/old/downloads/  /var/www/ept/downloads/
rsync -a /path/to/old/scheduled-jobs/certificate-templates/  /var/www/ept/scheduled-jobs/certificate-templates/

# Restore config secrets (review DB host/creds for the new box first)
cp /path/to/old/application.ini  /var/www/ept/application/configs/application.ini
cp /path/to/old/.env             /var/www/ept/application/configs/.env

# Fix ownership/permissions if needed, then run any pending migrations
ept post-update
ept check
```

> **Vérifiez les identifiants de la base de données après avoir copié application.ini :** `application.ini` contient l'hôte, l'utilisateur et le mot de passe de la base de données de l'**ancienne** machine. Si le MySQL de la nouvelle machine est différent, modifiez `resources.db.params.*` avant de charger l'application.

Enfin, connectez-vous à `http://<host>/admin` et vérifiez que les participants et les expéditions s'affichent. Ouvrez un rapport finalisé sur l'ancienne machine. Générez ensuite un nouveau rapport pour vérifier que les fichiers téléversés, le sel et le secret ont été transférés sans erreur.

---

## Ce que transfère setup.sh et ce que vous transférez {#what-setupsh-carries-vs-what-you-carry}

| Élément | Transféré par `setup.sh` | À votre charge |
| --- | --- | --- |
| Code de l'application | ✅ téléchargé à neuf depuis la branche master sur GitHub | — |
| Base de données | ✅ avec `--db=<archive>` | Produire l'archive avec db-tools |
| `public/uploads/` | ❌ | Copier à la main |
| `downloads/` | ❌ | Copier à la main |
| `scheduled-jobs/certificate-templates/` | ❌ | Copier à la main |
| `application.ini` / `.env` | ❌ régénérés à partir des modèles | Copier à la main |
| `security.salt` / `FORM_SECRET` | ❌ régénérés | **Obligatoirement** copiés depuis l'ancienne machine |

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
