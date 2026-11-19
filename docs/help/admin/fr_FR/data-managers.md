---
title: Data Managers (Participant Logins)
summary: Manage Data Manager / PT Login accounts — add, edit, reset password, change email, and link to participants
tags: [data-manager, pt-login, participant-login, password, mapping]
---

# Gestionnaires de données (connexions des participants)

Cette page liste les comptes **PT Login** que les participants
utilisent pour se connecter et saisir leurs résultats. Chaque compte
est associé à un ou plusieurs participants et ne voit que ceux-là.

## Boutons du haut

- **Ajouter un nouveau gestionnaire de données (identifiant du
  participant)** : ouvre le formulaire pour ajouter un compte PT Login.
- **Réinitialiser à partir de la liste collée** : ouvre une fenêtre
  dans laquelle vous collez des e-mails ou des ID de laboratoire.
  Chaque ID du laboratoire retrouve tous les comptes PT Login associés
  à ce participant. Chaque e-mail retrouve un compte PT Login. Vous
  mettez ensuite une réinitialisation du mot de passe en file
  d'attente, et vous pouvez envoyer à chaque utilisateur ses nouveaux
  identifiants par e-mail.
- **E-mails inutilisables** : télécharge une liste Excel des comptes
  dont l'e-mail est absent, a été rejeté ou ne peut pas recevoir de
  courrier.

## Filtres

- **Statut** *(par défaut : Actif)* : *Actif*, *Inactif* ou *Tout*.
  Actif est la valeur par défaut, pour écarter les comptes retirés.
- **Association** : *Tout*, *Associé à un participant* ou *N'est
  associé à aucun participant*. Ce filtre n'apparaît que si certains
  comptes PT Login ne sont associés à aucun participant.
- **Champs de recherche sous chaque en-tête de colonne** : chaque
  champ recherche uniquement dans sa colonne.

Les lignes actives **associées à aucun participant** ont un fond
jaune. Ces utilisateurs peuvent se connecter mais n'ont rien à faire.

## Colonnes

- **Nom**
- **Contact** : e-mail et téléphone. Une petite étiquette signale un
  e-mail qui ne peut pas recevoir de courrier : *Rejeté (bounce)*,
  *Non délivrable* ou *Connexion uniquement*.
- **Participants associés** : le nombre de participants que voit ce
  compte PT Login. Cliquez dessus pour les lister.
- **Action** : voir ci-dessous.

## Colonne Action

Chaque ligne comporte ces boutons :

- **Modifier** : ouvre le formulaire de modification.
- **Associer** : ouvre une fenêtre pour choisir les participants que
  voit ce compte PT Login.
- **Mot de passe** : ouvre une fenêtre pour définir un nouveau mot de
  passe pour cet utilisateur.
- **E-mail** : ouvre une fenêtre pour changer l'**Adresse e-mail
  principale**. L'adresse e-mail principale sert aussi d'identifiant.
- **Voir comme** : ouvre l'espace participant en tant que cet
  utilisateur, dans un nouvel onglet. Ce bouton n'apparaît que pour
  les utilisateurs actifs, et seulement pour les administrateurs qui
  ont cette autorisation. Chaque utilisation est enregistrée.

## Réinitialiser plusieurs mots de passe à la fois

1. Cochez la case au début de chaque ligne voulue. La case de
   l'en-tête coche toutes les lignes de la page affichée.
2. Cliquez sur **Mettre en file d'attente la réinitialisation et
   l'e-mail**.

Les réinitialisations s'exécutent en arrière-plan. Chaque utilisateur
reçoit un e-mail avec ses nouveaux identifiants. Tant que des lignes
sont cochées, **Réinitialiser à partir de la liste collée** est masqué.

## Conseils

- **Consultez de temps en temps le filtre « N'est associé à aucun
  participant ».** Un compte PT Login actif sans participant peut se
  connecter, mais son tableau de bord est vide.
- **Mot de passe et E-mail sont deux fenêtres différentes.** Si un
  utilisateur n'a plus accès à son ancien e-mail, changez d'abord
  l'e-mail. Le nouvel e-mail devient son identifiant. Réinitialisez
  ensuite le mot de passe, pour que les nouveaux identifiants
  partent vers le nouvel e-mail.
