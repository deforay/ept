---
title: Custom Tests
summary: Set up a test type that ePT does not have built in, with its own results and scoring
tags: [custom tests, schemes, results, import, export]
---

# Tests personnalisés

Un test personnalisé est un type de test que vous définissez vous-même,
par exemple un test rapide de la syphilis ou du paludisme. Une fois le
test actif, vous pouvez lui ajouter des expéditions comme pour un
programme intégré.

## Ajouter un test personnalisé

1. Cliquez sur **Ajouter un nouveau test personnalisé**.
2. Saisissez le **Nom du test personnalisé** et un **Code de test
   personnalisé** court. La page vous indique si le code est déjà
   utilisé.
3. Renseignez le **Nombre de tests** : le nombre de tests qu'un
   participant réalise sur chaque échantillon.
4. Renseignez le **Score de réussite** et le **Statut**.
5. Dans **Sélectionner le format du test**, choisissez **Qualitative**.
   Le format **Quantitative** est encore en développement et peut ne
   pas fonctionner entièrement.
6. Remplissez le tableau des résultats. Ajoutez une ligne pour chaque
   résultat qu'un participant peut déclarer. Voir « Le tableau des
   résultats » ci-dessous.
7. Cliquez sur **Ajouter**.

## Le tableau des résultats

Chaque ligne est un résultat que les participants peuvent choisir.

| Colonne | Ce qu'il faut saisir |
| --- | --- |
| **Résultat attendu** | Le résultat tel que les participants le voient, par exemple *Réactif* |
| **Code de résultat** | Un code court enregistré dans la base de données. Il doit être unique |
| **Type de résultat** | **Résultat du test** pour un seul test, ou **Interprétation finale** pour la conclusion globale |
| **Affiché à** | **Participant**, **Administrateur** ou **Tout** |
| **Ordre de tri** | La position dans la liste déroulante |

Ajoutez au moins une ligne **Interprétation finale**. Sans elle, la
liste déroulante du résultat final propose tous les codes, y compris
les résultats de test. Les participants qui choisissent un code de
résultat de test obtiennent alors zéro, même quand leur réponse est
correcte.

## Paramètres facultatifs

- **Désactiver l'autre kit de test** retire le choix *Autre*. Les
  participants ne peuvent donc pas saisir un kit absent de la liste.
- **Appliquer ces kits de test** définit les kits de test recommandés
  pour ce test.
- **Saisir des détails supplémentaires** ajoute un champ au formulaire
  de réponse, avec le libellé que vous saisissez.
- **Version du rapport** et **Date d'entrée en vigueur du rapport**
  s'impriment dans le pied de page du rapport.

## Modifier un test personnalisé

Cliquez sur **Modifier** sur sa ligne. Quand une expédition utilise déjà
un résultat, son code et son libellé sont verrouillés. Vous pouvez
toujours changer son ordre de tri et les personnes qui le voient.

## Copier un test personnalisé

- **Dupliquer** crée un nouveau test personnalisé avec les mêmes
  paramètres et résultats sur cette instance.
- **Exporter** enregistre le test dans un fichier `.json`. Sur une autre
  instance ePT, cliquez sur **Importer** et téléversez ce fichier. Cochez
  **Remplacer un test personnalisé si un test portant le même code
  existe déjà** pour remplacer un test qui a le même code. Les kits de
  test sont rapprochés par leur nom. Les kits absents de cette instance
  sont ignorés avec un avertissement.
