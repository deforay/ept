---
title: Audit Log
summary: Find who did what in ePT, and when, including logins
tags: [audit, history, activity, login, security]
---

# Journal d'audit

Le journal d'audit enregistre ce que font les administrateurs, les
gestionnaires de données et les PTCC dans ePT, comme l'ajout d'un
participant ou l'envoi d'une réponse. Il peut aussi afficher
chaque tentative de connexion. Les entrées les plus récentes
apparaissent en premier.

## Trouver qui a modifié quelque chose

1. Ouvrez **Configurer → Journal d'audit**.
2. Sous **Plage de dates**, choisissez les jours de la
   modification.
3. Saisissez un nom, un ID du laboratoire, un code d'expédition ou
   un mot dans la zone de recherche.
4. Cliquez sur une entrée pour voir ses détails.

Pour voir tout ce qu'une personne a fait au cours d'une même
session, cliquez sur **Filtrer par cette session** dans les
détails. Vous pouvez aussi cliquer sur l'étiquette de session de
n'importe quelle entrée.

## Vérifier les connexions d'un utilisateur

1. Sous **Afficher**, cliquez sur **Connexions**.
2. Saisissez l'adresse e-mail de l'utilisateur dans **E-mail de
   l'acteur**.
3. Cherchez les entrées **Login failed** et **Login blocked**.

Cliquez sur **Tout** pour voir les actions et les connexions
ensemble.

## Filtres

| Filtre | Effet |
| --- | --- |
| **Afficher** | **Actions** (par défaut), **Connexions** ou **Tout** |
| **Plage de dates** | Affiche uniquement les entrées de ces jours |
| **Contexte** | Affiche une seule partie d'ePT, comme les participants |
| **Utilisateur** | Affiche les entrées d'un administrateur |
| **E-mail de l'acteur** | Affiche les entrées de toute personne dont l'e-mail contient ce texte. Utilisez-le pour les gestionnaires de données et les PTCC |
| **ID du laboratoire** | Affiche les entrées des gestionnaires de données de ce participant |
| **Session** | Affiche les entrées d'une même session |
| **Réinitialiser** | Efface tous les filtres |

La zone de recherche porte sur le texte de l'action, le nom et
l'e-mail de l'utilisateur, le contexte, ainsi que les noms et les
ID de laboratoire des participants.

## Détails d'une entrée

Cliquez sur une entrée pour ouvrir ses détails :

| Champ | Contenu |
| --- | --- |
| **Utilisateur** | La personne qui a agi |
| **E-mail** | L'adresse e-mail de l'utilisateur |
| **Rôle** | Admin, Data Manager, PTCC ou Participant |
| **Contexte** | La partie d'ePT concernée |
| **Adresse IP** | L'adresse depuis laquelle l'utilisateur s'est connecté |
| **Hachage de session** | Un code commun à toutes les entrées d'une même session |
| **Navigateur** et **OS** | Le navigateur et le système d'exploitation de l'utilisateur |
| **Action** | Ce que l'utilisateur a fait |

Utilisez **Par page**, en bas, pour afficher plus d'entrées par
page.
