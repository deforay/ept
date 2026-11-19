---
title: Scheme Test Results
summary: Set the results that participants and admins can choose for each qualitative scheme
tags: [config, schemes, results, possible results, result codes]
---

# Résultats des tests du programme

Chaque programme qualitatif a une liste de résultats possibles. Le
formulaire de réponse et le formulaire d'expédition proposent ces
résultats dans leurs listes déroulantes. Les programmes quantitatifs,
comme la charge virale, n'ont pas de liste ici.

Ouvrez **Configurer → Résultats des tests du programme**. La page
liste chaque programme qualitatif actif. Cliquez sur **Résultats des tests**
dans la ligne d'un programme pour ouvrir sa liste de résultats.

Vous pouvez aussi ouvrir la liste d'un programme depuis **Configurer →
Configuration des programmes**. Dans le menu latéral, ouvrez le
programme et cliquez sur **Résultats des tests**.

## Ajouter un résultat

1. Utilisez la barre en haut de la liste des résultats.
2. Dans **Type de résultat**, choisissez **Résultat du test** ou
   **Interprétation finale**.
3. Saisissez la **Réponse** : le résultat tel que les utilisateurs le
   voient, par exemple *Réactif*.
4. Saisissez le **Code de résultat** : un code court enregistré avec
   chaque réponse.
5. Dans **Affiché à**, choisissez qui voit le résultat.
6. Facultatif : saisissez un **Ordre de tri**.
7. Cliquez sur **Ajouter**. La ligne se place dans la liste selon
   l'ordre de tri.
8. Cliquez sur **Mettre à jour** pour enregistrer.

Rien n'est enregistré tant que vous ne cliquez pas sur **Mettre à
jour**. Tant que des modifications ne sont pas enregistrées, la page
affiche un bandeau d'avertissement.

Dans un même type de résultat, deux lignes ne peuvent pas avoir la
même réponse ni le même code. Si vous cliquez sur **Mettre à jour**
alors que la barre d'ajout contient encore du texte, la page vous
demande de l'ajouter ou de l'effacer d'abord.

Pour un test personnalisé, ePT place le code du programme devant le
code de résultat que vous saisissez. Les résultats de test reçoivent
*CODE-T-* et les interprétations finales reçoivent *CODE-F-*.

## Types de résultat

- **Résultat du test** : le résultat d'un test sur un échantillon.
- **Interprétation finale** : la conclusion globale pour un
  échantillon.

La liste affiche d'abord les résultats de test, puis les
interprétations finales. Dans chaque type, les lignes suivent
l'**Ordre de tri**.

Pour un test personnalisé, ajoutez au moins une ligne **Interprétation
finale**. Le formulaire d'expédition prend le résultat attendu parmi
les interprétations finales. Sans elle, cette liste déroulante propose
tous les codes, y compris les résultats de test.

## Qui voit un résultat

| **Affiché à** | Effet |
| --- | --- |
| **Tout** | Proposé dans les formulaires des participants et des administrateurs |
| **Participant** | Proposé uniquement dans le formulaire de réponse du participant |
| **Administrateur** | Proposé uniquement dans le formulaire d'expédition de l'administrateur |
| **Aucun** | Proposé dans aucun formulaire |

## Résultats verrouillés

Une icône de cadenas dans la colonne **Action** signale une ligne
verrouillée. Son code et son libellé sont grisés et elle n'a pas de
bouton de suppression. Vous pouvez encore modifier ses champs
**Affiché à** et **Ordre de tri**. Survolez le cadenas pour voir pourquoi la ligne est
verrouillée.

Une ligne est verrouillée pour l'une de ces deux raisons :

- **Utilisé par une expédition** : une expédition utilise le
  résultat. Les résultats de l'expédition gardent ce code. La ligne
  reste donc verrouillée après la finalisation de l'expédition.
- **Sous-test prédéfini** : le résultat appartient à un sous-test
  intégré, comme la syphilis dans la Sérologie du VIH. La ligne
  affiche le groupe du sous-test, par exemple *DTS_SYP_TEST*.

## Supprimer un résultat

1. Cliquez sur le bouton moins dans la colonne **Action** de la
   ligne. Son info-bulle indique **Supprimer la rangée**.
2. Cliquez sur **Mettre à jour**.

Seules les lignes non verrouillées ont le bouton moins.
