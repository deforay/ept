# Référentiel de stockage et de conservation

Ce référentiel décrit le stockage et le nettoyage implémentés dans ePT 7.6.21.
Les valeurs logicielles par défaut ne constituent pas une politique approuvée de conservation ou d'élimination du programme.

## Emplacements de stockage

Sauf indication contraire, les chemins sont relatifs au répertoire d'installation.

| Emplacement | Contenu | Enjeu de reprise ou de confidentialité |
| --- | --- | --- |
| Base MySQL | Comptes, participants, expéditions, réponses, paramètres et files | Nécessite une sauvegarde de la base et une restauration testée |
| `application/configs/application.ini` | Configuration d'exécution et secrets de connexion | Nécessite une sauvegarde restreinte et la conservation des paramètres |
| `application/configs/config.ini` | Valeurs de configuration applicative par défaut | Doit correspondre à l'installation restaurée |
| `application/configs/.env` | Secrets d'environnement lorsqu'ils sont configurés | Nécessite une sauvegarde restreinte |
| `public/uploads/` | Fichiers téléversés et fichiers justificatifs générés par l'application | À inclure dans les sauvegardes de fichiers |
| `public/uploads/track-api/` | Corps compressés des requêtes et réponses API | Peuvent contenir des identifiants ou des jetons |
| `downloads/` | Rapports générés | La reprise doit préserver les sorties requises ou prévoir leur régénération |
| `public/temporary/` | Exports temporaires et fichiers de travail | Soumis au nettoyage |
| `logs/` | Journaux applicatifs | Peuvent contenir des informations opérationnelles et de compte |
| `backups/` | Destination par défaut des archives de la base | Le stockage sur le même serveur ne protège pas contre sa perte |
| `backups/config/` | Copies de `application.ini` | Contiennent des secrets et ne constituent pas une sauvegarde complète de la configuration |

Les emplacements des rapports sont décrits dans l'[architecture des programmes, en anglais](../SchemeArchitecture.md#file-output-locations).
Les chemins configurés et les destinations hors serveur doivent figurer dans le dossier de déploiement.

## Opérations planifiées

La planification utilise le fuseau horaire de l'application et dépend du bon fonctionnement de cron/Crunz.
La présence des définitions ne prouve pas la réussite des tâches.

| Opération | Planification définie | Source |
| --- | --- | --- |
| Sauvegarde de la base | Chaque jour à 00:45 | `scheduled-jobs/ScheduledTasks.php` |
| Copie de configuration | Le dimanche à 01:00 | `bin/backup-config.php --quiet` |
| Nettoyage | Chaque jour à 03:30 | `bin/housekeeping.php --quiet` |
| Purge des journaux binaires | Chaque jour à 04:05, avec un paramètre de 7 jours | `db-tools purge-binlogs --days=7` |

La rétention par défaut de `db-tools.php` est de 15 archives de base de données.
Les copies de configuration sont conservées au nombre de 26 par défaut, avec un seuil minimal de 4.
L'outil ne crée pas de nouvelle copie lorsque le fichier est inchangé.

## Règles de nettoyage

| Cible | Éligibilité | Limite de conservation |
| --- | --- | --- |
| `track_api_requests` | Métadonnées des requêtes | 90 jours selon `requested_on` |
| `temp_mail` | Statuts terminaux `sent`, `failed`, `failure`, `fail` | 30 jours selon l'horodatage disponible d'envoi, de mise à jour ou de mise en file |
| `push_notification` | Statut nul ou différent de `pending` | 90 jours selon `created_on` |
| `audit_log` | Activité enregistrée ancienne | 730 jours avant le `created_on` le plus récent de la table |
| `user_login_history` | Activité de connexion ancienne | 730 jours avant le dernier horodatage de connexion de la table |
| `logs/*.log` | Fichiers journaux correspondants à la racine du répertoire | 30 jours selon la date de modification |
| `public/temporary/` | Éléments temporaires du système de fichiers | 7 jours selon la date de modification |

Les limites d'audit utilisent la ligne la plus récente de chaque table, pas la date actuelle.
Une installation inactive conserve donc sa dernière période d'activité.
Le nettoyage ne cible ni les tables de réponses PT ni les répertoires des rapports générés.
La suppression des métadonnées des requêtes API ne supprime pas les fichiers de contenu correspondants.

## Décisions de politique hors du code

Le responsable des données définit les durées de conservation des données PT, rapports, comptes, contenus API, sauvegardes et preuves d'audit.
La politique doit aussi couvrir les obligations de gel de suppression, l'autorisation d'élimination,
la vérification de suppression et les copies hors serveur.

Les procédures de reprise figurent dans [sauvegarde et migration, en anglais](../backup-and-migration.md).
Le [guide de remise](deployment-handover.md) consigne les responsables et les objectifs de reprise.
