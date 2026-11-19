---
title: VL (HIV Viral Load) Settings
summary: Scoring, report metadata, and report content for HIV Viral Load PT
tags: [config, vl, viral-load, hiv, scoring]
---

# Paramètres VL (charge virale du VIH)

Paramètres du programme **charge virale du VIH (VL)** : score de
réussite, informations de version du rapport et bloc de contenu imprimé
sur chaque rapport par participant.

## Champs

- **Score de réussite** *(obligatoire, 0 à 100)* : le score minimal
  qu'un participant doit obtenir pour réussir une expédition VL. Une
  valeur courante est `95`.
- **Version du rapport** : texte de version imprimé dans le **pied de
  page du rapport PDF** (par exemple, `v2.1`). Il indique quel ensemble
  de règles a produit le rapport.
- **Date d'entrée en vigueur du rapport** : date à laquelle la version
  actuelle du rapport est entrée en vigueur. Elle est imprimée dans le
  pied de page, à côté de la version.
- **Contenu des informations de contact** *(obligatoire, texte mis en
  forme)* : un bloc imprimé sur chaque rapport VL par participant. Usage
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

- **Réévaluez les expéditions VL après avoir modifié la notation.** Un
  nouveau score de réussite s'applique seulement à la prochaine
  évaluation.
- Les **Manual VL Reference Ranges** d'une expédition donnée se règlent
  dans *Analyse → Évaluer les réponses →* (ouvrez l'expédition) *→*
  bouton **Plage VL**, et non ici. Cette page définit les valeurs par
  défaut pour tout le programme.
- La **Version du rapport** et la **Date d'entrée en vigueur du rapport**
  figurent dans les pieds de page des rapports. Mettez-les à jour chaque
  fois que vous modifiez la notation ou le contenu. Les auditeurs voient
  ainsi quelles règles ont été appliquées.
- Le bloc **Contenu des informations de contact** apparaît sur *chaque*
  rapport VL. Gardez-le court : une petite note de type pied de page
  convient le mieux.
