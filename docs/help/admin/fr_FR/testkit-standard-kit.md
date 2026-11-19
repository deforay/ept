---
title: Map Test Kits to Tests
summary: Select which test kits appear in the participant response form for each test position (Test 1, Test 2, Test 3, etc.)
tags: [config, testkit, mapping, dts, hiv-serology, generic-scheme]
---

# Associer les kits de test aux tests

Cette page détermine **quels kits de test apparaissent dans le
formulaire de réponse du participant pour chaque position de test**. Par
exemple, si vous associez *Determine*, *SD Bioline* et *Stat-Pak* à
**HIV Serology — Test 1**, ces trois kits (et seulement eux) apparaissent
dans la liste déroulante *Test 1* du participant.

> Sans cette association, la liste déroulante de la position de test est
> vide dans le formulaire du participant. Cette étape est donc
> obligatoire pour que les expéditions de sérologie VIH / DTS
> fonctionnent.

## Mode d'emploi

1. Choisissez un **test** dans la liste déroulante *Choisissez un test
   pour associer le kit de test* :
   - **HIV Serology — Test 1 / Test 2 / Test 3** : les trois positions
     de test DTS.
   - **Tests des programmes génériques** : les tests personnalisés que
     vous avez définis dans des programmes autres que DTS apparaissent
     sous les trois options de sérologie VIH.
2. Le sélecteur à deux volets charge à gauche tous les kits
   *disponibles* pour cette position, et à droite tous les kits *déjà
   associés*.
3. **Cliquez sur le nom d'un kit** pour le faire passer d'un volet à
   l'autre. Le glisser-déposer n'existe pas : un simple clic déplace le
   kit dans un sens.
4. **Filtrez** l'un ou l'autre volet en tapant dans le champ de
   recherche en haut. C'est utile quand vous avez des centaines de kits.
5. **Tout sélectionner** *(à gauche)* : déplace tous les kits
   disponibles vers le volet des kits sélectionnés.
6. **Tout désélectionner** *(à droite)* : renvoie tous les kits
   sélectionnés vers le volet des kits disponibles.
7. **Enregistrer la sélection** : enregistre les choix pour cette
   position de test.
8. **Annuler** : abandonne les déplacements non enregistrés et
   réinitialise la page.

## Quels kits apparaissent à gauche ?

Seuls les kits **déjà approuvés** et **rattachés au programme concerné**
apparaissent :

- Pour **HIV Serology — Test 1 / 2 / 3** : les kits qui ont *DTS* dans
  leur liste **Programme** et le **numéro de test** correspondant coché
  (Test 1, Test 2 ou Test 3) dans le formulaire d'ajout ou de
  modification du kit.
- Pour les **tests des programmes génériques** : les kits rattachés à ce
  programme générique.

Si un kit n'apparaît pas à gauche, vérifiez son formulaire de
modification. Le plus souvent, la case du *numéro de test* pour cette
position n'est pas cochée.

## Conseils

- **Enregistrez chaque position de test séparément.** Le formulaire
  enregistre seulement la position affichée. Si vous choisissez un autre
  test dans la liste déroulante, les modifications non enregistrées sont
  perdues.
- **Modifier une association ne change pas les réponses passées.**
  Retirer un kit d'une position change seulement ce qu'un participant
  peut choisir *à partir de maintenant*. Les anciennes réponses gardent
  ce qui a été saisi.
- Si vous retirez tous les kits d'une position, le formulaire du
  participant affiche une liste déroulante vide pour cette position.
  Gardez au moins un kit, ou n'utilisez plus cette position de test.
- Utilisez le **filtre de recherche** de chaque volet pour les
  modifications rapides. Les longues listes de kits sont difficiles à
  parcourir à l'œil.
