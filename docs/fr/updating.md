# Mettre à jour une installation ePT

Ce guide met à jour une installation ePT en service avec le dernier code de `master`.

Sur un serveur où l'installation ou une mise à jour précédente a été exécutée, `ept` fait tout le travail. `sudo ept update` télécharge le programme de mise à jour actuel (`bin/upgrade.sh`, installé sous le nom `ept-update`) et l'exécute sur cette installation. Le programme de mise à jour actualise le code de l'application, les dépendances, le schéma de la base de données et l'entrée cron.

Pour vérifier qu'`ept` est installé, tapez `ept` et appuyez sur Entrée. Si un menu numéroté intitulé **ePT** s'affiche, il est installé. Tapez `8` et appuyez sur Entrée pour quitter le menu. Si vous voyez `command not found`, suivez [Si `ept` n'est pas encore installé](#if-ept-is-not-installed-yet).

## Avant de commencer {#before-you-start}

| Exigence | Détail |
| --- | --- |
| Système d'exploitation | Ubuntu avec Apache, MySQL, PHP et Composer déjà installés par [`setup.sh`](setup.md) |
| Droits | `root`, ou un utilisateur qui peut exécuter `sudo` |
| Mot de passe root de MySQL | Lu dans `application.ini` quand l'utilisateur configuré est `root`. Sinon, le script le demande |
| Interruption de service | Le site reste disponible pendant la copie des fichiers. Apache se recharge à la fin |

## Mettre à jour l'installation {#update-the-installation}

1. Faites une nouvelle sauvegarde :

    ```bash
    ept backup
    ```

    Attendez que la sauvegarde se termine sans erreur. Ne mettez pas à jour un serveur dont la sauvegarde échoue.

2. Lancez la mise à jour :

    ```bash
    sudo ept update
    ```

    Saisissez votre mot de passe si on vous le demande. Le programme de mise à jour demande s'il faut mettre à jour les paquets Ubuntu et s'il faut faire ses propres sauvegardes avant la mise à jour. Voir [Sauvegardes avant une mise à jour](#backups-before-an-update).

3. Quand le résumé « Upgrade Summary » s'affiche, vérifiez le serveur :

    ```bash
    ept check
    ```

    Chaque ligne doit afficher ✅. Voir [Vérifier la mise à jour](#verify-the-update).

Les options placées après `update` sont transmises au programme de mise à jour. Pour ignorer la mise à jour des paquets Ubuntu et les deux questions sur les sauvegardes :

```bash
sudo ept update -s -b
```

Utilisez `-s -b` seulement si le mot de passe root de MySQL est accessible depuis `application.ini`. Sinon, le programme de mise à jour s'arrête quand même pour le demander.

## Si `ept` n'est pas encore installé {#if-ept-is-not-installed-yet}

Les serveurs qui n'ont pas été mis à jour depuis l'arrivée d'`ept` ont encore l'ancienne commande `runner`. Sur ces serveurs, `runner update` exécute la mise à jour de Composer, qui n'est pas une mise à jour d'ePT. Exécutez une fois le programme de mise à jour directement depuis GitHub :

```bash
curl -fsSL "https://raw.githubusercontent.com/deforay/ept/master/bin/upgrade.sh?v=$(date +%s)" \
  | sudo bash -s -- -A
```

La mise à jour installe `ept`. Cette commande n'est donc nécessaire qu'une fois par serveur. Deux détails la font fonctionner :

- `?v=$(date +%s)` contourne le cache de raw.githubusercontent.com. Sans cela, le cache sert une ancienne copie du script pendant plusieurs minutes après une publication.
- Le script lit toutes les réponses depuis `/dev/tty`, et non depuis l'entrée standard. L'envoyer à `bash` par un tube ne bloque donc pas les questions interactives.

Tout ce qui suit `-s --` est transmis au script comme options. `-A` trouve et met à jour toutes les installations ePT sous `/var/www`.

## Mettre à jour plusieurs installations sur un serveur {#update-several-installations-on-one-server}

`ept update` met à jour l'installation à laquelle appartient `ept`. Pour mettre à jour plusieurs installations à la fois, appelez directement le programme de mise à jour :

```bash
# Update every installation under /var/www
sudo ept-update -A

# Detect installations, then choose which ones to update
sudo ept-update -A -i

# Update every installation without prompts
sudo ept-update -A -s -b
```

La liste complète des options se trouve dans la [Référence des outils en ligne de commande](cli-tools.md#update-existing-install).

> **Détection des installations :** avec `-A`, un répertoire sous `/var/www` est considéré comme une installation ePT s'il contient à la fois `application/configs/application.ini` et `public/`. Rien d'autre n'est modifié.

## Mettre à jour une installation Docker {#update-a-docker-installation}

`ept update` ne s'applique pas à Docker. Reconstruisez plutôt les conteneurs.

```bash
git pull && docker compose up --build -d
```

`application.ini`, `.env` et les modèles de certificats téléversés se trouvent dans les volumes `config` et `certificate_templates`. Une reconstruction les conserve donc.

**Une seule fois, sur une installation antérieure à ces volumes :** la première reconstruction remplace encore ces fichiers, car ils se trouvent dans l'ancien conteneur. Copiez-les hors du conteneur avant cette reconstruction, puis remettez-les en place après :

```bash
# Before the rebuild
docker compose cp ept:/var/www/ept/application/configs/application.ini ./application.ini.keep
docker compose cp ept:/var/www/ept/application/configs/.env ./env.keep
docker compose cp ept:/var/www/ept/scheduled-jobs/certificate-templates ./certificate-templates.keep

git pull && docker compose up --build -d

# After the rebuild
docker compose cp ./application.ini.keep ept:/var/ept-config/application.ini
docker compose cp ./env.keep ept:/var/ept-config/.env
docker compose cp ./certificate-templates.keep/. ept:/var/www/ept/scheduled-jobs/certificate-templates/
docker compose restart ept
```

Le script d'entrée exécute `composer post-update` à chaque démarrage du conteneur. Les migrations s'appliquent donc d'elles-mêmes. Exécutez les commandes de vérification ci-dessous dans le conteneur avec `docker compose exec ept <command>`.

## Sauvegardes avant une mise à jour {#backups-before-an-update}

Quand les sauvegardes sont activées, le script pose deux questions. La réponse par défaut est non pour les deux :

1. Sauvegarder la base de données. Choisissez les bases par leur numéro, ou tapez `all`. Les exports sont compressés avec gzip dans `/var/ept-backup/db/<database>_<timestamp>.sql.gz`.
2. Sauvegarder le dossier ePT. Chaque installation est copiée dans `/var/ept-backup/www/<folder>-backup-<timestamp>`.

Ces exports peuvent servir à une restauration ou à un changement de machine. Voir [Sauvegarde, restauration et migration](backup-and-migration.md).

## Vérifier la mise à jour {#verify-the-update}

L'exécution se termine par un bloc « Upgrade Summary ». Il liste les installations mises à jour avec succès et celles en échec. Vérifiez ensuite le serveur :

```bash
ept check
```

Un serveur en bon état affiche une ligne ✅ pour chaque contrôle, par exemple :

```text
✅ Database is reachable
✅ Schema is up to date — Version in sync: 7.7.1
```

Vérifiez le commit déployé :

```bash
cat /var/www/ept/VERSION.txt
```

Si quelque chose semble anormal, lisez le journal de l'exécution. Chaque exécution écrit dans `/tmp/ept-upgrade-<YYYYMMDD-HHMMSS>.log`.

Ouvrez ensuite le site dans un navigateur et connectez-vous.

## Corriger un écart de version {#fix-a-version-mismatch}

`ept check` affiche `Schema and code disagree` quand les migrations ne se sont pas appliquées entièrement. Exécutez-les de nouveau :

```bash
ept migrate
```

Cherchez dans la sortie l'instruction qui échoue. Les migrations sont idempotentes. Vous pouvez donc exécuter de nouveau une version appliquée en partie sans risque. Pour les options de migration, voir [Exécuter les migrations](cli-tools.md#run-migrations).

Si le message indique que la base de données est en avance sur le code, la mise à jour n'a pas livré le nouveau code. Exécutez de nouveau la mise à jour.

## Revenir en arrière {#roll-back}

Restaurez la sauvegarde du dossier faite avant la mise à jour :

```bash
sudo rsync -a --delete /var/ept-backup/www/ept-backup-<timestamp>/ /var/www/ept/
```

Restaurez ensuite l'export de base de données correspondant :

```bash
zcat /var/ept-backup/db/<database>_<timestamp>.sql.gz | mysql -u root -p <database>
```

Restaurez la base de données chaque fois que la mise à jour en échec a appliqué des migrations. L'ancien code seul ne peut pas fonctionner avec un schéma plus récent.

## Ce que change une mise à jour {#what-an-update-changes}

| Domaine | Changement |
| --- | --- |
| Code de l'application | Copié depuis un miroir git superficiel de `master` dans `/usr/local/lib/ept/src`. Les liens symboliques de l'installation sont conservés |
| Dépendances | `composer install` s'exécute seulement si `composer.json` ou `composer.lock` a changé. Les paquets Node sont installés pour le rendu des graphiques |
| Base de données | `composer post-update` exécute les migrations, puis régénère le fichier de sel s'il manque |
| Scripts à exécution unique | `bin/run-once.php` exécute les scripts en attente |
| Cron | L'entrée `cron.sh` exécutée chaque minute est ajoutée à la crontab de root si elle est absente |
| PHP | Passage à la version 8.4 si la machine utilise une autre version. OPcache est activé |
| MySQL | `mysqld.cnf` est réajusté selon la mémoire de la machine, puis MySQL redémarre |
| Paquets Ubuntu | Mis à jour, sauf si vous passez `-s` |

## Pour aller plus loin {#further-reading}

- [Référence des outils en ligne de commande](cli-tools.md) : chaque script de `bin/` et ses options
- [Sauvegarde, restauration et migration](backup-and-migration.md) : sauvegardes, restaurations et passage sur une nouvelle machine
- [Guide d'installation](setup.md) : nouvelle installation
