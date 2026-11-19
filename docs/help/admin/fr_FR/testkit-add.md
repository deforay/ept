---
title: Add Test Kit
summary: Create a new test kit, optionally enabling Additional Information capture for OD / Ct / custom values
tags: [config, testkit, add, additional-info]
---

# Ajouter un kit de test

Ajoute un nouveau kit de test diagnostique au système. Une fois ajouté,
il est disponible dans le formulaire de réponse des participants pour le
ou les programmes choisis.

## Champs

- **Nom du kit de test** *(obligatoire)* : nom complet du kit. Le
  système vérifie les doublons quand vous quittez le champ.
- **Nom du kit de test abrégé** : libellé plus court, facultatif (utilisé
  quand la place manque, par exemple dans les tableaux des rapports).
- **Est-ce approuvé par le ministère de la Santé ?** *(Oui/Non)* :
  indique si le ministère de la Santé a approuvé ce kit.
- **Validé par le fournisseur de PT ?** *(Oui/Non)* : indique si votre
  programme PT a validé ce kit.
- **Référence de la source** : citation, URL ou nom de document,
  facultatif.
- **Fabricant de kits de test** : nom du fabricant en texte libre.
- **Programme** *(obligatoire, choix multiple)* : un ou plusieurs
  programmes auxquels ce kit s'applique. Quand **DTS** est sélectionné,
  le champ **Choose a Test Number** apparaît (voir ci-dessous).
- **Choisissez un numéro de test pour associer le kit de test**
  *(obligatoire quand DTS est sélectionné)* : choisissez la ou les
  positions de test DTS auxquelles ce kit s'applique : **Test 1**,
  **Test 2**, **Test 3**. Vous pouvez en choisir plusieurs.
- **Autoriser les informations supplémentaires** *(Oui/Non)* : voir la
  section ci-dessous.
- **Commentaires** : notes en texte libre. Elles ne sont pas montrées
  aux participants.

## Autoriser les informations supplémentaires

Avec **Oui**, deux champs supplémentaires apparaissent. Le formulaire de
réponse des participants reçoit aussi une colonne supplémentaire à côté
de ce kit. Les participants y saisissent une lecture numérique ou un
texte en plus du résultat :

- **Libellé des informations complémentaires** : le nom de la colonne
  dans le formulaire de réponse (par exemple *OD Value*, *Ct Value*,
  *Reading*, *Lot Number*).
- **Les informations complémentaires sont-elles obligatoires ?**
  *(Oui/Non)* : avec **Oui**, les participants ne peuvent pas envoyer le
  formulaire sans remplir ce champ pour le kit.

C'est ici que vous recueillez les lectures quantitatives (densité
optique pour l'ELISA, valeurs Ct pour la PCR) qui complètent le résultat
réussi/échec.

## Enregistrement

- **Ajouter** : enregistre le formulaire et revient à la liste des kits
  de test.
- **Annuler** : abandonne les modifications et revient à la liste.

À l'enregistrement, un nouveau kit est **marqué comme approuvé par
défaut**. Il apparaît tout de suite dans les listes déroulantes des
participants. Utilisez le formulaire *Modifier* pour le passer en non
approuvé si vous voulez le masquer.

## Conseils

- Choisissez un **Nom du kit de test clair**. Il apparaît dans les
  listes déroulantes des participants et dans les rapports.
  *« Determine HIV-1/2 Ag/Ab Combo (Abbott) »* est plus utile que
  *« Determine »*.
- Pour DTS, **renseignez toujours le ou les numéros de test**. Un kit
  sans position de test n'apparaît à aucune position dans le formulaire
  des participants. Vous pouvez aussi modifier ce réglage plus tard dans
  **Associer les kits de test aux tests**.
- Utilisez **Autoriser les informations supplémentaires** avec
  modération. Chaque colonne obligatoire supplémentaire ajoute du travail
  aux participants. Utilisez-la seulement quand la lecture numérique est
  vraiment nécessaire.
- Le champ *Commentaires* convient aux notes internes (par exemple,
  « vérifié avec le panel X », « retrait progressif en 2026 »). Rien de
  ce champ n'est montré aux participants.
