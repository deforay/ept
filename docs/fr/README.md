# Documentation ePT

ePT est un système open source d'essais d'aptitude (PT) pour l'assurance qualité des laboratoires. Les organisations l'utilisent pour organiser des expéditions PT, recueillir les résultats des laboratoires participants, évaluer ces résultats par rapport aux données de référence et publier des rapports.

## Par où commencer {#start-here}

| J'ai besoin de | Page |
| --- | --- |
| Soumettre des résultats PT ou trouver mon rapport | [Soumettre des résultats en tant que gestionnaire de données](user-guide.md#submit-results-as-a-data-manager) |
| Mener un cycle PT du début à la fin | [Formation](training/README.md) |
| Résoudre un problème signalé | [Dépannage](troubleshooting.md) |

## Toute la documentation {#all-documentation}

### Utiliser ePT {#using-ept}

| Votre objectif | Page |
| --- | --- |
| Comprendre à quoi sert ePT | [À propos d'ePT](about-ept.md) |
| Réaliser les tâches courantes des administrateurs et participants | [Guide utilisateur](user-guide.md) |
| Apprendre tout le processus d'administration | [Formation](training/README.md) |
| Créer, modifier et examiner les accès | [Gestion des utilisateurs](user-management.md) |
| Résoudre un problème signalé | [Dépannage](troubleshooting.md) |

### Exploiter un serveur {#running-a-server}

| Votre objectif | Page |
| --- | --- |
| Dimensionner le matériel avant l'achat | [Infrastructure](infrastructure.md) |
| Installer ePT sur Ubuntu, Docker ou Windows | [Installation](setup.md) |
| Mettre à jour une installation en service | [Mise à jour](updating.md) |
| Sauvegarder, restaurer ou déplacer une installation | [Sauvegarde et reprise](backup-and-migration.md) |
| Assurer l'envoi des e-mails et le traitement des rejets | [Envoi des e-mails](email.md) |
| Vérifier l'état d'un serveur ou lancer la maintenance avec la commande `ept` | [Outils CLI](cli-tools.md#the-ept-command) |
| Réinitialiser un mot de passe, exécuter une migration, actualiser les traductions | [Outils CLI](cli-tools.md) |
| Réparer des tables ou colonnes manquantes | [Réparer les tables manquantes](heal-schema.md) |
| Comprendre les emplacements de stockage et les règles de nettoyage | [Stockage et conservation](data-lifecycle.md) |
| Constituer le dossier de preuves d'un déploiement | [Remise du déploiement](deployment-handover.md) |

### Référentiels du système {#system-reference}

Ces pages décrivent ePT 7.6.21. Les informations de déploiement, les politiques approuvées et les preuves de validation restent propres à chaque installation.

| Votre objectif | Page |
| --- | --- |
| Examiner les exigences fonctionnelles et les cas d'utilisation | [Exigences fonctionnelles](functional-requirements.md) |
| Examiner les contrôles de sécurité et les exigences non fonctionnelles | [Sécurité](security.md) |
| Comprendre les entités et champs métier principaux | [Modèle de données et dictionnaire](data-model.md) |
| Consulter les types SQL, valeurs par défaut et règles de nullabilité | [Colonnes physiques](physical-schema.md) |
| Identifier les routes d'intégration | [Routes API](api-reference.md) |
| Examiner les versions verrouillées et les métadonnées de licence | [Logiciels et licences](software-licenses.md) |
| Préparer les tests d'acceptation et consigner leurs preuves | [Dossier de validation](validation.md) |

### Développement {#development}

| Votre objectif | Page |
| --- | --- |
| Comprendre l'organisation du code | [Architecture](ARCHITECTURE.md) |
| Ajouter ou modifier un programme | [Programmes](SchemeArchitecture.md) |
| Traduire l'interface ou ajouter une langue | [Traduction](TranslationGuide.md) |
| Faire relire et fusionner une modification | [Normes d'ingénierie](engineering-standards.md) |

## Programmes pris en charge {#supported-test-schemes}

La table `scheme_list` contient sept programmes intégrés. Vous pouvez y ajouter autant de tests personnalisés que nécessaire.
La colonne centrale reprend le nom affiché dans l'interface d'administration et le portail participant.

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

## Parcours de formation {#training-curriculum}

| Document | Public | Durée |
| --- | --- | --- |
| [Vue d'ensemble](training/README.md) | Tous | — |
| [Partie 1 : Configuration et participants](training/part1-setup-and-participants.md) | Administrateur | ~10 min |
| [Partie 2 : Enquêtes et expéditions](training/part2-surveys-and-shipments.md) | Administrateur | ~10 min |
| [Partie 3 : Résultats et rapports des participants](training/part3-participant.md) | Participant | ~5 à 10 min |
| [Partie 4 : Évaluation, rapports et finalisation](training/part4-evaluation-and-reports.md) | Administrateur | ~10 min |
| [Exercice : sérologie du VIH sur DTS](training/exercises/dts-hiv-serology.md) | Tous | ~20 à 30 min |

## Fichiers de configuration principaux {#key-configuration-files}

| Fichier | Rôle |
| --- | --- |
| `application/configs/application.ini` | Base de données, sessions, modules |
| `constants.php` | Chemins globaux et `APP_VERSION` |

Pour la pile technique et le dimensionnement du matériel, consultez [Infrastructure](infrastructure.md). Pour l'arborescence des répertoires, consultez [Architecture](ARCHITECTURE.md#directory-map). Pour les conventions de documentation et de code, consultez les [normes d'ingénierie](engineering-standards.md).

## Assistance {#support}

Écrivez à [amit@deforay.com](mailto:amit@deforay.com) ou ouvrez un ticket sur [deforay/ept](https://github.com/deforay/ept).
