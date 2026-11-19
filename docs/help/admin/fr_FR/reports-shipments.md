---
title: Shipments Report
summary: Every shipment with its responses, pass rate, status and downloads
tags: [reports, shipments, downloads, overview, non-responders, status]
---

# Rapport sur les expéditions

**Rapports → Rapport sur les expéditions** liste toutes les expéditions de la période choisie. Chaque ligne indique combien de participants ont répondu, leurs performances, l'étape de l'expédition et les fichiers à télécharger.

L'expédition la plus récente apparaît en premier. Cliquez sur l'en-tête d'une colonne pour trier selon cette colonne. La colonne Téléchargements ne se trie pas.

## Filtres

Cliquez sur **Afficher les filtres** pour afficher les filtres. Le tableau se met à jour dès que vous modifiez un filtre. Il n'y a pas de bouton de recherche.

| Filtre | Effet | Par défaut |
|---|---|---|
| Date d'expédition | Affiche les expéditions dont la date d'expédition est dans cette période. Cliquez sur **Effacer** dans le sélecteur de dates pour afficher toutes les dates. | 3 dernières années |
| Programme | Affiche les expéditions d'un seul programme. | Tous les programmes. Si un seul programme est actif, ce programme. |
| Statut | Affiche les expéditions aux étapes choisies. Vous pouvez en choisir plusieurs. | Tous les statuts |
| À traiter | Affiche uniquement les expéditions encore ouvertes après leur date limite, ou évaluées mais pas encore finalisées. | Désactivé |
| Inclure les annulés | Ajoute les expéditions annulées à la liste. | Désactivé |

Chaque filtre actif s'affiche sous forme d'étiquette à côté du bouton **Afficher les filtres**.

- Cliquez sur une étiquette pour ouvrir le panneau des filtres sur ce filtre.
- Cliquez sur **×** sur une étiquette pour retirer ce filtre.

La zone **Rechercher** au-dessus du tableau cherche dans le code de l'expédition, le code de l'enquête PT et le nom du programme.

## Colonnes

| Colonne | Contenu |
|---|---|
| Expédition | Le code de l'expédition. En dessous, le code de l'enquête PT s'il diffère du code de l'expédition, et la date d'expédition. |
| Programme | Le programme de l'expédition. |
| Date limite | La date limite de réponse. L'heure s'affiche seulement si la date limite n'est pas en fin de journée. Le fuseau horaire de toutes les dates limites s'affiche sous l'en-tête de la colonne. |
| Réponses | Le pourcentage de participants inscrits qui ont répondu, avec une barre. En dessous, le nombre de répondants sur le nombre d'inscrits. Cliquez sur le pourcentage pour ouvrir le graphique des réponses. |
| Taux de réussite | Le pourcentage de réponses évaluées qui ont réussi, avec une barre. En dessous, le nombre de réussites sur le nombre de réponses évaluées. Affiche **Pas encore évalué** avant l'évaluation. |
| Statut | L'étape de l'expédition. Voir **Statut** ci-dessous. |
| Téléchargements | Les fichiers de cette expédition. Voir **Téléchargements** ci-dessous. |

Un participant compte comme **ayant répondu** s'il a soumis des résultats, y compris en retard, ou s'il a signalé qu'il n'a pas pu tester. Les participants qui n'ont pas répondu ne comptent pas dans le taux de réussite.

## Répartition

Le bouton placé avant le code de l'expédition ouvre la répartition de la participation sous la ligne. Vous pouvez aussi cliquer n'importe où sur la ligne, sauf sur un lien ou un bouton. Cliquez de nouveau pour la fermer.

Pour cette expédition, la répartition indique :

- combien de participants étaient inscrits, ont répondu et ont testé le panel
- combien ont réussi, sont en échec, attendent l'évaluation ou ont été exclus de la notation
- combien ont répondu sans pouvoir tester, et combien n'ont pas répondu
- le nombre de réponses tardives et de rapports téléchargés

## Statut

| Statut | Signification |
|---|---|
| Non expédié | L'expédition n'est pas encore envoyée aux participants. |
| Ouvert aux réponses | Les participants peuvent soumettre des résultats. **Date limite dépassée** s'affiche en dessous une fois la date limite passée. |
| Évalué | Les réponses sont évaluées. Les rapports ne sont pas encore générés. |
| Rapports générés | Des rapports provisoires existent. Les participants ne les voient pas encore. Un lien **Rapports provisoires →** les ouvre. |
| Finalisé | Les rapports sont finalisés et visibles par les participants. Un lien **Rapports →** les ouvre. |
| Annulée | L'expédition est annulée. Les expéditions annulées s'affichent seulement si **Inclure les annulés** est activé. |

Les pages **Rapports →** et **Rapports provisoires →** contiennent le rapport de synthèse, le fichier avec le rapport de chaque participant et le nombre de téléchargements.

## Téléchargements

Les téléchargements s'affichent sur une seule ligne. **Aperçu** est un bouton. Les autres fichiers suivent sous forme de liens, le cas échéant.

| Téléchargement | Contenu | Quand il s'affiche |
|---|---|---|
| Aperçu | Un classeur Excel avec la liste des participants, les résultats déclarés et les scores. | Dès que l'expédition est expédiée. |
| Non-répondants | Une liste Excel des participants qui n'ont pas encore répondu. | Jusqu'à la finalisation de l'expédition. |
| Retour d'information | Un fichier Excel des réponses des participants aux questions de retour d'information. | Quand au moins un participant y a répondu. |
| Formulaires TB | Un fichier ZIP avec le formulaire de résultats de chaque participant. | Expéditions TB, après la génération des formulaires et jusqu'à la finalisation de l'expédition. |

Les expéditions annulées n'ont pas de téléchargements.
