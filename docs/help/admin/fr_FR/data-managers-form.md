---
title: Data Manager Form
summary: Add or edit a Data Manager (PT Login), set their password and access, and choose the participants they see
tags: [data-manager, pt-login, participant-login, password, mapping, form]
---

# Formulaire du gestionnaire de données

Utilisez ce formulaire pour ajouter ou modifier un gestionnaire de
données. Un gestionnaire de données est un compte PT Login que les
participants utilisent pour se connecter et saisir leurs
résultats. Chaque gestionnaire de données ne voit que les
participants que vous lui associez.

## Ajouter un gestionnaire de données

1. Ouvrez **Configurer → Gestionnaire de données (connexion des
   participants)**.
2. Cliquez sur **Ajouter un nouveau gestionnaire de données
   (identifiant du participant)**.
3. Saisissez l'**Identifiant (e-mail principal)**. Cette adresse
   est l'identifiant de l'utilisateur.
4. Saisissez le **Mot de passe** et **Confirmer le mot de passe**.
5. Saisissez le **Prénom de l’utilisateur**.
6. Sous **Choisir le(s) participant(s)**, cliquez sur chaque
   participant pour lequel cet utilisateur saisit les résultats.
   Le participant passe dans **Participants sélectionnés**.
7. Cliquez sur **Ajouter**.

Si vous sélectionnez plus d'un participant, ePT vous demande de
confirmer. Le gestionnaire de données peut voir les données de
tous les participants sélectionnés.

ePT demande à l'utilisateur de changer son mot de passe à sa
première connexion.

## Modifier un gestionnaire de données

1. Dans la liste des gestionnaires de données, cliquez sur
   **Modifier** sur la ligne.
2. Modifiez les champs.
3. Cliquez sur **Sauvegarder**.

Pour conserver le mot de passe actuel, laissez **Mot de passe** et
**Confirmer le mot de passe** vides. Si vous saisissez un nouveau
mot de passe, ePT demande à l'utilisateur de le changer à sa
prochaine connexion.

## Champs

| Champ | Obligatoire | Effet |
| --- | --- | --- |
| **Identifiant (e-mail principal)** | Oui | L'identifiant et l'adresse e-mail principale de l'utilisateur. Aucun autre compte ne peut l'utiliser |
| **Mot de passe** | Oui, à l'ajout | Le mot de passe de l'utilisateur. Voir les règles ci-dessous |
| **Confirmer le mot de passe** | Oui, à l'ajout | Le même mot de passe une seconde fois |
| **Établissement** | Non | L'établissement de l'utilisateur |
| **Prénom de l’utilisateur** | Oui | Le prénom de l'utilisateur |
| **Nom de famille de l'utilisateur** | Non | Le nom de famille de l'utilisateur |
| **Adresse électronique secondaire** | Non | Une deuxième adresse. Elle doit différer de l'e-mail principal |
| **Numéro de téléphone portable** | Non | Le numéro de portable de l'utilisateur |
| **Numéro de téléphone** | Non | Un autre numéro de téléphone |
| **Accès au QC** | Non | **Oui** permet à l'utilisateur de saisir les informations de QC dans le formulaire de réponse, quand le QC est activé dans ePT. La valeur par défaut est **Non** |
| **Activer l'ajout de la date de réponse** | Non | **Oui** permet à l'utilisateur de saisir la date de réponse. La valeur par défaut est **Non** |
| **Permettre de choisir le mode de réception** | Non | **Oui** permet à l'utilisateur d'indiquer comment les échantillons sont arrivés. La valeur par défaut est **Non** |
| **Activer l'accès en consultation seule** | Non | **Oui** permet à l'utilisateur de voir les résultats sans les modifier. La valeur par défaut est **Non** |
| **Statut** | Oui | Les utilisateurs **Actif** peuvent se connecter. Les utilisateurs **Inactif** ne le peuvent pas |

Un mot de passe doit :

- compter au moins autant de caractères que la **Longueur du mot
  de passe** définie dans **Paramètres globaux d'ePT**
- contenir au moins une lettre et un chiffre
- ne pas contenir le nom de l'utilisateur ni la partie de
  l'adresse e-mail située avant le @

## Choisir les participants

La zone **Choisir le(s) participant(s)** comporte deux listes.
**Participants non sélectionnés**, à gauche, contient les
participants qui ne sont pas associés. **Participants
sélectionnés**, à droite, contient les participants que cet
utilisateur voit.

- Cliquez sur le nom d'un participant pour le passer dans l'autre
  liste.
- Saisissez du texte dans la zone au-dessus d'une liste pour la
  filtrer.
- Cliquez sur **Tout sélectionner** ou **Tout désélectionner**
  pour déplacer tous les participants de la liste.
- Cliquez sur **Afficher les filtres avancés** pour filtrer par
  **Pays**, **Province**, **District**, **Établissement**,
  **Niveau du réseau** ou **Affiliation**.

Les couleurs indiquent ce qui change à l'enregistrement :

| Couleur | Signification |
| --- | --- |
| Gris | **Enregistré (déjà associé)** |
| Vert | **Ajout en attente (enregistrer pour valider)** |
| Orange | **Suppression en attente (enregistrer pour appliquer)** |

Les listes affichent uniquement les participants actifs.
L'enregistrement conserve toute association avec un participant
inactif.

Vous devez sélectionner au moins un participant.

## Problèmes courants

| Message | Que faire |
| --- | --- |
| *This primary e-mail ID already exists for another PT login* | Utilisez une autre adresse, ou modifiez le gestionnaire de données existant |
| *Veuillez sélectionner au moins un participant à associer* | Placez au moins un participant dans **Participants sélectionnés** |
| *Veuillez vérifier vos adresses e-mail principale et secondaire* | Saisissez une adresse secondaire différente de l'adresse principale |

Pour ajouter un coordinateur qui couvre une région, utilisez
plutôt le formulaire PTCC. Voir [Formulaire PTCC](ptcc-form.md).
