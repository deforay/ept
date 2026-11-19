---
title: Map Participants to Data Managers
summary: Assign participants to a Data Manager so the right person sees the right participants when filling out responses
tags: [participants, data-manager, mapping, pt-login, bulk-import]
---

# Associer des participants aux gestionnaires de données

Cette page associe des **participants** à un **Gestionnaire de
données (connexion des participants)**. Un gestionnaire de données
se connecte à l'espace participant de l'application. Il voit
seulement les participants qui lui sont associés. Cette page
décide donc qui saisit les résultats de quels participants.

Le même écran s'ouvre dans une fenêtre depuis le bouton
**Associer** de la liste des gestionnaires de données. Dans la
fenêtre, le gestionnaire de données est déjà choisi et son nom
s'affiche dans le titre.

## Boutons en haut de la page

Ces boutons ne sont pas dans la fenêtre.

- **Importation groupée des associations** : ouvre une fenêtre
  pour téléverser un fichier contenant de nombreuses associations.
  Voir *Importation groupée* plus bas.
- **Participants non associés** : télécharge la liste de tous les
  participants **associés à aucun gestionnaire de données**.
  Personne ne peut saisir de résultats pour ces participants.

## Étape 1 : choisir un gestionnaire de données

Saisissez un nom ou une adresse e-mail dans **Gestionnaire de
données (connexion des participants)**. Les listes se chargent dès
que vous en choisissez un.

**Pays**, **Province** et **District** filtrent les participants de
gauche. Cliquez sur **Plus de filtres** pour **Établissement**,
**Niveau du réseau** et **Affiliation**. Le nombre sur le bouton
indique combien de ces filtres sont utilisés.

Les filtres ne s'appliquent qu'à la liste **Non associés**. Les
participants déjà associés restent à droite dans tous les cas.
Changer un filtre conserve les modifications pas encore
enregistrées.

## Étape 2 : déplacer les participants

- **Non associés** (à gauche) : participants actifs que ce
  gestionnaire de données ne voit pas.
- **Associés** (à droite) : participants actifs que ce
  gestionnaire de données voit.

Cliquez sur un participant pour le passer dans l'autre liste.
Chaque ligne affiche l'ID du laboratoire et le nom. Saisissez du
texte dans la zone de recherche d'une liste pour trouver un
participant par nom ou par ID du laboratoire.

- **Ajouter tous ceux affichés** : passe à droite tous les
  participants affichés par la recherche de gauche.
- **Retirer tous ceux affichés** : passe à gauche tous les
  participants affichés par la recherche de droite.

Si la zone de recherche est vide, ces boutons déplacent toute la
liste.

Les couleurs montrent ce que l'enregistrement va faire :

| Marque | Signification |
| --- | --- |
| Coche grise | Associé et enregistré |
| **+** vert | Sera ajouté à l'enregistrement |
| **−** orange | Sera retiré à l'enregistrement |

Les lignes non enregistrées s'affichent en premier, pour que vous
les voyiez toujours. Les nombres à côté du titre de chaque liste
indiquent combien de participants seront ajoutés ou retirés.

## Étape 3 : enregistrer

La barre en bas de la page indique combien de participants seront
ajoutés et retirés.

- **Enregistrer les modifications** : enregistre la liste Associés
  de ce gestionnaire de données.
- **Abandonner** : annule vos modifications non enregistrées et
  recharge les listes.
- **Fermer** *(fenêtre seulement)* : ferme la fenêtre.

Si vous choisissez un autre gestionnaire de données, fermez la
fenêtre ou quittez la page avec des modifications non
enregistrées, la page vous demande d'abord confirmation.

Enregistrer avec une liste **Associés** vide retire tous les
participants de ce gestionnaire de données. La page vous demande
de confirmer.

Les associations à des participants **inactifs** ne s'affichent
pas dans les listes. L'enregistrement les conserve.

## Importation groupée

Cliquez sur **Importation groupée des associations** et choisissez
un fichier Excel ou CSV. La ligne 1 est l'en-tête et elle est
ignorée. Chaque autre ligne associe un participant à un
gestionnaire de données :

| Colonne | Contenu |
| --- | --- |
| B | ID du laboratoire (obligatoire) |
| C | Nom du gestionnaire de données |
| D | Adresse e-mail principale du gestionnaire de données (obligatoire) |
| E | Adresse e-mail secondaire |
| F | Portable |

Si aucun gestionnaire de données n'a l'adresse e-mail de la
colonne D, un nouveau gestionnaire de données est créé avec le mot
de passe par défaut. L'importation groupée ajoute seulement des
associations. Elle n'en retire jamais.

## Conseils

- **Consultez Participants non associés avant une expédition.**
  C'est le moyen le plus rapide de trouver les participants pour
  lesquels personne ne peut saisir de résultats.
- **Utilisez les filtres pour faire le tri par pays ou par
  établissement.** Filtrez, recherchez, puis utilisez **Ajouter
  tous ceux affichés**.
- Un participant peut être associé à **plusieurs gestionnaires de
  données**. Ajoutez-le dans la liste de chaque gestionnaire de
  données.
