---
title: How to enroll participants
summary: The two kinds of enrollment in ePT — and how to do each one
audience: admin
estimated_minutes: 5
tags: [guide, enrollment, participants, workflow]
steps:
  - id: 1
    title: Two kinds of enrollment — what's the difference?
    target_pages: []
  - id: 2
    title: Add the participant to the system (if new)
    target_pages: [participants/index, participants/add, participants/bulk-import]
  - id: 3
    title: Enroll the participant in a scheme
    target_pages: [enrollments/index, enrollments/add, enrollments/bulk-enrollment]
  - id: 4
    title: Enroll the participant in a specific shipment
    target_pages: [distributions/index, shipment/index, shipment/ship-it, shipment/manage-enroll]
---

# Comment inscrire des participants

Dans ePT, l'inscription se fait à **deux niveaux**. Beaucoup
d'utilisateurs s'y perdent, car les deux se ressemblent. Ce guide
explique la différence, puis vous guide pour chacun.

Comptez environ 5 minutes.

## Étape 1 : Deux types d'inscription : quelle différence ?

Il faut bien distinguer deux inscriptions :

1. **L'inscription au programme** : vous indiquez au système « ce
   laboratoire fait le PT de sérologie VIH ». Vous le faites **une
   seule fois**, quand le laboratoire rejoint votre programme. Rien
   n'est envoyé à personne. Cela signifie seulement que le
   laboratoire fait partie de ce programme.

2. **L'inscription à l'expédition** : pour chaque cycle PT (chaque
   expédition), vous indiquez au système « cette expédition va à
   ces laboratoires ». Vous le faites **pour chaque expédition**.

Pourquoi les deux ? Un laboratoire peut être inscrit en sérologie
VIH toute l'année, mais vous pouvez vouloir l'exclure de
l'expédition d'un trimestre (par exemple, s'il est fermé pour
maintenance). L'inscription à l'expédition vous permet de choisir
cycle par cycle, sans retirer le laboratoire du programme.

L'inscription au programme détermine quels laboratoires sont
**sélectionnés par défaut** quand vous inscrivez des participants à
une expédition de ce programme. Vous pouvez toujours ajouter ou
retirer des laboratoires pour chaque expédition.

> L'ordre est donc toujours : **Ajouter le laboratoire → Inscription
> au programme → Inscription à l'expédition.**

## Étape 2 : Ajoutez le participant au système (s'il est nouveau)

Passez cette étape si le laboratoire est déjà dans votre système.

Pour ajouter un nouveau laboratoire :

- Allez dans **Configurer → Participants au PT**.
- Pour un seul laboratoire : cliquez sur **Ajouter un nouveau
  participant**, saisissez le nom, le code unique et les
  coordonnées, puis enregistrez.
- Pour plusieurs laboratoires à la fois : cliquez sur **Importation
  groupée des participants**, téléchargez le modèle Excel,
  remplissez-le, puis téléversez-le. Les champs à en-tête rouge
  sont obligatoires.

Après l'enregistrement, le laboratoire apparaît dans la liste des
participants avec un ID du participant unique.

## Étape 3 : Inscrivez le participant à un programme

C'est l'étape **unique** qui permet à un laboratoire de participer
à un programme. Un laboratoire est inscrit à un programme lorsqu'il
figure sur au moins une des **listes d'inscription** du programme.
Chaque programme a une liste **Par défaut**, marquée *Principale*.

1. Allez dans **Configurer → Inscriptions au programme**.
2. Dans l'onglet **Listes**, trouvez le groupe du programme.
3. Cliquez sur **Modifier** pour la liste **Par défaut**.
4. Cliquez sur un laboratoire dans **Pas sur cette liste** (à
   gauche) pour le faire passer dans **Sur cette liste** (à droite).
5. Cliquez sur **Enregistrer les modifications**.

Si le programme n'a pas encore de groupe, cliquez sur **Nouvelle
liste**, choisissez le programme, laissez le nom vide et cliquez sur
**Choisir les participants**.

Un laboratoire peut être inscrit à plusieurs programmes. Répétez
cette étape pour chaque programme auquel le laboratoire participe.

> **Autres façons de remplir une liste**
>
> - **Importation en masse d'inscriptions** remplit une liste à
>   partir d'un fichier Excel. Vous voyez les changements avant tout
>   enregistrement.
> - **Dupliquer la liste**, dans le menu **Plus** d'une liste, crée
>   une nouvelle liste avec les mêmes laboratoires. La nouvelle
>   liste peut appartenir au même programme ou à un autre.

## Étape 4 : Inscrivez le participant à une expédition précise

Vous faites cette étape **à chaque fois** que vous créez une
nouvelle expédition.

- Allez dans **Gérer → Enquête PT** et cliquez sur **Ajouter des
  participants** à côté de votre enquête. La liste des expéditions
  de l'enquête s'ouvre.
- Cliquez sur le bouton rouge **Inscrire** sur la ligne de
  l'expédition.
- Deux listes s'affichent :
  - **Participants inscrits/sélectionnés** (à droite) : les
    laboratoires qui recevront cette expédition. À la première
    ouverture de la page, les laboratoires inscrits au programme y
    sont déjà.
  - **Participants qui ne sont pas encore inscrits à ce programme**
    (à gauche) : tous les autres laboratoires actifs.
- Cliquez sur un nom pour le faire passer d'une liste à l'autre.
- Cliquez sur **Inscrire** en bas pour enregistrer.

Si un laboratoire attendu **n'est pas dans la liste de droite**,
trouvez-le à gauche et cliquez dessus. Pour qu'il soit sélectionné
par défaut la prochaine fois, inscrivez-le au programme (étape 3).

Après l'enregistrement, l'expédition partira vers les laboratoires
de la liste de droite dès que vous cliquerez sur **Expédier
maintenant** dans la liste des enquêtes PT.
