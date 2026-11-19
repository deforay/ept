---
title: VL Sample Analysis
summary: The sample results of one VL shipment, per sample and per assay, with the viral load distribution of each assay
tags: [reports, vl, viral load, samples, assay, z-score, distribution]
---

# Analyse des échantillons VL

**Rapports → Analyse des échantillons VL** montre les scores des participants pour chaque échantillon d'une expédition VL. Il montre aussi les charges virales déclarées pour chaque technique.

À l'ouverture de la page, le rapport affiche l'expédition VL la plus récente. Choisissez une autre expédition dans la liste **Expédition** pour la changer. La liste contient toutes les expéditions VL non annulées, les plus récentes en premier.

## Résultats pris en compte

Le rapport compte les résultats des réponses soumises. Les réponses tardives comptent. Les participants qui n'ont pas répondu ou qui n'ont pas pu tester ne comptent jamais.

Chaque résultat a un score.

| Score | Signification |
|---|---|
| Acceptable | Le z-score est inférieur à 2. |
| Avertissement | Le z-score est supérieur ou égal à 2 et inférieur à 3. La notation compte un avertissement comme une réussite. |
| Inacceptable | Le z-score est supérieur ou égal à 3. |
| Exclu | Le résultat a été exclu de la notation. |
| Non noté | L'expédition n'est pas encore évaluée, ou le résultat n'a pas de score. |

## Résultats par échantillon

Chaque ligne correspond à un échantillon de l'expédition.

| Colonne | Contenu |
|---|---|
| Échantillon | Le libellé de l'échantillon. |
| Résultats | Le nombre de résultats pour l'échantillon. |
| Taux de réussite | Le pourcentage de résultats notés Acceptable ou Avertissement, avec une barre. En dessous, le nombre de réussites sur le nombre de résultats notés. Affiche **Pas encore évalué** avant l'évaluation. |
| Acceptable, Avertissement, Inacceptable, Exclu, Non noté | Le nombre de résultats avec ce score. |

## Résultats par technique

Chaque ligne correspond à une technique. Les résultats d'un participant comptent sous la technique indiquée dans sa réponse. Les réponses sans technique comptent sous **Aucune technique déclarée**.

Le graphique affiche une barre par technique, divisée par score. Le tableau a les mêmes colonnes que **Résultats par échantillon**, plus **Participants** : le nombre de participants qui ont utilisé la technique.

## Répartition des charges virales par technique

Chaque carte correspond à une technique, avec un graphique par échantillon. Chaque point est la charge virale log10 d'un résultat. La couleur du point indique le z-score : vert pour acceptable, orange pour avertissement, rouge pour inacceptable.

| Ligne | Signification |
|---|---|
| Bande grisée, entre les lignes pointillées | La plage acceptable de l'échantillon pour cette technique. |
| Ligne continue | La médiane. |

Au-dessus de chaque graphique figurent le libellé de l'échantillon, le nombre de résultats, la médiane et l'écart type (SD). La carte indique le nombre de participants et le nombre de résultats indétectables.

| Bouton | Axe des X |
|---|---|
| Par participant | Une position par participant. L'axe des Y est la charge virale log10. |
| Par charge virale log10 | La charge virale log10. Les points sont étalés vers le haut et vers le bas pour ne pas se chevaucher. |

Les résultats marqués invalides ou en erreur ne figurent pas dans les graphiques.

## Télécharger Excel

**Télécharger Excel** enregistre un fichier Excel pour l'expédition affichée. **Échantillons** contient une ligne par échantillon. **Techniques** contient une ligne par technique. Les deux feuilles incluent le taux de réussite.
