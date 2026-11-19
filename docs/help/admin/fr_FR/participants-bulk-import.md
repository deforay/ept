---
title: Bulk Import Participants
summary: Upload an Excel or CSV file, review what will change, then import many participants at once
tags: [participants, bulk-import, excel, upload]
---

# Importation groupée des participants

L'importation groupée ajoute ou met à jour de nombreux participants
à partir d'un seul fichier. Elle se fait en trois étapes :
**Téléverser**, **Vérification** et **Terminé**. Rien n'est
enregistré avant que vous cliquiez sur **Importer** sur la page de
vérification.

## Étape 1 : préparer le fichier

1. Cliquez sur **Télécharger le modèle Excel**.
2. Remplissez une ligne par participant. Gardez la ligne d'en-tête
   et l'ordre des colonnes.
3. Mettez la colonne **ID du participant** au format Texte. Sinon,
   Excel supprime les zéros initiaux et `00123` devient `123`.

Les colonnes obligatoires sont :

| Colonne | Contenu |
| --- | --- |
| **ID du participant** | L'ID du laboratoire. Il doit être unique |
| **Participant individuel (oui/non)** | `yes` pour une personne, `no` pour un laboratoire ou une clinique |
| **Prénom ou nom du laboratoire** | Pour un laboratoire ou une clinique, le nom du laboratoire |
| **Nom du pays** | Choisissez dans la liste déroulante du modèle |
| **Adresse e-mail** | L'adresse e-mail du laboratoire |

Le **Mot de passe** est facultatif. Si le mot de passe est vide,
le participant reçoit le mot de passe par défaut commun.

## Étape 2 : choisir les options et téléverser

**Si un ID du participant existe déjà dans ePT :**

- **Ajouter uniquement les nouveaux** ne modifie pas les
  participants existants et ignore leurs lignes.
- **Mettre à jour toutes les informations** remplace chaque valeur
  par celle du fichier. Une cellule vide efface la valeur
  enregistrée. Cochez **Réinitialiser également leurs mots de
  passe** pour définir aussi de nouveaux mots de passe.
- **Mettre à jour uniquement l'adresse e-mail** modifie seulement
  l'adresse e-mail des participants existants.

**Même adresse e-mail pour plusieurs participants :** choisissez
**Autoriser** pour qu'un gestionnaire de données s'occupe de
plusieurs participants.

Déposez le fichier sur la page ou cliquez pour le chercher.
Cliquez ensuite sur **Vérifier l'importation**.

## Étape 3 : vérification

La page de vérification montre ce qui va arriver à chaque ligne.
Rien n'est encore enregistré.

- Les onglets répartissent les lignes en **Nouveaux
  participants**, **Sera mis à jour**, **Déjà à jour** et
  **Erreurs**.
- **(effacé)** signale une valeur qu'une cellule vide va effacer.
  Pour garder la valeur, remplissez la cellule et téléversez de
  nouveau le fichier.
- Les avertissements en haut de la page listent les
  réinitialisations de mot de passe, les participants inactifs qui
  redeviennent actifs et les noms de pays qu'ePT enregistrera
  autrement.
- **Télécharger les lignes qui ne seront pas importées** enregistre
  les lignes en erreur. Vous pouvez les corriger et les téléverser
  de nouveau.

Cliquez sur **Importer … ligne(s)** pour enregistrer. Pour
recommencer, cliquez sur **Importer un autre fichier**.

## Étape 4 : terminé

La dernière page indique combien de participants ont été créés et
mis à jour. Vérifiez-en quelques-uns dans **Configurer →
Participants au PT**.

## Dépannage

- **Le fichier téléchargé ne correspond pas au modèle.** Les
  en-têtes de colonne sont incorrects. Le tableau affiche chaque
  **En-tête prévu** à côté de ce qui a été **Trouvé dans votre
  fichier**. Téléchargez de nouveau le modèle et copiez-y vos
  données.
- **Un ID du participant a perdu ses zéros initiaux.** La colonne
  n'était pas au format Texte. Corrigez les ID dans le fichier et
  téléversez-le de nouveau.
- **Une ligne est dans Erreurs.** La ligne indique pourquoi. Les
  causes fréquentes sont une valeur obligatoire manquante, une
  adresse e-mail non valide, ou une adresse e-mail déjà utilisée
  alors que **Autoriser** n'est pas choisi.
