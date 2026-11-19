---
title: Add a Shipment
summary: How to add a shipment (sample panel) under a PT Survey
tags: [shipment, add, samples, panel]
---

# Ajouter une expédition

Vous êtes sur cette page parce que vous avez cliqué sur **New Shipment**
dans la liste des expéditions. Ou bien vous avez enregistré une nouvelle
enquête PT et choisi **Ajouter et passer à l'expédition**.

Une **expédition** est un ensemble d'échantillons de test pour un
programme (par exemple, un panel DTS de sérologie VIH). Vous allez
configurer ce panel dans l'enquête PT que vous venez de créer.

## Pas d'inquiétude : c'est l'étape suivante, pas une nouvelle tâche

Beaucoup d'utilisateurs sont perdus ici, car la page semble nouvelle.
Vous n'avez pas perdu votre enquête PT. L'enquête est enregistrée.
Cette page est simplement l'**étape 2** : indiquez au système quels
échantillons cette enquête contiendra.

Si vous fermez cette page sans enregistrer d'expédition, votre enquête PT
existe toujours. Mais elle reste à l'état **pas encore entièrement
configurée**. Vous devrez revenir ajouter une expédition avant de pouvoir
l'expédier.

## Ce que vous devez remplir

### Programme

Choisissez le type de test dans la liste déroulante, par exemple
**Dried Tube Specimen — HIV Serology**.

Seuls les programmes activés dans votre système apparaissent ici.

### Code de l'expédition

Un code court qui identifie cette expédition.

- Le système remplit un code pour vous. Vous pouvez le modifier.
- Utilisez uniquement des lettres, des chiffres et des tirets.

### Date et heure limites de soumission des résultats

L'heure exacte de clôture des réponses. L'heure suit le fuseau horaire
de l'application, défini dans **Paramètres globaux d'ePT**.

- Choisissez une date et une heure dans le futur.
- Les participants voient cette date limite sur leur portail.

### Fermeture automatique à la date limite

- **Oui** arrête d'accepter les réponses à la date limite.
- **Non** continue d'accepter les réponses en retard. Elles restent
  signalées comme tardives.

### Échantillons

Cliquez sur **+ Sample/Control** pour ajouter une ligne d'échantillon.
Pour chaque échantillon :

- Donnez-lui un nom ou un numéro (par exemple, `S1`).
- Choisissez le **résultat attendu** dans la liste déroulante. C'est la
  bonne réponse.
- Ajoutez autant d'échantillons que votre panel en demande, en général
  3 à 5.

Le bouton **Supprimer** retire une ligne d'échantillon inutile.

## Ce qui se passe à l'enregistrement

Cliquez sur **Ajouter une expédition** en bas de la page.

- L'expédition est enregistrée dans l'enquête PT.
- Vous arrivez sur la page **Inscrire**. Choisissez-y les participants
  qui recevront cette expédition.

Après l'inscription des participants, retournez dans
**Gérer → Enquête PT** et cliquez sur **Expédier maintenant** pour
envoyer réellement l'expédition aux participants.

## Conseils

- Ajoutez **tous vos échantillons et résultats attendus avant
  d'enregistrer**. C'est beaucoup plus simple que de revenir les
  modifier plus tard.
- Vous pouvez ajouter **plusieurs expéditions** à la même enquête PT
  (par exemple, un panel de sérologie VIH et un panel CV à la même date).
  Revenez dans **Gérer → Enquête PT** et cliquez de nouveau sur
  **Ajouter une expédition**.
