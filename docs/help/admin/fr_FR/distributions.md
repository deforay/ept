---
title: PT Surveys
summary: View, add, edit, and ship PT Surveys (distributions) and their shipments
tags: [distributions, pt-survey, shipments, ship]
---

# Enquêtes PT

Une **enquête PT** (aussi appelée *distribution*) correspond à un
cycle d'essais d'aptitude à une date choisie. Chaque enquête contient
une ou plusieurs **expéditions** : les panels que les participants
testent et auxquels ils répondent. Cette page liste toutes les
enquêtes du système et permet de les créer, les modifier et les
expédier.

## Contenu de la page

- **Ajouter une nouvelle enquête PT** (en haut à droite) : crée une
  nouvelle enquête.
- Le tableau liste toutes les enquêtes, une ligne par enquête, avec
  tri et recherche.

### Colonnes

- **Voir les expéditions** : bouton loupe. Cliquez pour ouvrir une
  fenêtre qui affiche toutes les expéditions de cette enquête sans
  quitter la page.
- **Type d'expédition/de panel PT** : les types de panels (par exemple
  VL, EID, DTS) de cette enquête. Affiche *Aucune
  expédition/aucun panel ajouté* en gris quand l'enquête n'a pas
  encore d'expédition.
- **Date de l'enquête PT** : la date du cycle.
- **Code de l'enquête PT** : le code unique de l'enquête. Cliquez
  dessus pour ouvrir la page des expéditions filtrée sur ce code.
- **Code(s) de l'expédition** : les codes de toutes les expéditions
  de l'enquête, séparés par des virgules.
- **Statut** : l'état actuel (voir ci-dessous).
- **Action** : des boutons qui varient selon le statut (voir
  ci-dessous).

## Statuts des enquêtes

- **Créé** : l'enquête existe, mais elle n'a pas encore d'expédition,
  ou ses expéditions n'ont pas de participants. Les participants ne
  voient rien.
- **Configured** : les expéditions et les participants sont en place.
  Le bouton **Expédier maintenant** apparaît.
- **Expédiée** : vous avez cliqué sur **Expédier maintenant**. Les
  participants voient les expéditions et peuvent répondre.
- **Partially Finalized** : certaines expéditions de l'enquête sont
  finalisées, d'autres pas encore.
- **Finalisé** : toutes les expéditions actives sont finalisées.
- **Annulée** : toutes les expéditions de l'enquête ont été annulées.

## Boutons de la colonne Action

Les boutons affichés dépendent du statut de l'enquête :

- **Modifier** : ouvre l'enquête pour la modifier.
- **Ajouter une expédition** : affiché quand l'enquête n'a pas
  d'expédition. Ouvre la liste des expéditions de l'enquête pour en
  ajouter une.
- **Ajouter des participants** : affiché quand des expéditions
  existent mais ne sont pas prêtes à être expédiées. Ouvre la liste
  des expéditions, où vous cliquez sur **Inscrire** pour une
  expédition.
- **Expédier maintenant** : affiché quand les expéditions et les
  participants sont prêts. Voir « Expédier une enquête » ci-dessous.
- **Expédiée** *(désactivé)* : confirme que l'enquête est en ligne.
- **E-mail** : ouvre l'éditeur d'e-mail pour les participants des
  expéditions de cette enquête.
- **Annuler** : annule toutes les expéditions non finalisées de
  l'enquête. Affiché uniquement tant qu'aucune expédition n'est
  finalisée. Une confirmation vous est demandée. L'annulation est
  irréversible.
- **Supprimer** : supprime une enquête vide. Affiché uniquement quand
  l'enquête n'a pas d'expédition et n'est pas expédiée.

## Expédier une enquête

Tant que vous n'avez pas cliqué sur **Expédier maintenant**, une
enquête et ses expéditions **ne sont pas visibles pour les
participants** : elles restent dans votre espace de brouillon. Un
clic sur **Expédier maintenant** :

1. Vous demande de confirmer (l'action est irréversible).
2. Met l'enquête et ses expéditions en ligne.
3. Envoie une notification pour que les laboratoires sachent qu'ils
   ont de nouveaux panels auxquels répondre.

Parcours habituel : **Ajouter une nouvelle enquête PT** → ajoutez les
expéditions sur la page suivante → associez les participants →
vérifiez avec **Voir les expéditions** → **Expédier maintenant**
quand tout est correct → plus tard, utilisez **Envoyer un e-mail aux
participants** pour les relances.

## Conseils

- La liste est triée par **Date de l'enquête PT**, les enquêtes les
  plus récentes en haut. Le cycle en cours est donc toujours en tête.
- Pour voir uniquement les enquêtes qui attendent d'être expédiées,
  saisissez *configured* dans la recherche de la colonne **Statut**.
  Seules les lignes **Configured** s'affichent, et vous pouvez les
  traiter une à une.
- Après l'expédition, vous pouvez encore modifier la date. Le code se
  verrouille dès qu'une des expéditions de l'enquête est finalisée.
  Voir l'aide **Modifier l'enquête PT**.
