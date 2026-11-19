---
title: Shipment Response Report
summary: Who responded to each shipment, who did not, and how they performed
tags: [reports, shipments, response rate, non-responders, pass rate, participants]
---

# Rapport des réponses à l'expédition

**Rapports → Rapport des réponses à l'expédition** indique combien de participants ont répondu à chaque expédition. Il liste aussi chaque participant avec sa réponse et son résultat.

Le rapport s'affiche dès l'ouverture de la page. Les filtres s'appliquent dès que vous les modifiez. Il n'y a pas de bouton Obtenir un rapport.

## Filtres

Cliquez sur **Afficher les filtres** pour afficher les filtres.

| Filtre | Effet | Par défaut |
|---|---|---|
| Date d'expédition | Inclut les expéditions dont la date d'expédition est dans cette période. Cliquez sur **Effacer** dans le sélecteur de dates pour inclure toutes les dates. | 3 dernières années |
| Programme | Inclut les expéditions d'un seul programme. | Tous les programmes. Si un seul programme est actif, ce programme. |
| Expédition | Inclut uniquement les expéditions choisies. Vous pouvez en choisir plusieurs. La liste contient toutes les expéditions non annulées de la période et du programme. | Toutes les expéditions de la période |
| Pays, Région, Province, District | Inclut uniquement les participants de ce lieu. Chaque filtre s'affiche seulement si des participants ont ce champ renseigné. | Tout |

Si vous modifiez la période ou le programme, la liste **Expédition** se recharge. Une expédition choisie reste choisie tant qu'elle figure dans la liste.

Chaque filtre actif s'affiche sous forme d'étiquette à côté du bouton **Afficher les filtres**.

- Cliquez sur une étiquette pour ouvrir le panneau des filtres sur ce filtre.
- Cliquez sur **×** sur une étiquette pour retirer ce filtre.

Les expéditions annulées n'apparaissent jamais dans ce rapport.

## Par expédition

Chaque ligne correspond à une expédition. Les nombres incluent uniquement les participants qui correspondent aux filtres.

| Colonne | Contenu |
|---|---|
| Expédition | Le code et la date de l'expédition. |
| Programme | Le programme de l'expédition. Masquée quand un programme est choisi. |
| Inscrits | Le nombre de participants inscrits. |
| Répondu | Le pourcentage de participants inscrits qui ont répondu, avec une barre. En dessous, le nombre de répondants sur le nombre d'inscrits. |
| Pas de réponse | Le nombre de participants qui n'ont pas répondu. |
| Taux de réussite | Le pourcentage de réponses évaluées qui ont réussi, avec une barre. En dessous, le nombre de réussites sur le nombre de réponses évaluées. Affiche **Pas encore évalué** avant l'évaluation. |
| Échec | Le nombre de participants en échec. |

| Clic | Résultat |
|---|---|
| Sur un code d'expédition | Le tableau **Participants** affiche uniquement cette expédition. Cliquez de nouveau sur le code pour afficher toutes les expéditions. |
| Sur un nombre **Pas de réponse** | Le tableau **Participants** affiche les participants de cette expédition qui n'ont pas répondu. |
| Sur un nombre **Échec** | Le tableau **Participants** affiche les participants de cette expédition en échec. |
| Ailleurs sur la ligne | Ouvre la répartition de la participation à l'expédition sous la ligne. Cliquez de nouveau pour la fermer. |

La répartition compte tous les participants de l'expédition. Elle n'applique pas les filtres de lieu.

## Participants

Chaque ligne correspond à un participant dans une expédition. Un participant inscrit à trois expéditions a trois lignes.

| Colonne | Contenu |
|---|---|
| Participant | Le nom du participant. En dessous, l'ID du participant et le nom de l'institut. |
| Localisation | Le district, la province et le pays. |
| Expédition | Le code et la date de l'expédition. |
| Réponse | **Répondu**, **Tardives**, **N'a pas pu tester** ou **Pas de réponse**. |
| Répondu le | La date d'envoi de la réponse. |
| Résultat | **Réussi**, **Échec**, **Exclu** ou **Non évalué**. Vide avant l'évaluation. |

Les boutons au-dessus du tableau le filtrent selon la réponse.

| Bouton | Affiche les participants qui |
|---|---|
| Tout | Sont inscrits. |
| Répondu | Ont soumis des résultats, y compris en retard, ou ont signalé qu'ils n'ont pas pu tester. |
| Tardives | Ont soumis des résultats après la date limite. |
| N'a pas pu tester | Ont signalé qu'ils n'ont pas pu tester le panel. |
| Pas de réponse | N'ont pas répondu. |
| Échec | Ont répondu et sont en échec. |

La zone **Rechercher** au-dessus du tableau cherche dans le nom du participant, l'ID du participant, le nom de l'institut, le lieu et le code de l'expédition.

## Télécharger Excel

**Télécharger Excel** enregistre le tableau **Participants** dans un fichier Excel. Le fichier reprend les filtres, le bouton de réponse et la recherche du tableau affiché. Il contient toutes les pages du tableau.
