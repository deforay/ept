---
title: Generate Reports
summary: Build draft per-participant and summary reports for internal review (not yet visible to participants)
tags: [reports, generate, distribution, draft, queue]
---

# Générer les rapports

Cette page est l'**étape 2** du processus de rapport en trois étapes :

1. **Analyse → Évaluer les réponses** : notez les réponses.
2. **Analyse → Générer les rapports** *(cette page)* : produisez
   des rapports provisoires pour une revue interne.
3. **Analyse → Finaliser les rapports** : produisez de nouveau les
   rapports et verrouillez-les. Les participants ne les voient
   qu'après cette étape.

Les rapports produits ici sont des **brouillons**. Ils permettent
à l'administrateur PT de vérifier la mise en page, les scores, les
commentaires, les graphiques et les notes d'action corrective avant
la publication. **Les participants ne les voient pas tant que vous
n'avez pas finalisé.**

## Fonctionnement de la page

Le tableau du haut liste toutes les enquêtes PT. Cliquez sur
**Voir** dans la colonne Action pour déplier le panneau
**Expéditions dans le cadre de l'enquête PT ...** **directement
sous la ligne**, comme sur la page d'évaluation.

- Cliquez de nouveau sur **Voir** sur la même ligne pour replier
  le panneau.
- Cliquez sur **Voir** sur une autre ligne pour changer de
  panneau. Une seule enquête est dépliée à la fois.
- Le système retient la dernière enquête ouverte. Le panneau se
  rouvre automatiquement à votre retour.

Le panneau des expéditions affiche les mêmes chiffres que la page
d'évaluation : *Nombre d'échantillons / Nombre de participants /
Nombre de réponses*, *% de réponses*, *Nombre de réussites*,
*État de l'expédition*. Il comporte aussi une colonne **Action**.
Ses boutons dépendent de l'étape où se trouve l'expédition.

## Colonne Action : ce que vous voyez et quand

### Rapports pas encore produits

- **Générer les rapports** *(bleu)* : lance la génération des
  rapports de cette expédition dans la file d'attente en
  arrière-plan. Le bouton est désactivé si la date de l'enquête PT
  est encore dans le futur.

### Rapports produits, non finalisés

- **Voir les rapports** *(œil bleu clair)* : ouvre les rapports
  provisoires pour relire les PDF avant de finaliser. C'est tout
  l'intérêt de cette étape : *relisez d'abord les brouillons.*
- **Régénérer les rapports** *(flèche circulaire orange)* : produit
  de nouveau les rapports. Utilisez-le après une modification de
  commentaire, un remplacement de fichier d'action corrective, une
  nouvelle évaluation ou tout autre changement.
- **Finaliser** *(✓ bleu)* : passe à l'étape 3. Ouvre la page de
  finalisation, où vous confirmez et verrouillez les rapports.

### Déjà finalisée

- **Voir** *(œil bleu)* : affichage en lecture seule de
  l'expédition finalisée. Une fois l'expédition finalisée, cette
  page ne propose plus les boutons de régénération ni de
  finalisation. Utilisez **Analyse → Finaliser les rapports** pour
  les liens de téléchargement et les actions de suivi.

## File d'attente en arrière-plan : lancez et passez à autre chose

La génération des rapports s'exécute en arrière-plan. Quand vous
cliquez sur **Générer les rapports** ou **Régénérer les rapports** :

1. L'expédition est ajoutée à une file d'attente.
2. La page affiche une barre de progression mise à jour toutes les
   deux ou trois secondes.
3. **Vous n'avez pas besoin d'attendre.** Allez sur une autre page,
   revenez plus tard ou fermez l'onglet. La file d'attente continue.
   À votre retour, les boutons indiquent le nouvel état.
4. Pendant l'exécution d'une tâche, les boutons de cette expédition
   sont désactivés. Vous ne pouvez donc pas lancer deux fois la
   même tâche.

Pour les très grandes enquêtes (des milliers de participants), la
génération peut prendre plusieurs minutes. Lancez les tâches de
plusieurs expéditions à la suite, puis revenez vérifier plus tard.

## Quand Générer les rapports est désactivé

- **L'expédition n'est pas encore évaluée.** Évaluez-la d'abord
  dans **Analyse → Évaluer les réponses**.
- **Une tâche est en attente ou en cours.** Tous les boutons sont
  désactivés jusqu'à la fin de la tâche. Attendez, puis actualisez
  la page.
- **L'expédition est finalisée.** Ses rapports sont définitifs et
  ne peuvent plus être produits de nouveau.

## Ce qui est produit

Chaque exécution produit :

- **Les rapports par participant** : un PDF par participant, avec
  son résultat, ses scores, les commentaires et la pièce jointe
  d'action corrective éventuelle.
- **Le rapport de synthèse** : un PDF de synthèse pour toute
  l'expédition, avec les statistiques globales, les graphiques et
  la répartition des réussites et des échecs.

Ces fichiers sont stockés dans le dossier des rapports de
l'expédition jusqu'à la finalisation. Après la finalisation, les
participants peuvent télécharger leur exemplaire depuis leur
tableau de bord.

## Conseils

- **Générez d'abord, relisez ensuite, puis finalisez.** C'est la
  raison d'être de cette étape : la finalisation est irréversible.
- **Régénérez après chaque modification.** La modification d'un
  commentaire, le remplacement d'un fichier d'action corrective ou
  une nouvelle évaluation ne reproduisent pas les PDF d'eux-mêmes.
  Cliquez sur Régénérer pour obtenir des brouillons à jour.
- **Ne lancez pas deux fois de suite la même expédition.** Si une
  tâche est déjà en cours, les boutons sont désactivés. Attendez
  la fin de la tâche avant de réessayer.
