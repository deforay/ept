# Documentation ePT en français

Ces guides et référentiels concernent ePT 7.6.21. Ils s'appliquent à toutes les installations.
Les configurations locales, politiques approuvées et preuves de validation restent propres à chaque installation.

## Utilisation et exploitation

| Besoin | Document |
| --- | --- |
| Réaliser les tâches courantes des administrateurs et participants | [Guide utilisateur](user-guide.md) |
| Créer, modifier et examiner les accès | [Gestion des utilisateurs](user-management.md) |
| Examiner un incident opérationnel | [Dépannage](troubleshooting.md) |
| Comprendre les emplacements et règles de nettoyage | [Stockage et conservation](data-lifecycle.md) |
| Constituer le dossier d'une installation | [Remise du déploiement](deployment-handover.md) |

## Référentiels du système

| Besoin | Document |
| --- | --- |
| Examiner les exigences et cas d'utilisation | [Exigences fonctionnelles](functional-requirements.md) |
| Examiner les contrôles et exigences non fonctionnelles | [Sécurité](security.md) |
| Comprendre les entités et champs métier principaux | [Modèle de données et dictionnaire](data-model.md) |
| Consulter les types SQL, valeurs par défaut et règles de nullabilité | [Colonnes physiques](physical-schema.md) |
| Identifier les points d'entrée d'intégration | [Routes API](api-reference.md) |
| Examiner les versions verrouillées et métadonnées de licence | [Logiciels et licences](software-licenses.md) |
| Définir les tests d'acceptation et consigner leurs preuves | [Dossier de validation](validation.md) |

## Guides disponibles en anglais

Les guides antérieurs ci-dessous restent en anglais. Les liens correspondants dans les pages françaises le précisent.

| Besoin | Document en anglais |
| --- | --- |
| Comprendre le périmètre d'ePT | [Présentation](../about-ept.md) |
| Installer ePT | [Installation](../setup.md) |
| Mettre à jour une installation | [Mise à jour](../updating.md) |
| Sauvegarder, restaurer ou déplacer une installation | [Sauvegarde et migration](../backup-and-migration.md) |
| Dimensionner l'infrastructure | [Infrastructure](../infrastructure.md) |
| Vérifier l'état d'un serveur ou lancer la maintenance avec la commande `ept` | [Outils CLI](../cli-tools.md#the-ept-command) |
| Utiliser les commandes d'administration | [Outils CLI](../cli-tools.md) |
| Examiner la conception technique | [Architecture](../ARCHITECTURE.md), [programmes](../SchemeArchitecture.md), [module administrateur](../AdminModuleGuide.md) |
| Suivre le parcours de formation | [Formation](../training/README.md) |
| Maintenir la documentation et les traductions | [Règles de développement](../engineering-standards.md), [traduction](../TranslationGuide.md) |

## Programmes pris en charge {#supported-test-schemes}

La table `scheme_list` contient sept programmes intégrés et les tests personnalisés.
La colonne centrale reprend les noms anglais dans ePT.

| Code | Nom dans ePT | Description |
| --- | --- | --- |
| `dts` | Dried Tube Specimen - HIV Serology | Tests rapides du VIH selon plusieurs algorithmes |
| `vl` | Dried Tube Specimen - HIV Viral Load | Mesure quantitative de la charge virale avec analyse du score Z |
| `eid` | Dried Blood Spot - Early Infant Diagnosis | Diagnostic du VIH chez le nourrisson par PCR |
| `tb` | Dried Tube Specimen - Tuberculosis | Tests moléculaires (GeneXpert) et microscopie |
| `recency` | Rapid Test for Recent Infection (RTRI) | Tests d'infection récente |
| `covid19` | SARS-CoV-2 | Tests PCR sur plusieurs plateformes |
| `dbs` | Dried Blood Spot - HIV Serology | Tests EIA et Western Blot |
| `generic` | Custom Tests | Types de tests configurables avec champs dynamiques |
