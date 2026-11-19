---
title: Participants
summary: Add, view, edit, and manage your PT participants
tags: [participants, labs, data-managers, mapping]
---

# Participants

La page Participants liste tous les laboratoires, cliniques ou
personnes inscrits à votre programme PT. Ici, vous pouvez ajouter
des participants un par un, en importer beaucoup en un seul
téléversement, modifier les fiches existantes et voir comment les
participants sont associés à leurs gestionnaires de données.

## Contenu de la page

L'en-tête de la page propose trois actions principales, à droite :

- **Ajouter un nouveau participant** : ouvre le formulaire pour un
  seul participant. Utilisez-le pour ajouter une ou deux fiches.
- **Importation groupée des participants** : recommandé pour toute
  liste importante. Téléversez un fichier Excel et le système
  ajoute ou met à jour les lignes en une seule fois.
- Menu **Exporter** :
  - **Participants** : exporte toute la liste (avec les filtres
    appliqués) au format `.xlsx`.
  - **E-mails inutilisables** : télécharge une liste Excel des
    comptes dont l'adresse e-mail manque, a été rejetée ou ne peut
    pas recevoir de messages.

S'il existe des participants à l'état *En attente*, un bouton
jaune **Afficher uniquement les participants en attente**
s'affiche avec leur nombre. Cliquez dessus pour ne voir que ces
lignes.

## Le tableau des participants

Chaque ligne affiche l'ID du participant, son nom, son pays, ses
coordonnées, son affiliation, son statut et les gestionnaires de
données qui lui sont associés.

- Des zones **Rechercher** se trouvent sous chaque titre de
  colonne. Saisissez du texte pour filtrer la liste par ID, nom,
  pays, portable, téléphone, affiliation, e-mail ou statut. Les
  recherches se cumulent : vous pouvez les combiner.
- La colonne **Action** contient les boutons Modifier et Supprimer
  de chaque ligne.

### Colonne Gestionnaires de données : voir les gestionnaires associés

Cliquez sur **Voir** dans la colonne Gestionnaires de données pour
ouvrir une ligne qui affiche tous les gestionnaires de données
associés à ce participant. Vous pouvez alors :

- Voir pour chaque gestionnaire de données son nom, son
  établissement, son adresse e-mail principale, son type et son
  statut.
- **Réinitialiser le mot de passe** d'un gestionnaire de données,
  sans quitter la page.
- **Modifier l'adresse e-mail principale** d'un gestionnaire de
  données, sans quitter la page.

Quand plus de cinq gestionnaires de données sont associés, une
petite zone de filtre s'affiche au-dessus de la liste. Elle permet
de chercher parmi les gestionnaires de données de ce seul
participant.

Les lignes **sans gestionnaire de données associé** sont
surlignées en jaune pâle, mais seulement quand le participant est
*actif*. Rien n'est à faire pour les participants inactifs sans
association.

## Filtre d'association

Au-dessus du tableau se trouve une liste déroulante
**Association** :

- **Tout** : affiche tous les participants (par défaut).
- **Associé à un gestionnaire de données** : seulement les
  participants associés à au moins un gestionnaire de données.
- **N'est associé à aucun gestionnaire de données** : seulement
  les participants sans gestionnaire de données. Ce sont les
  lignes surlignées en jaune, celles qu'il faut le plus souvent
  corriger. Un participant actif sans gestionnaire de données ne
  peut pas recevoir d'expéditions ni répondre aux panels PT.

Le filtre se combine avec les recherches par colonne. Par exemple,
pour voir *tous les participants actifs sans association au
Kenya*, combinez la recherche par pays avec **N'est associé à aucun
gestionnaire de données**.

## Conseils

- Pour plus de quelques nouveaux participants, utilisez
  **Importation groupée des participants** au lieu de les ajouter
  un par un. C'est plus rapide et vous obtenez une seule liste de
  ce qui a été enregistré, ignoré ou refusé.
- Utilisez régulièrement le filtre d'association pour repérer les
  participants qui ont perdu leur gestionnaire de données (par
  exemple après la suppression ou la désactivation d'un
  gestionnaire de données).
- Le téléchargement Excel tient compte des recherches par colonne
  mais ignore le filtre d'association. Ajustez les recherches par
  colonne pour limiter l'export.
