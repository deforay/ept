# Documentation ePT

ePT est un système open source d'essais d'aptitude (PT). Un fournisseur PT l'utilise pour envoyer des panels aux laboratoires, recueillir leurs résultats, les noter et renvoyer un rapport à chaque laboratoire.

## Par où commencer {#start-here}

Choisissez la carte qui vous correspond.

<div class="grid cards" markdown>

-   :material-flask-outline:{ .lg .middle } __Je teste des panels PT dans un laboratoire__

    ---

    Soumettez vos résultats et téléchargez votre rapport.

    - [Soumettre des résultats](user-guide.md#submit-results-as-a-data-manager)
    - [Trouver votre rapport](user-guide.md#find-reports-and-account-information)
    - [Connexion impossible ou expédition introuvable ?](troubleshooting.md#fix-problems-participants-report)

-   :material-clipboard-check-outline:{ .lg .middle } __J'organise des campagnes PT__

    ---

    Préparez les participants, expédiez les panels, évaluez les résultats et envoyez les rapports.

    - [Formation](training/README.md)
    - [Guide utilisateur](user-guide.md)
    - [Gérer les accès des utilisateurs](user-management.md)

-   :material-server-outline:{ .lg .middle } __Je gère le serveur__

    ---

    Installez, mettez à jour, sauvegardez et maintenez ePT en service.

    - [Installation](setup.md)
    - [Mise à jour](updating.md)
    - [Sauvegarde et reprise](backup-and-migration.md)
    - [Dépannage](troubleshooting.md)

-   :material-code-tags:{ .lg .middle } __Je travaille sur le code__

    ---

    Découvrez comment ePT est construit et comment le modifier.

    - [Architecture](ARCHITECTURE.md)
    - [Programmes](SchemeArchitecture.md)
    - [Normes d'ingénierie](engineering-standards.md)

</div>

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

## Assistance {#support}

Écrivez à [amit@deforay.com](mailto:amit@deforay.com) ou ouvrez un ticket sur [deforay/ept](https://github.com/deforay/ept).
