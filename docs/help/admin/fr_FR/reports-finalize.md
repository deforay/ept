---
title: Finalize Reports
summary: Re-build and lock the reports — only finalized reports are visible to participants
tags: [reports, finalize, distribution, publish, queue]
---

# Finaliser les rapports

Cette page est l'**étape 3** du processus de rapport en trois étapes :

1. **Analyse → Évaluer les réponses** : notez les réponses.
2. **Analyse → Générer les rapports** : produisez des rapports
   provisoires pour une revue interne.
3. **Analyse → Finaliser les rapports** *(cette page)* : produisez
   de nouveau les rapports et verrouillez-les.

> **Seuls les rapports finalisés sont visibles par les participants.**
> Tant que vous n'avez pas finalisé, tout ce que vous avez généré
> n'est visible que par vous. Une fois l'expédition finalisée, chaque
> participant peut télécharger son rapport depuis son tableau de bord.

## Fonctionnement de la page

La page reprend la présentation de *Générer les rapports*. Le
tableau du haut liste toutes les enquêtes PT. Cliquez sur **Voir**
dans la colonne Action pour déplier le panneau **Expéditions dans
le cadre de l'enquête PT ...** **directement sous la ligne**.

- Cliquez de nouveau sur **Voir** sur la même ligne pour replier
  le panneau.
- Cliquez sur **Voir** sur une autre ligne pour changer de
  panneau. Une seule enquête est dépliée à la fois.
- Le système retient la dernière enquête ouverte. Le panneau se
  rouvre automatiquement à votre retour.

Le panneau des expéditions affiche les mêmes chiffres que les pages
d'évaluation et de génération : *Nombre d'échantillons / Nombre de
participants / Nombre de réponses*, *% de réponses*, *Nombre de
réussites*, *État de l'expédition*. Sa colonne **Action** est
consacrée à la finalisation.

## Colonne Action : ce que vous voyez et quand

### Prête à finaliser

- **Finaliser** *(✓ vert)* : ouvre la page de finalisation. Ce
  bouton est disponible quand les rapports sont générés et que
  l'expédition n'est pas finalisée. Sur cette page :
  1. Vérifiez **Commentaire pour tous les participants de cette
     expédition**.
  2. Renseignez **Date d'approbation** et **Approuvé par**. Laissez
     les deux champs vides pour enregistrer votre nom et l'heure
     actuelle.
  3. Cliquez sur **Finaliser** pour mettre en file d'attente la
     production finale des rapports.

### Déjà finalisée

- **Finalisé** *(rouge, désactivé)* : confirme que l'expédition est
  finalisée. Les rapports sont désormais visibles par les
  participants.
- **Télécharger le rapport de synthèse** *(vert)* : télécharge
  directement le PDF de synthèse (`<shipment-code>-summary.pdf`).
- **Télécharger 1 - 50 Rapport des participants**, **Télécharger
  51 - 100 Rapport des participants**, … *(bleu)* : les rapports des
  participants sont regroupés par lots de 50 pour limiter la taille
  des fichiers. Il y a un bouton par lot.

### Pas encore prête

- **Finaliser** *(désactivé)* : il manque l'un des éléments suivants :
  - **Les rapports ne sont pas générés.** Allez dans **Analyse →
    Générer les rapports** et générez-les d'abord.
  - **Le formulaire de feedback n'existe pas.** Si l'expédition
    recueille le feedback des participants, un bouton **Créer un
    formulaire de feedback** s'affiche à la place. Cliquez dessus,
    créez le formulaire, puis revenez finaliser.
  - **Une tâche est en attente ou en cours.** Attendez, puis
    actualisez la page.

## Ce que fait réellement « Finaliser »

Cliquer sur **Finaliser** fait passer l'expédition à son état
définitif :

1. Les rapports sont produits une dernière fois avec les scores, le
   commentaire et le fichier d'action corrective les plus récents.
2. L'expédition est marquée comme **finalisée** et l'heure est
   enregistrée.
3. **Les rapports par participant deviennent visibles sur le tableau
   de bord du participant.** Chaque participant peut alors
   télécharger son propre rapport.
4. Réévaluer, Générer les rapports et Finaliser sont désactivés pour
   cette expédition. Vous pouvez encore modifier le commentaire avec
   **Mettre à jour le commentaire**. Pour remplacer le PDF du rapport
   de synthèse, cliquez sur **Remplacer le rapport de synthèse** sur
   l'expédition dans **Analyse → Voir les expéditions finalisées**.
   Ce bouton s'affiche uniquement aux administrateurs qui ont le
   privilège de remplacer les rapports de synthèse finalisés.

Cette opération **est irréversible** depuis l'interface normale.
Vérifiez que les rapports provisoires sont corrects *avant* de
finaliser.

## File d'attente en arrière-plan : lancez et passez à autre chose

Comme *Générer les rapports*, la finalisation s'exécute en
arrière-plan :

1. L'expédition est ajoutée à la file d'attente.
2. La page affiche une barre de progression mise à jour toutes les
   deux ou trois secondes.
3. **Vous n'avez pas besoin d'attendre.** Allez sur une autre page,
   revenez plus tard ou fermez l'onglet. La tâche continue.
4. À la fin de la tâche, le bouton *Finaliser* est remplacé par
   *Finalisé* et les liens *Télécharger*. Les participants peuvent
   alors voir les rapports.

Pour les très grandes enquêtes (des milliers de participants), la
finalisation peut prendre plusieurs minutes. Lancez-la, puis revenez
vérifier plus tard.

## Après la finalisation : envoyer les rapports

Une fois l'expédition finalisée, d'autres actions deviennent
disponibles ailleurs dans l'administration pour transmettre les
rapports aux participants. Par exemple, l'envoi des PDF par e-mail
ou des notifications sur le tableau de bord. La finalisation se
contente de *rendre les rapports visibles*. Le mode de notification
des participants dépend de votre suivi.

## Conseils

- **Ne sautez pas l'étape de relecture.** Le processus prévu est
  *Générer les rapports → relire les brouillons → Finaliser*. Il
  n'existe pas de bouton pour annuler la finalisation.
- **Réévaluez avant de finaliser**, pas après. La réévaluation est
  bloquée une fois l'expédition finalisée.
- **Les modifications du commentaire et du fichier d'action
  corrective sont intégrées aux PDF définitifs.** Vérifiez-les avant
  de cliquer sur Finaliser.
- **Les boutons de téléchargement n'apparaissent qu'à la fin de la
  tâche en file d'attente.** S'ils ne s'affichent pas encore, la
  production est toujours en cours. Rechargez la page dans une ou
  deux minutes.
