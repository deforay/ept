---
title: PTCC Form
summary: Add or edit a PT Country Coordinator and choose the countries, provinces or districts they cover
tags: [ptcc, coordinator, tb, region, form]
---

# Formulaire PTCC

Utilisez ce formulaire pour ajouter ou modifier un coordinateur PT
de pays (PTCC). Un PTCC couvre un ou plusieurs pays, provinces ou
districts. ePT associe le PTCC à tous les participants de cette
zone.

## Ajouter un PTCC

1. Ouvrez **Configurer → Gérer le PTCC**.
2. Cliquez sur **Ajouter un nouveau PTCC**.
3. Saisissez l'**Identifiant (e-mail principal)**. Cette adresse
   est l'identifiant du PTCC.
4. Saisissez le **Mot de passe** et **Confirmer le mot de passe**.
5. Saisissez le **Prénom de l’utilisateur**.
6. Sous **Pays**, tapez au moins trois lettres du nom d'un pays.
   Choisissez le pays dans la liste. Répétez pour chaque pays.
7. Facultatif : sous **État/Province**, choisissez les provinces
   à couvrir.
8. Facultatif : sous **District**, choisissez les districts à
   couvrir.
9. Cliquez sur **Ajouter**.

ePT demande au PTCC de changer son mot de passe à sa première
connexion.

## Modifier un PTCC

1. Dans la liste des PTCC, cliquez sur **Modifier** sur la ligne.
2. Modifiez les champs ou la zone.
3. Cliquez sur **Sauvegarder**.

Pour conserver le mot de passe actuel, laissez **Mot de passe** et
**Confirmer le mot de passe** vides. Si vous saisissez un nouveau
mot de passe, ePT demande au PTCC de le changer à sa prochaine
connexion.

## Fonctionnement de la zone

| Vous choisissez | Le PTCC couvre |
| --- | --- |
| Un pays seulement | Tous les participants de ce pays |
| Un pays et certaines de ses provinces | Tous les participants de ces provinces |
| Une province et certains de ses districts | Tous les participants de ces districts |

À l'enregistrement, ePT associe le PTCC aux participants de la
zone. Il retire les participants situés hors de la zone. Quand
vous enregistrez un participant, ePT l'associe aux PTCC dont la
zone le contient. Une tâche quotidienne rattrape tout participant
oublié par ces étapes.

Un participant est dans la zone quand son pays, sa province et son
district correspondent. Vérifiez ces champs sur le participant si
un PTCC ne voit pas ce participant.

## Champs

| Champ | Obligatoire | Effet |
| --- | --- | --- |
| **Identifiant (e-mail principal)** | Oui | L'identifiant et l'adresse e-mail principale du PTCC. Aucun autre compte ne peut l'utiliser |
| **Mot de passe** | Oui, à l'ajout | Le mot de passe du PTCC. Il doit compter au moins autant de caractères que la **Longueur du mot de passe** définie dans **Paramètres globaux d'ePT**. Il doit contenir une lettre et un chiffre. Il ne doit pas contenir le nom du PTCC |
| **Confirmer le mot de passe** | Oui, à l'ajout | Le même mot de passe une seconde fois |
| **Établissement** | Non | L'établissement du PTCC |
| **Prénom de l’utilisateur** | Oui | Le prénom du PTCC |
| **Nom de famille de l'utilisateur** | Non | Le nom de famille du PTCC |
| **Adresse électronique secondaire** | Non | Une deuxième adresse. Elle doit différer de l'e-mail principal |
| **Numéro de téléphone portable** | Non | Le numéro de portable du PTCC |
| **Numéro de téléphone** | Non | Un autre numéro de téléphone |
| **Activer l'accès en consultation seule** | Non | **Oui** permet au PTCC de voir les résultats sans les modifier. La valeur par défaut est **Non** |
| **Statut** | Oui | Les PTCC **Actif** peuvent se connecter. Les PTCC **Inactif** ne le peuvent pas |
| **Pays** | Oui | Les pays couverts par le PTCC |
| **État/Province** | Non | Limite la zone à ces provinces |
| **District** | Non | Limite la zone à ces districts |

## Transformer un gestionnaire de données en PTCC

Quand vous ajoutez un PTCC avec l'e-mail d'un gestionnaire de
données existant, ePT demande s'il faut ajouter cet utilisateur
comme PTCC.

- Cliquez sur **OK** pour transformer le gestionnaire de données
  en PTCC. ePT retire les anciennes associations de l'utilisateur
  avec des participants et l'associe aux participants de la
  nouvelle zone.
- Cliquez sur **Annuler** pour effacer l'e-mail et en saisir un
  autre.

Pour ajouter un gestionnaire de données pour un ou plusieurs
laboratoires, voir [Formulaire du gestionnaire de
données](data-managers-form.md).
