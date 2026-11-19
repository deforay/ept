---
title: PDF Report Settings
summary: Letterhead, logo, PDF template, layout and approver used on generated reports
tags: [config, reports, pdf, logo, template, letterhead, approver]
---

# Paramètres du rapport PDF

Cette page contrôle l'**apparence** de tous les rapports PDF
produits par le système : rapports des participants, rapports de
synthèse et certificats.

Le menu latéral liste les trois sections. Cliquez sur le nom d'une
section pour y aller. Saisissez du texte dans **Rechercher dans les
paramètres** pour n'afficher que les champs dont le libellé
correspond. **Développer tout** et **Tout réduire** ouvrent ou
ferment toutes les sections.

## En-tête

ePT construit l'en-tête à partir de ces paramètres. Si un modèle
PDF est téléversé dans la section suivante, c'est le modèle qui
fournit l'en-tête. Ces paramètres ne sont alors pas utilisés.

- **Texte d'en-tête du rapport** *(obligatoire)* : le texte en
  haut de chaque rapport. Limitez-le à une ou deux lignes. Un long
  texte pousse le reste de la page vers le bas.
- **Position de l'adresse de l'institut** : l'endroit où apparaît
  l'adresse de l'institut définie dans *Global Config*.
  - **En-tête** : sous le titre du rapport.
  - **Pied de page** : dans le pied de page.
  - **Ne pas afficher** : nulle part.
- **Logo du rapport** : le logo imprimé sur chaque rapport. La
  zone affiche le logo actuel. Utilisez **Sélectionner une image**
  ou **Changer** pour en téléverser un. Utilisez **Supprimer** pour
  le retirer. Un PNG ou un JPG d'environ 200×150 pixels donne le
  meilleur résultat.

## Modèle PDF

Cette section est facultative. Un PDF téléversé ici devient le
fond de page de chaque rapport, par exemple un papier à en-tête
préimprimé avec un filigrane et des lignes de pied de page.

- **Modèle de rapport** : le fichier PDF. Quand un modèle est
  téléversé, un aperçu s'affiche avec trois boutons :
  - **Afficher en taille réelle** : ouvre le PDF.
  - **Remplacer** : téléverse un nouveau fichier à sa place.
  - **Supprimer** : retire le modèle. Une confirmation vous est
    demandée.
- **Marge supérieure du modèle** : la distance, en pixels, entre
  le haut de la page et le début du contenu du rapport, pour ne
  pas recouvrir l'en-tête du modèle. Une valeur courante est `40`.

Seuls les fichiers `.pdf` sont acceptés.

## Mise en page et sortie

- **Mise en page du rapport** : la mise en page utilisée par les
  rapports. Ce champ s'affiche seulement si l'application a au
  moins une mise en page.
- **Rapports pour les soumissions exclues** : *OUI* produit aussi
  des rapports pour les participants dont la soumission a été
  exclue de l'évaluation. Les participants qui n'ont pas répondu
  ne reçoivent jamais de rapport.
- **Nom de l'approbateur par défaut** : proposé comme approbateur
  quand une expédition est finalisée. Les rapports finalisés avant
  l'enregistrement d'un approbateur impriment ce nom. La personne
  qui finalise une expédition peut le changer pour cette
  expédition.

## Enregistrer

Cliquez sur **Mettre à jour** pour enregistrer tout le formulaire,
y compris un logo ou un modèle téléversé. Les boutons **Mettre à
jour** et **Retour** restent visibles pendant le défilement. Les
champs obligatoires sont marqués d'un `*`.

## Conseils

- Les modifications s'appliquent aux prochains rapports produits
  par ePT. Testez-les en régénérant les rapports d'une expédition
  depuis *Analyse → Générer les rapports*.
- Si vous remplacez le logo ou le modèle, régénérez les brouillons
  de rapport importants. Les rapports finalisés gardent
  l'apparence qu'ils avaient au moment de la finalisation.
- Si le contenu du rapport chevauche l'en-tête du modèle,
  augmentez la **Marge supérieure du modèle**. Si la première page
  a un grand espace vide, diminuez-la.
