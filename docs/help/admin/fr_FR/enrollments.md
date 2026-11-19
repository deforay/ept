---
title: Scheme Enrollments
summary: See every enrollment list per scheme, create and edit lists, and clone, archive, rename, merge or delete them
tags: [enrollment, scheme, participants, lists, bulk-import]
---

# Inscriptions au programme

Une **liste d'inscription** est un groupe nommé de participants pour
un programme. Un participant est inscrit à un programme lorsqu'il
figure sur au moins une de ses listes.

Chaque programme a une liste **Par défaut**, marquée *Principale*.
Le formulaire du participant l'y ajoute quand vous cochez un
programme pour ce participant. Les autres listes sont des listes que
vous tenez pour un cycle, une région ou une expédition, par exemple
*Sites de test du cycle 3*. Ship It propose vos listes comme
présélections quand vous choisissez les destinataires d'une
expédition.

Chaque nom de liste ne peut servir que dans **un seul programme**,
car Ship It propose les listes par leur nom. Chaque programme a sa
propre liste Par défaut : c'est la seule exception. ePT refuse un
nouveau nom, un changement de nom, une duplication ou une importation
qui réutiliserait le nom de liste d'un autre programme. Les listes
qui partageaient un nom avant cette règle portent la mention *Name
also used in another scheme*. Renommez l'une d'elles.

Les listes des programmes inactifs ne sont affichées ni sur cette
page ni dans Ship It. Leurs noms peuvent être réutilisés.

## Boutons en haut de la page

- **Nouvelle liste** : choisissez un programme, saisissez un nom de
  liste, puis choisissez les participants. Laissez le nom vide pour
  ouvrir la liste Par défaut du programme.
- **Importation en masse d'inscriptions** : remplit une liste à
  partir d'un fichier Excel. Vous voyez ce qui va changer avant tout
  enregistrement.

La zone **Programme** à côté des onglets affiche un seul programme
dans les deux onglets.

## Onglet Listes

Les listes sont regroupées sous leur programme. L'en-tête de chaque
programme indique combien de participants actifs y sont inscrits, et
propose un lien **Nouvelle liste** pour ce programme.

| Colonne | Signification |
| --- | --- |
| Liste | Le nom de la liste |
| Participants | Les participants actifs sur la liste |
| Dernière modification | La date du dernier ajout d'un participant |

Chaque liste a ces boutons :

- **Modifier** : ouvre la liste pour ajouter ou retirer des
  participants.
- **Plus** : les autres actions sur la liste.
  - **Importer depuis un fichier** : ouvre l'Importation en masse
    d'inscriptions pour cette liste.
  - **Télécharger** : un fichier Excel de la liste. Il utilise la
    structure d'importation : vous pouvez le modifier et le
    téléverser de nouveau.
  - **Dupliquer la liste** : crée une nouvelle liste avec les mêmes
    participants.
  - **Renommer**, **Fusionner dans une autre liste**, **Archiver** et
    **Supprimer**. La liste Par défaut n'a pas ces boutons.

### Dupliquer une liste

Utilisez **Dupliquer la liste** pour créer une nouvelle liste à
partir d'une liste existante, par exemple la liste du prochain cycle
à partir de celle du cycle en cours.

1. Ouvrez **Plus** sur la liste et cliquez sur **Dupliquer la
   liste**.
2. Choisissez le programme de la nouvelle liste. Ce peut être le même
   programme ou un autre.
3. Saisissez un nom pour la nouvelle liste et cliquez sur
   **Dupliquer**. Le nom ne peut pas être déjà utilisé par une autre
   liste.

La nouvelle liste contient les participants actifs de la liste
d'origine. Elle s'ouvre pour que vous puissiez ajouter ou retirer des
participants. La liste d'origine ne change pas.

### Archiver, renommer, fusionner et supprimer

- **Archiver** : déplace la liste dans le groupe *Archived lists* en
  bas de la page. Ship It ne la propose plus. Ses participants
  restent inscrits. Utilisez **Réactiver** pour la rétablir.
- **Renommer** : si le programme a déjà une liste portant le nouveau
  nom, utilisez plutôt la fusion.
- **Fusionner dans une autre liste** : ajoute toutes les personnes de
  cette liste à une liste choisie dans le même programme, puis
  supprime cette liste.
- **Supprimer** : supprime la liste. Saisissez le nom de la liste pour
  confirmer. Ses participants restent inscrits par les autres listes
  sur lesquelles ils figurent.

### Anciennes listes sans programme

Auparavant, Ship It enregistrait des listes sans indiquer leur
programme. Elles se trouvent dans un groupe replié en bas de la page.
Cliquez sur **Attribuer un programme** pour attribuer son programme à
une liste. Elle devient alors une liste normale que vous pouvez
modifier. Si le programme a déjà une liste portant ce nom, les deux
listes sont fusionnées.

## Onglet Par participant

Une ligne par participant actif :

- **Programmes** : les programmes auxquels il est inscrit.
- **Première inscription** : la date de sa première inscription.
- **Listes** : cliquez sur **Voir** pour afficher chaque programme et
  chaque liste où il figure. Cliquez sur un nom de liste pour
  l'ouvrir.

Saisissez du texte dans les zones sous les en-têtes pour rechercher
dans une colonne.
