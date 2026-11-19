---
title: Add PT Survey
summary: Create a new PT Survey on a specific date with a unique code
tags: [distributions, pt-survey, add, create]
---

# Ajouter une enquête PT

Une **enquête PT** correspond à un cycle PT à une date choisie.
Ajouter une enquête est la première étape. Vous ajoutez ensuite les
**expéditions** qui la composent. L'enquête ne devient visible pour
les participants qu'après un clic sur **Expédier maintenant** dans la
liste des enquêtes.

## Champs obligatoires

### Date de l'enquête PT

Choisissez la date de ce cycle PT dans le sélecteur de date.

- Un **cercle rouge** signale une date qui a déjà une enquête PT.
  Vous pouvez quand même la choisir. La page vous avertit alors de
  l'existence de l'autre enquête.
- Si l'enquête existante correspond au même cycle, ajoutez votre
  expédition à cette enquête au lieu d'en créer une deuxième.

### Code de l'enquête PT

Un identifiant court et unique pour cette enquête (par exemple
`DBS-2026-Q1`, `VL-MAY-2026`).

- Le code doit être **unique pour toutes les enquêtes**. Le
  formulaire recherche les doublons dès que vous quittez le champ et
  affiche une erreur si le code est déjà utilisé.
- Caractères autorisés : lettres, chiffres et tirets. Les autres
  caractères sont supprimés pendant la saisie.
- Si le système est configuré pour remplir les codes à votre place
  (le paramètre *Générer automatiquement le code d'enquête PT*), le
  code est rempli dès que vous choisissez une date. Vous pouvez encore
  le modifier avant d'enregistrer.

## Enregistrer : deux options

Le formulaire propose deux boutons d'enregistrement selon ce que vous
voulez faire ensuite :

- **Ajouter et passer à l'expédition** *(principal)* : enregistre
  l'enquête et ouvre directement la page des expéditions pour
  préparer les panels tout de suite. C'est le parcours habituel.
- **Enregistrer et ajouter l'expédition plus tard** : enregistre
  l'enquête et vous ramène à la liste des enquêtes. La nouvelle
  enquête reste à l'état **Créé** jusqu'à ce que vous
  y ajoutiez des expéditions.

Utilisez **Annuler** pour abandonner les modifications et revenir à
la liste des enquêtes.

## Après l'enregistrement

- L'enquête est créée avec la date et le code choisis.
- Elle n'est **pas** encore visible pour les participants. Les
  enquêtes restent masquées jusqu'au clic sur **Expédier maintenant**
  dans la liste des enquêtes, une fois leurs expéditions entièrement
  préparées.
- Vous pouvez modifier la date et le code plus tard depuis la liste
  des enquêtes. Le code se verrouille dès qu'une des expéditions de
  l'enquête est finalisée.

## Conseils

- Choisissez *d'abord* la date. Si le système remplit les codes à
  votre place, le code est construit à partir de la date. Changer la
  date après avoir saisi un code peut remplacer ce que vous avez
  saisi.
- Gardez des codes courts et parlants. Ils apparaissent dans les
  e-mails aux participants, les tableaux de bord et chaque rapport
  d'expédition.
