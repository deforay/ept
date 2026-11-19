---
title: DTS (HIV Serology) Settings
summary: Scoring, sample and testing options, and national algorithm test-kit rules for HIV Serology PT
tags: [config, dts, hiv-serology, scoring, algorithm, testkit]
---

# Paramètres DTS (sérologie du VIH)

Ces paramètres contrôlent le calcul du score et les rapports du
programme **sérologie du VIH (DTS)**. La page comporte trois sections.

## Configuration du score

Comment les réponses DTS sont notées.

- **Score minimal de réussite** *(obligatoire, %)* : le score dont un
  participant a besoin pour **réussir** une expédition. Valeurs de 0
  à 100. Une valeur courante est `95`.
- **Score du panel/de l'expédition** *(obligatoire, pts)* : les points
  attribués pour le résultat du panel (les résultats réels des tests
  rapides).
- **Score de documentation** *(obligatoire, pts)* : les points
  attribués pour les questions de documentation.
- **Score de l'algorithme** *(obligatoire, %)* : bonus ou pénalité
  pour l'utilisation du bon algorithme national de diagnostic.

> **Panel + documentation doivent totaliser 100.** Quand vous saisissez
> une valeur dans un champ, l'autre s'ajuste pour que le total reste
> à 100.

## Options d'échantillons et de test

Les règles courantes et les détails des rapports.

- **Jours pour la réhydratation des échantillons** *(obligatoire)* :
  le délai avant qu'un participant puisse tester l'échantillon
  réhydraté :
  - **0 - Permettre les tests le jour même**
  - **1 - Test après 1 jour seulement**
  - **2 - Test après 2 jours seulement**
- **Collecter des kits de test supplémentaires** *(Oui/Non)* : permet
  aux participants d'indiquer les kits supplémentaires ou de secours
  qu'ils ont utilisés.
- **Version du rapport** : le texte de version imprimé dans le pied
  de page du rapport PDF (par exemple `v3.2`).
- **Date d'entrée en vigueur du rapport** : la date imprimée dans le
  pied de page du rapport pour indiquer depuis quand la méthode
  actuelle s'applique.
- **Type de programme DTS** *(obligatoire)* : choisissez le programme :
  - **Types standard** : *Standard*, *3 tests standard*
  - **Spécifique au pays** : Ghana, Malawi, Myanmar, Sierra Leone,
    Côte d'Ivoire, RDC, Vietnam
  - Votre choix modifie les algorithmes disponibles ci-dessous et les
    champs supplémentaires affichés.
- **Algorithmes autorisés** *(obligatoire, choix multiple)* : les
  algorithmes que les participants peuvent choisir dans leur
  formulaire de réponse. Comprend *En série*, *Parallèle* et les
  algorithmes nationaux de chaque pays pris en charge.
- **Désactiver l'option « Autre kit de test »** *(obligatoire,
  Oui/Non)* : avec *Oui*, l'option *Autre* disparaît de la liste des
  kits du participant. Il doit alors choisir l'un des kits que vous
  avez configurés.

### Malawi / 3 tests standard uniquement

Quand le **Type de programme DTS** est *Malawi* ou *3 tests standard*,
deux options supplémentaires apparaissent :

- **Afficher les champs d'état des échantillons** *(Oui/Non)* :
  affiche les champs sur l'état des échantillons dans le formulaire de
  résultats du participant.
- **Afficher les champs de test répété** *(Oui/Non)* : permet aux
  participants d'enregistrer les résultats des échantillons qu'ils ont
  testés de nouveau.

## Algorithme national pour le test rapide de dépistage du VIH

Cette section est **facultative**. Utilisez-la uniquement si vous
voulez que le système exige un kit de test précis à chaque position
de test.

> **Fonctionnement.** La réponse d'un participant ne compte comme
> réussie pour un échantillon que si le kit choisi à chaque position
> de test fait partie des kits sélectionnés ici. Laissez une position
> vide pour ne pas faire ce contrôle à cette position.
>
> **Configurez d'abord les kits de test** dans *Configurer → Kits de
> test → Associer les kits de test aux tests*. Sinon, les listes de
> kits de cette section seront vides.

La section est divisée en trois panneaux (chacun s'affiche selon votre
programme) :

### Panel DTS standard

- **Test 1 Kits** : les kits autorisés à la position de test 1.
- **Test 2 Kits** : les kits autorisés à la position de test 2.
- **Test 3 Kits** : les kits autorisés à la position de test 3.

Chaque liste est à choix multiple : vous pouvez autoriser plusieurs
kits par position.

### Panel combiné DTS + syphilis

Les mêmes listes Test 1 / 2 / 3 pour le panel combiné VIH + syphilis.
Utilisé uniquement si votre programme comprend la syphilis.

### Panel combiné DTS + RTRI

La même structure pour le panel combiné VIH + RTRI (infection
récente). Utilisé uniquement si votre programme comprend le RTRI.

## Enregistrer

Cliquez sur **Mettre à jour** pour enregistrer tout le formulaire.
Les champs obligatoires sont marqués d'un `*`.

## Conseils

- **Réévaluez une expédition** après avoir modifié les paramètres de
  score ou d'algorithme. Les nouvelles règles ne s'appliquent qu'à la
  prochaine réévaluation.
- **Ne modifiez pas le score de réussite après l'expédition d'une
  enquête**, sauf si vous prévoyez de réévaluer toutes ses expéditions.
- Configurez **d'abord les kits de test** avant de remplir la section
  Algorithme national pour le test rapide de dépistage du VIH, sinon
  les listes de kits seront vides.
- Après avoir changé le **Type de programme DTS**, vérifiez à nouveau
  la liste **Algorithmes autorisés** : les options disponibles ont pu
  changer.
