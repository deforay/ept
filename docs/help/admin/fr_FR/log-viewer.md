---
title: Log Viewer
summary: Read ePT's error logs to find out what went wrong, without access to the server
tags: [logs, errors, troubleshooting, debug]
---

# Afficheur de journaux

L'afficheur de journaux montre les fichiers journaux d'ePT. ePT
écrit un fichier par jour. Utilisez-le pour trouver l'erreur à
l'origine d'une page ou d'une tâche en échec.

## Trouver l'erreur à l'origine d'un problème

1. Ouvrez **Configurer → Afficheur de journaux**.
2. Dans la première liste, choisissez le jour du problème.
3. Dans la liste des niveaux, choisissez **ERROR**.
4. Saisissez un mot lié au problème dans la zone de recherche,
   comme un code d'expédition ou une adresse e-mail.
5. Si une ligne comporte **context** en dessous, cliquez sur
   **context** pour voir les détails.

Les lignes les plus récentes apparaissent en premier.

Si une page d'erreur affiche un **ID d'erreur**, cliquez sur
**Voir les journaux** sur cette page. L'afficheur de journaux
s'ouvre avec l'ID d'erreur déjà saisi dans la zone de recherche.

## Choisir le journal

Les deux onglets du haut choisissent le journal. Leurs noms
s'affichent en anglais :

- **Application Errors** : les problèmes sur le serveur.
- **Client (Browser) Errors** : les problèmes dans le navigateur
  des utilisateurs.

## Commandes

| Commande | Effet |
| --- | --- |
| Liste des fichiers | Choisit le jour. Le jour le plus récent apparaît en premier. Chaque fichier indique sa taille |
| Liste des niveaux | Affiche uniquement les lignes d'un niveau : **DEBUG**, **INFO**, **WARNING**, **ERROR** ou **CRITICAL**. **Tous les niveaux** affiche toutes les lignes |
| **Texte à rechercher (sans distinction de majuscules/minuscules)…** | Affiche uniquement les lignes qui contiennent ce texte |
| **Nombre maximal de lignes** | Le nombre de lignes à afficher, de 50 à 5000. La valeur par défaut est 500 |
| **Actualiser** | Recharge le fichier |
| **Effacer les filtres** | Efface le niveau, la recherche et la limite de lignes |
| **Supprimer le fichier** | Supprime le fichier choisi |
| **Supprimer tout** | Supprime tous les fichiers de l'onglet en cours |

Au-dessus des lignes, la page indique le nombre de lignes
trouvées, la taille du fichier et la dernière écriture d'ePT dans
le fichier.

## Supprimer d'anciens journaux

1. Choisissez le fichier.
2. Cliquez sur **Supprimer le fichier**.
3. Confirmez.

Pour supprimer tous les fichiers de l'onglet, cliquez sur
**Supprimer tout** et confirmez deux fois. Les fichiers supprimés
ne peuvent pas être restaurés. ePT enregistre chaque suppression
dans le journal de l'application.
