---
title: Test Kits
summary: List, filter, approve, and manage test kits used by participants in their response forms
tags: [config, testkit, dts, hiv-serology, approval]
---

# Kits de test

La page **Kits de test** liste tous les kits de diagnostic configurés
dans le système. Les participants choisissent dans cette liste quand ils
remplissent leur formulaire de réponse. Ce que vous approuvez ici est ce
qu'ils voient.

## Boutons de l'en-tête de page

- **Ajouter un nouveau kit de test** : ouvre le formulaire *Ajouter*
  pour créer un nouveau kit.
- **Associer les kits de test aux tests** *(seulement quand DTS /
  sérologie VIH est un programme actif)* : ouvre la page qui associe les
  kits aux positions de test (Test 1 / Test 2 / Test 3, ou tests des
  programmes génériques).

## Filtre de statut

La **liste déroulante de statut** au-dessus du tableau filtre les kits
affichés :

- **Afficher tout** : tous les kits, quel que soit leur statut
  d'approbation.
- **Afficher les éléments approuvés** : les kits marqués comme approuvés.
  Seuls ces kits apparaissent dans les listes déroulantes des
  participants.
- **Afficher les éléments non approuvés** : les kits explicitement
  refusés.
- **Show Pending** : les kits en attente d'une décision d'approbation.
  Cette option affiche aussi une liste déroulante **Modifier le
  statut** avec **Tout approuver**. Cette action approuve tous les
  kits en attente du système. Ils apparaissent ensuite sous
  **Afficher les éléments approuvés**.

**Tout approuver** ignore le filtre et les lignes visibles. Cette action
modifie tous les kits en attente : vérifiez d'abord la liste des kits en
attente. Pour modifier un seul kit, cliquez sur **Modifier** sur sa
ligne.

## Colonnes

- **Nom du kit de test** : nom complet du kit.
- **Nom du programme** : le ou les programmes auxquels il appartient.
- **Fabricant de kits de test** : nom du fabricant, en texte libre.
- **Est-il agréé par le ministère de la Santé ?** : indique si le
  ministère de la Santé a approuvé le kit.
- **Validé par le fournisseur de PT ?** : indique si votre programme PT
  a validé le kit.
- **Approuvé** : *Oui* / *Non*. Réglé par **Actif** dans le formulaire
  de modification, ou par **Tout approuver** pour les kits en attente.
- **Créé le** : date du premier ajout du kit.
- **Action** : bouton crayon **Modifier**.

## Conseils

- À sa création, un nouveau kit est réglé sur **Approuvé = Oui**.
- Les participants ne peuvent choisir un kit que s'il est **approuvé**
  et associé au programme et au numéro de test. Approuvez les kits
  avant d'expédier une enquête qui en a besoin. Un kit déjà enregistré
  sur une réponse reste sur cette réponse.
- **Tout approuver** n'apparaît qu'après avoir choisi *Show Pending*.
- Pour DTS, après avoir approuvé un kit, associez-le en général aussi à
  une position de test (Test 1 / 2 / 3) avec **Associer les kits de test
  aux tests**. Sinon, il n'apparaît pas dans le formulaire de réponse
  pour cette position.
