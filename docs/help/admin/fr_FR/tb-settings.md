---
title: TB Settings
summary: Scoring, report metadata, and report content for TB PT
tags: [config, tb, tuberculosis, scoring]
---

# Paramètres TB

Paramètres du programme **TB** : score de réussite, informations de
version du rapport et bloc de coordonnées imprimé sur chaque rapport TB.

## Champs

- **Score de réussite** *(obligatoire, 0 à 100)* : le score minimal
  qu'un participant doit obtenir pour réussir une expédition TB. Une
  valeur courante est `95`.
- **Version du rapport** : texte de version imprimé dans le **pied de
  page du rapport PDF** (par exemple, `v1.4`).
- **Date d'entrée en vigueur du rapport** : date à laquelle la version
  actuelle du rapport est entrée en vigueur. Elle est imprimée dans le
  pied de page, à côté de la version.
- **Contenu des informations de contact** *(obligatoire, texte mis en
  forme)* : un bloc imprimé sur chaque rapport TB par participant. Usage
  courant : nom du coordinateur PT, adresse, téléphone, e-mail et liens
  de référence. Il se modifie dans l'éditeur de texte intégré
  (Summernote). Le gras, les listes et les liens fonctionnent donc sans
  saisir de HTML.

## Enregistrement

- **Mettre à jour** : enregistre le formulaire. Les champs obligatoires
  sont marqués d'un `*`.
- **Retour** : revient à la page d'accueil de l'administration sans
  enregistrer.

## Conseils

- **Réévaluez les expéditions TB après avoir modifié le score de
  réussite.** Le nouveau score s'applique seulement à la prochaine
  évaluation.
- Mettez à jour la **Version du rapport** et la **Date d'entrée en
  vigueur du rapport** chaque fois que vous modifiez la notation ou le
  contenu. C'est une bonne pratique : les auditeurs voient ainsi quelle
  version des règles a produit un rapport donné.
- Le bloc **Contenu des informations de contact** apparaît sur *chaque*
  rapport TB. Gardez-le court : une note de type pied de page convient
  le mieux.
