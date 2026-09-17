# Comment préparer la remise d'un déploiement

[English](../deployment-handover.md)

Constituez un dossier de preuves versionné pour une installation ePT.

## Avant de commencer

Utilisez un compte opérateur autorisé à examiner l'installation cible.
Ce guide concerne ePT 7.6.21 avec PHP 8.4 et MySQL 8 ou ultérieur.
Obtenez les noms du responsable du programme, du responsable de l'hébergement et du relecteur chargé de l'acceptation.

## Enregistrer la version de référence

1. Exécutez le contrôle de version depuis le répertoire d'installation :

   ```bash
   php bin/check-version-sync.php
   ```

2. Relevez l'identifiant de déploiement dans `VERSION.txt`, si ce fichier existe.
3. Pour une copie Git, relevez `git rev-parse HEAD` et `git status --short`.
4. Relevez les versions du système d'exploitation, de PHP, de MySQL et du serveur web.
5. Relevez les programmes actifs, algorithmes, paramètre d'instance, fuseau horaire et présentations des rapports.

Excluez les secrets de la copie remise. Décrivez leur fonction au lieu de reproduire les mots de passe,
jetons, clés de chiffrement ou identifiants de connexion.

## Capturer le schéma physique

Utilisez un répertoire de sortie à accès restreint et un compte de base autorisé à exporter le schéma.
Remplacez `ept_database` par le nom réel de la base.

```bash
mysqldump --no-data --skip-comments --no-tablespaces \
  --user=SCHEMA_READER --password ept_database > ept-schema.sql
```

Examinez l'export avant de le partager. Joignez la version de la base, la date de capture et la version applicative.
Si nécessaire, ajoutez les définitions des vues, routines ou événements utilisés par l'installation mais absents de l'export.

Utilisez le [dictionnaire principal](data-model.md) pour expliquer les entités principales.
Consignez les définitions de champs non résolues comme des lacunes, sans en deviner le sens.

## Constituer le dossier

| Livrable | Base existante | Preuve d'achèvement |
| --- | --- | --- |
| Exigences fonctionnelles | [Référentiel des exigences](functional-requirements.md) | Revue du programme et différences locales approuvées |
| Conception technique | [Architecture, en anglais](../ARCHITECTURE.md), [modèle de données](data-model.md), [inventaire API](api-reference.md) | Diagramme réel du déploiement, schéma et contrats des intégrations activées |
| Exigences de sécurité | [Référentiel de sécurité](security.md) | Preuves des contrôles locaux et résultats des évaluations |
| Manuel utilisateur | [Guide utilisateur](user-guide.md) et formation | Navigation locale et captures d'écran examinées |
| Administration des utilisateurs | [Gestion des utilisateurs](user-management.md) | Attributions approuvées et registre de revue des accès |
| Installation et maintenance | [Installation, en anglais](../setup.md), [mise à jour, en anglais](../updating.md), [dépannage](troubleshooting.md) | Opérateurs désignés et calendrier de maintenance |
| Reprise | [Sauvegarde et migration, en anglais](../backup-and-migration.md) | Inventaire des sauvegardes, emplacement hors serveur et exercice de restauration réussi |
| Validation | [Dossier de validation](validation.md) | Cas exécutés, anomalies, nouveaux tests et approbation |
| Inventaire logiciel | [Logiciels et licences](software-licenses.md) | Versions réellement installées et ressources fournies localement |
| Code source et livraison | Référence versionnée du dépôt | Commit déployé, résumé des changements et problèmes connus |
| Formation | [Parcours, en anglais](../training/README.md) | Dates, formateurs, présences et contrôles des compétences |

## Compléter les documents opérationnels locaux

1. Relevez les ressources du serveur ou de la machine virtuelle, les volumes de stockage et les limites réseau.
2. En cas de virtualisation, relevez la configuration de l'hyperviseur et des sauvegardes des machines virtuelles.
3. Désignez les responsables de la surveillance des sauvegardes, des restaurations et de l'escalade.
4. Convenez des objectifs de temps et de point de reprise avec le responsable du programme.
5. Consignez une procédure de continuité pour recueillir les réponses pendant une panne.
6. Définissez le rapprochement des données recueillies pendant la panne après la reprise du service.
7. Obtenez la politique approuvée de conservation et d'élimination auprès du responsable des données.
8. Joignez les contacts d'assistance, accords de service, dates des contrats et responsabilités de renouvellement.
9. Joignez le plan de déploiement, l'évaluation du site et l'inventaire des établissements.

## Vérifier la remise

1. Exécutez les [cas d'acceptation](validation.md) convenus dans l'environnement de test cible.
2. Vérifiez chaque document par rapport à la version enregistrée et à la configuration locale.
3. Attribuez un responsable et une date cible à chaque document manquant.
4. Obtenez les décisions d'acceptation des responsables du programme et de l'hébergement.

Placez le dossier terminé sous contrôle documentaire local.
Pour chaque document, indiquez une révision, un responsable, une date de revue et un statut d'approbation.
