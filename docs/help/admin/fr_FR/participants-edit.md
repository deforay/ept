---
title: Edit Participant
summary: Update an existing participant's details, mapped Data Managers, and enrolled schemes
tags: [participants, edit, update, data-managers, schemes]
---

# Modifier un participant

Utilisez ce formulaire pour mettre à jour un participant existant.
Les champs sont les mêmes que dans le formulaire **Ajouter un
participant**. Consultez cette page pour le sens de chaque champ
et la liste des champs obligatoires. Quelques points fonctionnent
un peu différemment en modification.

## Différences avec l'ajout

- L'**ID du participant** est modifiable, mais la vérification des
  doublons ignore le participant *en cours*. Vous pouvez corriger
  une faute de frappe sans que le formulaire la refuse.
- La case **Participant individuel** est déjà réglée selon la
  façon dont le participant a été créé. La changer fait passer de
  **Prénom / Nom** (personne) à **Nom du laboratoire / Nom de la
  personne de contact** (laboratoire ou clinique). Attention : si
  vous la changez, vérifiez que les champs de nom contiennent
  toujours les bonnes valeurs.
- Le titre en haut affiche le **Statut** actuel (Actif / Inactif /
  En attente) pour que vous voyiez l'état d'un coup d'œil.

## Associer des gestionnaires de données

Le sélecteur à deux listes affiche à droite les gestionnaires de
données actuels du participant. Ici, vous pouvez :

- Ajouter un gestionnaire de données en cliquant dessus à gauche.
- Retirer une association existante en cliquant dessus à droite.
- Créer un nouveau gestionnaire de données avec **Créer un nouveau
  gestionnaire de données** si celui voulu n'existe pas encore.

Si vous retirez tous les gestionnaires de données de la liste de
droite, le participant n'est plus associé à personne. La liste des
participants le signale par un surlignage jaune (participants
actifs seulement). Ne le faites que si le participant va être
désactivé.

## Programmes

Le champ **Programmes** liste les programmes auxquels ce participant
est inscrit. Ajoutez ou retirez des programmes ici, par exemple pour
inscrire le participant au programme de charge virale.

## Réinitialiser le mot de passe

Quand la connexion directe des participants est activée, vous
pouvez définir dans ce formulaire un nouveau mot de passe pour la
connexion de ce participant. Si vous laissez les champs de mot de
passe vides, l'ancien mot de passe est conservé.

## Enregistrer

Cliquez sur **Mettre à jour** en bas pour enregistrer. Vous
revenez à la liste des participants. Si vous remplacez l'ID du
participant par un ID qui appartient déjà à un autre participant,
la vérification des doublons le signale et bloque
l'enregistrement.
