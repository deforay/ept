---
title: Add Participant
summary: Create a new participant record and map them to one or more Data Managers
tags: [participants, add, create, data-managers]
---

# Ajouter un participant

Utilisez ce formulaire pour ajouter un seul nouveau participant :
un laboratoire, une clinique ou une personne. Pour en ajouter
plusieurs à la fois, utilisez plutôt **Importation groupée des
participants**.

## Champs obligatoires

Les champs marqués d'un `*` rouge doivent être remplis pour que le
formulaire puisse être enregistré. Les champs obligatoires sont :

- **ID du participant** : votre identifiant interne unique. Le
  formulaire vérifie les doublons dès que vous quittez le champ.
  Si l'ID est déjà utilisé, une erreur s'affiche et vous ne pouvez
  pas envoyer le formulaire.
- **Prénom** *(ou Nom du laboratoire, voir plus bas)*
- **Nom de l'institut**
- **Nom du département**
- **E-mail**
- **Pays**
- **État/Province**
- **District**
- **Niveau du réseau**
- **Affiliation**
- **Statut** (*Actif* par défaut)

## Personne ou laboratoire/clinique

La case **Participant individuel** en haut du formulaire change le
fonctionnement des champs de nom :

- **Décochée (par défaut)** : le participant est un laboratoire ou
  une clinique. Le premier champ s'appelle **Nom du laboratoire**
  et un champ **Nom de la personne de contact** s'ajoute. Le champ
  Nom est masqué.
- **Cochée** : le participant est une personne. Les champs
  **Prénom** et **Nom** s'affichent. Le champ de la personne de
  contact est masqué.

Faites ce choix *avant* de remplir les champs de nom, pour que le
formulaire affiche les bons libellés.

## Champs d'adresse

Saisissez l'**Adresse physique** du participant. L'**Adresse de
livraison** en dessous sert à l'envoi des panels. Remplissez-la
seulement si elle diffère de l'adresse physique. Cochez
**Identique à l'adresse physique** pour copier l'adresse physique
dans l'adresse de livraison. Les deux restent alors identiques
pendant vos modifications.

## Coordonnées

Vous pouvez saisir un **Numéro de téléphone portable.** et un
**Numéro de téléphone**. Le champ **E-mail supplémentaire/de
remplacement** accepte plusieurs adresses, séparées par des
virgules. Il sert aux participants qui veulent recevoir les
notifications dans plusieurs boîtes.

## Associer des gestionnaires de données

Chaque participant **doit être associé à au moins un gestionnaire
de données**. Le gestionnaire de données est la personne qui se
connecte et soumet les réponses PT pour le participant. La section
d'association est un sélecteur à deux listes :

- **Gestionnaires de données disponibles** à gauche : tous les
  gestionnaires de données du système.
- **Gestionnaires de données sélectionnés** à droite : ceux
  associés à ce participant.

Cliquez sur un nom dans la liste de gauche pour le passer à
droite. Cliquez sur un nom dans la liste de droite pour le
remettre à gauche. Utilisez **Tout sélectionner** ou **Tout
désélectionner** pour déplacer d'un coup tous les gestionnaires de
données affichés par le filtre en cours. Chaque liste a une zone de
recherche pour les longues listes.

Si le gestionnaire de données voulu n'existe pas encore, cliquez
sur **Créer un nouveau gestionnaire de données** au-dessus du
sélecteur. Vous le créez sans quitter ce formulaire.

Quand la connexion directe des participants est activée (un
réglage valable pour tout le système), l'association à un
gestionnaire de données est facultative. Le formulaire demande
alors aussi un mot de passe pour la connexion propre du
participant.

## Après l'enregistrement

Cliquez sur **Ajouter** pour enregistrer. Vous revenez à la liste
des participants. Si un ID du participant en double est détecté
après l'envoi, le formulaire reste ouvert et signale le doublon
pour que vous puissiez le corriger.
