---
title: Bulk Import Enrollment
summary: Upload an Excel file of Participant IDs to fill a scheme's enrollment list, and review the changes before they are saved
tags: [enrollment, bulk-import, excel, scheme, lists]
---

# Importation en masse d'inscriptions

L'importation comporte trois étapes : **Téléverser**,
**Vérification** et **Terminé**. Rien n'est enregistré avant que
vous cliquiez sur **Importer** à l'étape Vérification.

## Étape 1 : Téléverser

1. Choisissez le **Programme**.
2. Choisissez une **Liste d'inscription**. Choisissez-en une parmi
   les suggestions ou saisissez un nouveau nom. Laissez le champ vide
   pour utiliser la liste Par défaut du programme.
3. Choisissez ce qui arrive aux **Participants déjà sur la liste
   mais absents du fichier** :
   - **Les garder sur la liste** : le fichier ajoute seulement des
     participants.
   - **Les retirer, pour que la liste corresponde au fichier** : la
     liste contient alors exactement les participants du fichier.
4. Déposez le fichier sur la page, ou cliquez pour le parcourir.
5. Cliquez sur **Vérifier l'importation**.

### Le fichier

Cliquez sur **Télécharger le modèle Excel** pour obtenir la
structure du fichier. Placez un ID de participant (ID du
laboratoire) par ligne dans la colonne A. La ligne 1 est l'en-tête.
Les autres colonnes sont ignorées.

Pour modifier une liste existante, cliquez sur **Télécharger** pour
cette liste dans Inscriptions au programme, modifiez le fichier,
puis téléversez-le ici.

Si l'en-tête de la colonne A ne correspond pas au modèle, la page
affiche ce qu'elle attendait et ce qu'elle a trouvé.

## Étape 2 : Vérification

Les vignettes comptent les lignes. Cliquez sur une vignette pour
afficher uniquement ces lignes.

| Action | Signification |
| --- | --- |
| Ajouter | Sera ajouté à la liste |
| Déjà sur la liste | Aucun changement |
| Supprimer | Sur la liste mais absent du fichier. Affiché seulement si vous avez choisi de les retirer |
| Ignorés | Le participant n'est pas actif, ou la ligne répète une ligne précédente |
| Erreur | Aucun participant n'a cet ID de participant |

Excel supprime souvent les zéros initiaux : *06157* devient *6157*.
Si exactement un participant correspond une fois les zéros rajoutés,
la ligne est utilisée et la vérification l'indique.

Cliquez sur **Importer** pour enregistrer, ou sur **Importer un autre
fichier** pour recommencer.

## Étape 3 : Terminé

La page indique combien de participants ont été ajoutés et retirés.
Cliquez sur **Ouvrir cette liste** pour voir la liste.
