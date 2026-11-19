---
title: VL Assay Distribution
summary: VL results per assay, with the pass rate of each assay
tags: [reports, vl, viral load, assay, platform, pass rate]
---

# Répartition des techniques VL

**Rapports → Répartition des techniques VL** indique quelles techniques les participants ont utilisées dans les expéditions VL, et les performances des réponses de chaque technique.

Le rapport s'affiche dès l'ouverture de la page. Les filtres s'appliquent dès que vous les modifiez. Il n'y a pas de bouton Obtenir un rapport.

## Filtres

Cliquez sur **Afficher les filtres** pour afficher les filtres. Le rapport couvre uniquement la VL. Il n'y a donc pas de filtre de programme. Si la VL n'est pas active, la page l'indique.

| Filtre | Effet | Par défaut |
|---|---|---|
| Date d'expédition | Inclut les expéditions dont la date d'expédition est dans cette période. Cliquez sur **Effacer** dans le sélecteur de dates pour inclure toutes les dates. | 3 dernières années |
| Expédition | Inclut uniquement les expéditions choisies. Vous pouvez en choisir plusieurs. La liste contient toutes les expéditions VL non annulées de la période. | Toutes les expéditions de la période |
| Pays, Région, Province, District | Inclut uniquement les participants de ce lieu. Chaque filtre s'affiche seulement si des participants ont ce champ renseigné. | Tout |

Chaque filtre actif s'affiche sous forme d'étiquette à côté du bouton **Afficher les filtres**.

- Cliquez sur une étiquette pour ouvrir le panneau des filtres sur ce filtre.
- Cliquez sur **×** sur une étiquette pour retirer ce filtre.

Les expéditions annulées n'apparaissent jamais dans ce rapport.

## Résultats

Chaque inscription a exactement un résultat. Il compte sous la technique indiquée dans la réponse du participant.

| Résultat | Signification |
|---|---|
| Réussi | La réponse a réussi. |
| Échec | La réponse est en échec. |
| Exclu | La réponse a été exclue de la notation. |
| N'a pas pu tester | Le participant a signalé qu'il n'a pas pu tester le panel. |
| Pas de réponse | Le participant n'a pas répondu. |
| Non évalué | Le participant a répondu et l'expédition n'est pas encore évaluée. |

Les inscriptions sans technique comptent sous **Aucune technique déclarée**. Il s'agit surtout de participants qui n'ont pas répondu ou qui n'ont pas pu tester.

## Regrouper par

| Bouton | Lignes |
|---|---|
| Technique | Une ligne par technique. |
| Année et technique | Une ligne par année d'expédition et par technique. L'année la plus récente apparaît en premier. |

## Graphique

Chaque barre correspond à une technique. La barre est divisée par résultat. Avec **Année et technique**, chaque barre additionne toutes les années.

## Tableau

| Colonne | Contenu |
|---|---|
| Année | L'année d'expédition. S'affiche seulement avec **Année et technique**. |
| Technique | Le nom court de la technique. En dessous, le nom complet. |
| Inscriptions | Le nombre d'inscriptions. |
| Taux de réussite | Le pourcentage de réponses évaluées qui ont réussi, avec une barre. En dessous, le nombre de réussites sur le nombre de réponses évaluées. Affiche **Pas encore évalué** avant l'évaluation. |
| Échec, Exclu, N'a pas pu tester, Pas de réponse, Non évalué | Le nombre d'inscriptions avec ce résultat. |

Les techniques qui ont le plus d'inscriptions apparaissent en premier. **Aucune technique déclarée** apparaît en dernier. La ligne **Total** additionne toutes les lignes. Cliquez sur l'en-tête d'une colonne pour trier selon cette colonne.

## Télécharger Excel

**Télécharger Excel** enregistre le tableau dans un fichier Excel, avec une ligne de total. Le fichier reprend les filtres et le regroupement du rapport affiché.
