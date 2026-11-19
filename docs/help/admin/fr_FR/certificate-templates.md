---
title: Certificate Templates
summary: The designs annual certificates print on, the details ePT adds and the checks on upload
tags: [certificates, templates, design, designer, pdf, fields, excellence, participation]
---

# Modèles de certificats

**Configurer → Modèles de certificats** contient un modèle Excellence et un modèle Participation par programme. ePT imprime chaque certificat annuel à partir de ces modèles.

[Voir : Comment configurer un modèle de certificat](guides/make-a-certificate-template.md)

## Types de modèles

| Modèle | Utilisé pour |
|---|---|
| Excellence | Les participants qui ont répondu à toutes les expéditions du programme dans le lot et les ont toutes réussies |
| Participation | Les participants qui ont répondu à toutes les expéditions du programme sans toutes les réussir |

Un participant qui a manqué une expédition du programme ne reçoit aucun certificat pour ce programme.

## Sortes de modèles

| Sorte | Description | Comment ePT imprime les informations |
|---|---|---|
| Maquette | Un PDF ou une image du certificat, téléversé sans champs de formulaire | Dans les cadres placés dans l'éditeur |
| Formulaire PDF | Un PDF avec un champ de formulaire nommé `participant_name` | Dans les champs de formulaire du PDF |

La carte du modèle indique la sorte après le nom du fichier.

## États des modèles

| État | Signification |
|---|---|
| Prêt | Les certificats s'impriment à partir de ce modèle. |
| Informations à placer | Une maquette sans cadre Nom du participant. Les certificats ne s'impriment pas tant que ce cadre n'est pas placé. |
| Vérifier les champs | Un formulaire PDF dont certains champs ne sont pas remplis par ePT. Ces champs s'impriment tels qu'ils sont dans le modèle. |
| Manquant | Aucun modèle. Le programme ne reçoit aucun certificat de ce type. |

## Informations

Chaque information a un nom dans l'éditeur et un nom de champ pour les formulaires PDF.

| Nom dans l'éditeur | Champ du formulaire PDF | Anciens noms de champ | Valeur | Source |
|---|---|---|---|---|
| Nom du participant | `participant_name` | `participantname`, `labname`, `participant` | Le nom du laboratoire. Pour les participants individuels, le prénom et le nom. | Fiche du participant |
| ID du laboratoire | `lab_id` | Aucun | L'ID du laboratoire | Fiche du participant |
| Ville | `city` | Aucun | La ville | Fiche du participant |
| Pays | `country` | Aucun | Le nom du pays | Fiche du participant |
| Nom du programme | `scheme_name` | Aucun | Le nom du programme, par exemple « HIV Viral Load » | Programme |
| Technique | `assay` | `assayname` | La technique de charge virale ou la technique d'extraction EID. Vide pour les autres programmes. | La dernière réponse du participant dans le lot |
| Année des expéditions | `shipment_year` | `shipmentyear` | L'année de la dernière expédition du programme pour le participant dans le lot | Date de l'expédition |
| Date d'émission | `issue_date` | Aucun | La date de génération du lot, au format de date défini dans Paramètres globaux | Date de génération |
| Nom du signataire | `signatory_name` | Aucun | **Nom du signataire des certificats** | Configurer → Paramètres globaux |
| Fonction du signataire | `signatory_title` | Aucun | **Fonction du signataire des certificats** | Configurer → Paramètres globaux |

Le Nom du participant est obligatoire. Toutes les autres informations sont facultatives.

Dans un formulaire PDF, les noms de champ ne tiennent pas compte de la casse. Un champ de formulaire portant un autre nom garde le texte que lui donne le modèle.

## Cadres de l'éditeur

Chaque cadre contient du texte : des informations, un texte fixe ou les deux. Une information apparaît dans le texte sous la forme de son nom de champ entre accolades, par exemple `{participant_name}`.

| Réglage | Valeurs | Par défaut |
|---|---|---|
| Police | Sans empattement, Avec empattement, Sans empattement, toutes langues, Avec empattement, toutes langues | Sans empattement |
| Taille | 4 à 120 points | 28 pour Nom du participant, 14 pour les autres informations |
| Style | Gras, italique | Gras pour Nom du participant |
| Couleur | Toute couleur | Noir |
| Alignement | Gauche, centre, droite | Centre |
| Si le texte est trop long | Le réduire, ou Le répartir sur plusieurs lignes | Le réduire |

**Le réduire** diminue le texte jusqu'à ce qu'il tienne dans la largeur du cadre, sans descendre sous 60 % de la taille choisie. Un texte encore trop long à cette taille passe sur plusieurs lignes, centrées dans le cadre.

**Le répartir sur plusieurs lignes** fait passer le texte à la ligne dans le cadre et le réduit jusqu'à ce qu'il tienne dans la hauteur du cadre.

Les polices Sans empattement et Avec empattement ne contiennent que les caractères d'Europe occidentale. Un texte avec d'autres caractères, par exemple vietnamiens ou cyrilliques, s'imprime dans la police correspondante « toutes langues ».

La liste **Affichage** de l'éditeur contient le participant de la dernière expédition évaluée du programme et le participant au nom le plus long. **Aperçu PDF** imprime une page pour chacun, avec les modifications non enregistrées.

## Contrôles au téléversement

| Fichier téléversé | Résultat |
|---|---|
| Un PDF avec un champ texte `participant_name` qu'ePT peut remplir | Enregistré comme formulaire PDF |
| Tout autre PDF qu'ePT peut lire | Enregistré comme maquette. L'éditeur s'ouvre. |
| Une image PNG ou JPG | Convertie en PDF d'une page au format A4 et enregistrée comme maquette. L'éditeur s'ouvre. |
| Un PDF qu'ePT ne peut pas lire directement | Réécrit avec pdftk ou Ghostscript, puis enregistré comme maquette |
| Un PDF endommagé ou protégé par mot de passe, ou tout autre fichier | Refusé |

Remplacer une maquette conserve ses cadres.

La génération des certificats contrôle à nouveau chaque modèle. Un modèle qui échoue est indiqué dans le message d'erreur du lot.

## Actions de la carte du modèle

| Action | Résultat |
|---|---|
| Placer les informations | Ouvre l'éditeur. Maquettes uniquement. |
| Aperçu | Ouvre le modèle rempli avec des participants d'exemple |
| Voir | Ouvre le modèle vierge |
| Remplacer | Téléverse un nouveau fichier à la place du fichier actuel, après les contrôles |
| Supprimer | Supprime le modèle et ses cadres |

## Prérequis

Les formulaires PDF nécessitent `pdftk` sur le serveur. Les maquettes ne nécessitent rien de plus. Ghostscript permet à ePT de lire davantage de PDF au téléversement.
