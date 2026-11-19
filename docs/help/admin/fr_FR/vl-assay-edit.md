---
title: Edit VL Assay / Platform
summary: Rename a Viral Load assay / platform
tags: [config, vl, viral-load, assay, edit]
---

# Modifier la technique / plateforme VL

Modifie une technique / plateforme de charge virale du VIH existante.

## Champs

- **Nom** *(obligatoire)* : nom complet de la technique. La vérification
  des doublons ignore la technique actuelle.
- **Nom abrégé** *(obligatoire)* : libellé plus court.

## Enregistrement

- **Mettre à jour** : enregistre et revient à la liste des techniques
  VL.
- **Annuler** : abandonne les modifications et revient à la liste.

## Conseils

- Un changement de nom s'applique **immédiatement**. Le nouveau nom
  apparaît dans les listes déroulantes des participants au prochain
  chargement de la page. Les réponses existantes pointent toujours vers
  la même fiche de technique : les anciennes données ne risquent rien.
- Il n'existe pas de suppression douce ici. Si une technique est retirée
  du service, ajoutez une marque comme *« [retired] … »* au début du nom.
  Les participants savent ainsi qu'ils ne doivent pas la choisir, et les
  réponses existantes restent intactes.

## Plus de détails sur les techniques VL

La plupart des modifications ici sont de simples changements de nom ou
de petites corrections. Si vous hésitez entre renommer une technique et
en ajouter une nouvelle, la réponse sûre est : **ajoutez-en une nouvelle
et retirez l'ancienne**. Les anciens rapports restent ainsi faciles à
lire.

- Utilisez **le fabricant + la gamme de produit** quand vous renommez,
  pour correspondre à ce que les participants voient ailleurs.
- Gardez le **Nom abrégé** sous environ 20 caractères pour qu'il tienne
  dans les colonnes étroites et les légendes de graphiques.
