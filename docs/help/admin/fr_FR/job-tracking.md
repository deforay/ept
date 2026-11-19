---
title: Job Tracking
summary: Follow background jobs such as evaluation and report generation, and cancel a job that is stuck
tags: [jobs, queue, background, reports, evaluation, stuck, cancel]
---

# Suivi des tâches

Cette page liste les tâches qu'ePT exécute en arrière-plan. Elles
comprennent l'évaluation, la génération des rapports, les
certificats et l'envoi des e-mails. La liste s'actualise toutes
les 5 secondes.

## Trouver pourquoi une tâche a échoué

1. Ouvrez **Configurer → Suivi des tâches**.
2. Sous **Statut**, choisissez **Failed**.
3. Sous **Type de tâche**, choisissez le type de tâche.
4. Survolez **Échec** dans la colonne **Statut**. Une tâche de
   génération des rapports affiche la raison.
5. Corrigez la cause.
6. Relancez la tâche depuis la page où vous l'avez lancée.

Les autres types de tâche n'affichent pas de raison ici. Pour la
trouver, ouvrez **Configurer → Afficheur de journaux**. Choisissez
le jour où la tâche a tourné, puis le niveau **ERROR**.

## Annuler une tâche bloquée

Une tâche est bloquée quand son statut reste **En attente**,
**En cours**, **En cours de génération** ou **Finalisation en
cours** et que sa progression n'avance pas.

1. Trouvez la tâche dans la liste.
2. Cliquez sur **Annuler** dans la colonne **Actions**.
3. Confirmez.

**Annuler** marque la tâche comme annulée. Cela n'arrête pas un
travail déjà en cours sur le serveur.

L'annulation d'une tâche de génération des rapports libère
l'expédition. Vous pouvez alors générer de nouveau ses rapports.
L'annulation d'une tâche de formulaires TB vous permet de générer
de nouveau les formulaires.

ePT arrête aussi une tâche de génération des rapports qui ne
progresse plus pendant 5 minutes. Il la marque **Échec** et libère
l'expédition.

Vous ne pouvez pas annuler les lignes **Send Emails**. Pour arrêter
un e-mail planifié, utilisez **Suspendre** ou **Annuler** dans
l'onglet **Planifiés** de **Envoyer un e-mail aux participants**.

## Filtres

Filtrez par **Type de tâche** et par **Statut**.

| Filtre | Effet |
| --- | --- |
| **Type de tâche** | Affiche un seul type de tâche |
| **Statut** | Affiche les tâches **Pending**, **Processing**, **Completed** ou **Failed** |
| **Plage de dates** | Affiche les tâches démarrées pendant cette période. Par défaut, les 6 derniers mois |
| **Réinitialiser** | Efface les filtres et rétablit la période par défaut |

Cliquez sur **Actualiser maintenant** pour recharger la liste tout
de suite.

## Types de tâche

| Type de tâche | Ce que fait la tâche |
| --- | --- |
| **Report Generation** | Génère ou finalise les rapports d'une expédition |
| **Evaluation** | Évalue une expédition |
| **Certificate Generation** | Génère des certificats |
| **Email Reports** | Envoie les rapports aux participants par e-mail |
| **Send Emails** | Envoie les e-mails mis en file d'attente un même jour. Chaque ligne correspond à un jour |
| **TB Forms Generation** | Génère les formulaires TB d'une expédition |
| **Bulk Password Reset** | Réinitialise de nombreux mots de passe en une fois |
| **Scheduled Job** | Toute autre tâche d'arrière-plan |

Survolez le type de tâche pour voir plus de détails.

## Colonnes

| Colonne | Contenu |
| --- | --- |
| **Type de tâche** | Le type de tâche |
| **Référence** | Le code de l'expédition, ou le jour pour **Send Emails**. Pour la génération des rapports, cliquez dessus pour ouvrir les rapports de l'expédition |
| **Statut** | Le statut de la tâche. Une icône qui tourne indique que la tâche est en cours |
| **Progression** | Pour la génération des rapports, les rapports des participants terminés et le rapport de synthèse. Pour **Send Emails**, les e-mails envoyés et le nombre d'échecs |
| **Demandé par** | L'administrateur qui a lancé la tâche, ou **System** |
| **Démarré le** | Depuis combien de temps la tâche a démarré. Survolez pour voir la date et l'heure |
| **Terminé à** | Depuis combien de temps la tâche s'est terminée |
| **Durée** | La durée d'exécution de la tâche |
| **Actions** | **Annuler**, pour une tâche qui n'est pas terminée |

## Statuts

| Statut | Signification |
| --- | --- |
| **En attente** | La tâche attend de démarrer |
| **En cours**, **En cours de génération**, **Finalisation en cours** | La tâche s'exécute |
| **Terminé**, **Rapports générés**, **Finalisé** | La tâche est terminée |
| **Échec** | La tâche s'est arrêtée sur une erreur |
| **Annulée** | Un administrateur a annulé la tâche |

Si **Send Emails** indique des e-mails en échec, ouvrez
**Configurer → E-mails non envoyés** pour en voir la raison.
