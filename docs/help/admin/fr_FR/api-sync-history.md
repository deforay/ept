---
title: API Sync History
summary: See the requests that data managers sent to ePT through its API, with what was sent and returned
tags: [api, sync, history, data-manager, troubleshooting]
---

# Historique de la synchronisation de l'API

Cette page liste les requêtes que les gestionnaires de données
envoient à ePT par son API, depuis une autre application. Chaque
ligne correspond à une requête, comme une connexion ou des
résultats enregistrés. Utilisez cette page pour vérifier si les
résultats d'un gestionnaire de données sont arrivés dans ePT.

## Vérifier si les résultats d'un gestionnaire de données sont arrivés

1. Ouvrez **Configurer → Historique de la synchronisation de
   l'API**.
2. Sous **Sélectionner le gestionnaire de données (optionnel)**,
   choisissez le gestionnaire de données.
3. Sous **Choisissez une plage de dates (facultatif)**, choisissez
   les jours d'envoi des résultats.
4. Sous **Sélectionner le type de synchronisation (optionnel)**,
   choisissez **Save Shipments**.
5. Trouvez la ligne dans la liste.
6. Cliquez sur **Show Params** pour voir ce qui a été envoyé et ce
   qu'ePT a renvoyé.

La fenêtre comporte un onglet **REQUEST** et un onglet
**RESPONSE**. Chaque onglet affiche l'adresse appelée et les
données. Si la réponse contient `"status": "fail"`, son message
indique pourquoi ePT a refusé la requête.

Si aucune ligne ne correspond, ePT n'a pas enregistré ces
résultats. Demandez au gestionnaire de données de les envoyer de
nouveau.

## Filtres

| Filtre | Effet |
| --- | --- |
| **Choisissez une plage de dates (facultatif)** | Affiche uniquement les requêtes de ces jours |
| **Sélectionner le type de synchronisation (optionnel)** | Affiche un seul type de requête, comme **Login** ou **Save Shipments** |
| **Sélectionner le type de programme (facultatif)** | Affiche les requêtes d'un seul programme |
| **Sélectionner le gestionnaire de données (optionnel)** | Affiche les requêtes d'un seul gestionnaire de données |
| **Réinitialiser** | Efface tous les filtres |

## Colonnes

| Colonne | Contenu |
| --- | --- |
| **ID de transaction** | Un code unique pour la requête |
| **Nombre d'enregistrements synchronisés** | Le nombre d'enregistrements contenus dans la requête |
| **Type de synchronisation** | Le type de requête |
| **Type de programme** | Le programme, ou **COMMON** quand la requête ne concerne pas un programme précis |
| **URL** | L'adresse appelée |
| **Synchronisé le** | La date de réception de la requête par ePT |
| **Action** | **Show Params** |
