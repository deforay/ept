---
title: Participant Performance Report
summary: Each participant's results across shipments, with pass rate, failures and average score
tags: [reports, participants, performance, pass rate, scores, failures]
---

# Rapport sur les performances des participants

**Rapports → Rapport sur les performances des participants** montre les résultats de chaque participant sur plusieurs expéditions. Chaque ligne correspond à un participant, avec une bande de résultats, un par expédition.

Le rapport s'affiche dès l'ouverture de la page. Les filtres s'appliquent dès que vous les modifiez. Il n'y a pas de bouton **Obtenir un rapport**.

## Filtres

Cliquez sur **Afficher les filtres** pour afficher les filtres.

| Filtre | Effet | Valeur par défaut |
|---|---|---|
| Date d'expédition | Inclut les expéditions dont la date d'expédition se trouve dans cette période. Cliquez sur **Effacer** dans le sélecteur de dates pour inclure toutes les dates. | 3 dernières années |
| Programme | Inclut les expéditions d'un programme. | Tous les programmes. Si un seul programme est actif, ce programme. |
| Expédition | Inclut uniquement les expéditions choisies. Vous pouvez en choisir plusieurs. La liste contient toutes les expéditions non annulées de la période et du programme. | Toutes les expéditions de la période |
| Pays, Région, Province, District | Inclut uniquement les participants de ce lieu. Chaque filtre ne s'affiche que si ce champ est renseigné pour des participants. | Tous |

Si vous modifiez la période ou le programme, la liste **Expédition** se recharge. Une expédition choisie reste sélectionnée tant qu'elle figure dans la liste.

Chaque filtre actif s'affiche sous forme d'étiquette à côté du bouton **Afficher les filtres**.

- Cliquez sur une étiquette pour ouvrir le panneau des filtres sur ce filtre.
- Cliquez sur **×** sur une étiquette pour retirer ce filtre.

Les expéditions annulées n'apparaissent jamais dans ce rapport.

## Boutons de performance

Les boutons au-dessus du tableau limitent le tableau à un groupe de participants. Chaque bouton indique le nombre de participants qu'il affiche.

| Bouton | Affiche les participants qui |
|---|---|
| Tout | Sont inscrits à au moins une expédition. |
| Au moins un échec | Ont échoué à une ou plusieurs expéditions. |
| Au moins une absence de réponse | N'ont pas répondu à une ou plusieurs expéditions. |
| Aucun échec | Ont réussi une ou plusieurs expéditions et n'ont échoué à aucune. |

## Tableau

Chaque ligne correspond à un participant. Les totaux ne comptent que les expéditions qui correspondent aux filtres.

| Colonne | Contenu |
|---|---|
| Participant | Le nom du participant. En dessous, l'identifiant du participant et le nom de l'institution. |
| Localisation | Le district, la province et le pays. |
| Expéditions | Le nombre d'expéditions auxquelles le participant est inscrit. En dessous, le nombre d'expéditions auxquelles il a répondu. |
| Taux de réussite | Le pourcentage de réponses évaluées qui ont réussi, avec une barre. En dessous, le nombre de réussites sur le nombre de réponses évaluées. Affiche **Pas encore évalué** avant l'évaluation. |
| Échec | Le nombre d'expéditions auxquelles le participant a échoué. |
| Score moyen | Le score moyen des réponses évaluées. Affiche **Aucun score** avant l'évaluation. |
| Résultats, du plus ancien au plus récent | Un carré par expédition, de la plus ancienne à la plus récente. |

Le tableau affiche d'abord les participants qui ont le plus d'échecs. Cliquez sur l'en-tête d'une colonne pour trier selon cette colonne.

La zone **Rechercher** au-dessus du tableau porte sur le nom du participant, l'identifiant du participant, le nom de l'institution et le lieu.

## Bande de résultats

Chaque carré correspond à une expédition. La couleur indique le résultat. En cas de réussite ou d'échec, le score s'affiche dans le carré.

| Carré | Signification |
|---|---|
| Vert clair | Réussi |
| Rouge | Échec |
| Contour en pointillés | Pas de réponse |
| Jaune | N'a pas pu tester |
| Gris | Exclu |
| Contour gris clair | A répondu, pas encore évalué |

Pointez un carré pour voir le code de l'expédition, la date d'expédition, le résultat et le score.

La bande affiche les 12 expéditions les plus récentes. Un nombre précédé de **+** avant les carrés indique combien d'expéditions plus anciennes ne sont pas affichées.

## Expéditions d'un participant

Cliquez sur un participant pour afficher ses expéditions sous la ligne, de la plus récente à la plus ancienne. Cliquez de nouveau sur le participant pour les masquer.

| Colonne | Contenu |
|---|---|
| Expédition | Le code de l'expédition et la date d'expédition. |
| Programme | Le programme de l'expédition. |
| Répondu le | La date de soumission de la réponse. **Tardives** signale une réponse soumise après la date limite. |
| Score | Le score. Vide sauf si la réponse a réussi ou échoué. |
| Résultat | **Réussi**, **Échec**, **Pas de réponse**, **N'a pas pu tester**, **Exclu** ou **Non évalué**. |

## Télécharger Excel

**Télécharger Excel** enregistre le rapport dans un fichier Excel avec deux feuilles.

| Feuille | Contenu |
|---|---|
| Participants | Une ligne par participant, avec tous les totaux du tableau. |
| Par expédition | Une ligne par participant et par expédition, avec le score et le résultat. |

Le fichier utilise les filtres, le bouton de performance et la recherche du rapport affiché à l'écran. Il inclut tous les participants, pas seulement la page en cours.
