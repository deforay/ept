---
title: Annual Performance Report
summary: Evaluated results for a date range, and the certificates that follow from them
tags: [reports, annual, certificates, excellence, participation, csv, excel]
---

# Rapport annuel de performance

**Rapports → Rapport annuel de performance** liste toutes les réponses évaluées aux expéditions finalisées d'une période. Il exporte aussi les données utilisées pour les certificats. Les certificats eux-mêmes s'émettent dans **Rapports → Certificats annuels**.

## Réponses prises en compte

Le rapport couvre les expéditions finalisées et non annulées. Une réponse compte quand elle a réussi, ou quand elle a été soumise et a échoué. Les participants qui n'ont pas répondu n'apparaissent jamais en échec.

## Options du rapport

| Champ | Effet | Valeur par défaut |
|---|---|---|
| Date d'expédition | Inclut les expéditions dont la date d'expédition est dans cette période. | 12 derniers mois |
| Programme | Inclut les expéditions des programmes choisis. Vous pouvez en choisir plusieurs. | Tous les programmes |
| Expédition | Inclut seulement les expéditions choisies. La liste contient toutes les expéditions finalisées de la période et des programmes. | Toutes les expéditions de la liste |

Changer la période ou les programmes recharge la liste **Expédition**. Une expédition choisie reste choisie tant qu'elle est encore dans la liste.

| Type de rapport | Fichier | Contenu |
|---|---|---|
| Rapport annuel uniquement | CSV | Une ligne par réponse, avec le participant, l'expédition, le score, le résultat, les avertissements et les actions correctives. |
| Pour la certification | Excel | Une ligne par participant, avec le score et le résultat de chaque expédition, et le type de certificat pour chaque programme. |

Le type de certificat est **Excellence** quand le participant a répondu à toutes les expéditions du programme et les a toutes réussies. Il est **Participation** quand le participant a répondu à toutes les expéditions sans toutes les réussir. Il est **N.A.** dans les autres cas.

## Certificats

Les certificats s'émettent dans **Rapports → Certificats annuels**, qui utilise les mêmes résultats que le fichier Pour la certification. [Voir : Comment émettre les certificats annuels](guides/issue-annual-certificates.md)
