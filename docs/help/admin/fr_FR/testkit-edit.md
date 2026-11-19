---
title: Edit Test Kit
summary: Update a test kit's details, approval status, scheme/test mapping, and Additional Information settings
tags: [config, testkit, edit, approval, additional-info]
---

# Modifier le kit de test

Modifie un kit de test existant. Les champs sont les mêmes que dans le
formulaire **Ajouter**. Une liste déroulante **Approuvé** s'y ajoute :
elle permet de montrer ou de masquer un kit aux participants sans le
supprimer.

## Champs

- **Nom du kit de test** *(obligatoire)* : nom complet du kit. La
  vérification des doublons ignore le kit actuel.
- **Nom du kit de test abrégé** : libellé court, facultatif.
- **Est-ce approuvé par le ministère de la Santé ?** *(Oui/Non)* :
  indique si le ministère de la Santé a approuvé ce kit.
- **Validé par le fournisseur de PT ?** *(Oui/Non)* : indique si votre
  programme PT a validé ce kit.
- **Référence de la source** : citation, URL ou document, facultatif.
- **Fabricant de kits de test** : nom du fabricant.
- **Actif** *(Oui/Non)* : affiché comme **Approuvé** dans la liste des
  kits. Les filtres **Afficher les éléments approuvés** et **Afficher
  les éléments non approuvés** l'utilisent.
- **Programme** *(obligatoire, choix multiple)* : le ou les programmes
  du kit. Quand **DTS** en fait partie, le champ **Choose a Test Number**
  apparaît.
- **Choisissez un numéro de test pour associer le kit de test** :
  position(s) de test, pour DTS uniquement : Test 1, Test 2, Test 3.
  Choix multiple.
- **Autoriser les informations supplémentaires** *(Oui/Non)* : active
  ou désactive la colonne de données supplémentaires dans le formulaire
  de réponse des participants. Avec **Oui** :
  - **Libellé des informations complémentaires** : libellé de la colonne
    dans le formulaire de réponse (par exemple *OD Value*, *Ct Value*).
  - **Les informations complémentaires sont-elles obligatoires ?**
    *(Oui/Non)*.
- **Commentaires** : notes internes. Elles ne sont pas montrées aux
  participants.

## Enregistrement

- **Mettre à jour** : enregistre les modifications et revient à la
  liste.
- **Annuler** : abandonne les modifications et revient à la liste.

## Conseils

- **Passez Approuvé à Non au lieu de supprimer le kit.** Des expéditions
  passées utilisent peut-être ce kit. Avec *Non*, il disparaît de la
  liste déroulante des participants sans casser les anciennes données.
- Modifier **Autoriser les informations supplémentaires** change le
  formulaire de réponse des participants *à partir de maintenant*. Les
  anciennes réponses gardent ce qui a été saisi à l'époque.
- Si vous passez **Autoriser les informations supplémentaires** de
  *Oui* à *Non*, la colonne supplémentaire disparaît pour les nouvelles
  réponses. Le *Libellé des informations complémentaires* et les
  anciennes valeurs sont conservés.
- Pour DTS, modifier **Choisissez un numéro de test pour associer le kit
  de test** déplace immédiatement le kit d'une position de test à
  l'autre. Vérifiez ensuite la page **Associer les kits de test aux
  tests** pour confirmer que le kit est bien coché (ou non) aux bonnes
  positions.
