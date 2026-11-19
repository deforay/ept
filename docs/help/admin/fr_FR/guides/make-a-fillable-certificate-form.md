---
title: How to make a fillable PDF certificate form
summary: For admins who prefer PDF forms. Most admins upload a design and place the details in ePT instead
audience: admin
estimated_minutes: 20
tags: [guide, certificates, templates, pdf, libreoffice, excellence, participation]
steps:
  - id: 1
    title: Open the design in LibreOffice
    target_pages: []
  - id: 2
    title: Add a text field for each detail
    target_pages: []
  - id: 3
    title: Export a PDF form
    target_pages: []
  - id: 4
    title: Upload the PDF
    target_pages: [certificate-templates/index]
  - id: 5
    title: Check the preview
    target_pages: [certificate-templates/index]
---

# Comment créer un formulaire PDF de certificat à remplir

La plupart des administrateurs n'ont pas besoin de ce guide. Il est plus rapide de téléverser une maquette et de placer les informations dans ePT. [Voir : Comment configurer un modèle de certificat](make-a-certificate-template.md)

Un formulaire PDF à remplir est l'autre type de modèle : un PDF avec un champ de formulaire à chaque endroit où figure une information, comme le nom du participant. ePT remplit les champs. Ce guide transforme votre maquette en formulaire.

Chaque programme a besoin de deux modèles : un pour **Excellence** et un pour **Participation**. Répétez ces étapes pour chacun.

Il vous faut LibreOffice. C'est une suite bureautique gratuite qui fonctionne comme Microsoft Office. Microsoft Word ne peut pas ajouter de champs de formulaire à un PDF. LibreOffice le peut, et il ouvre les fichiers Word et les PDF. Téléchargez-le sur [libreoffice.org](https://www.libreoffice.org) et installez-le. Adobe Acrobat Pro fonctionne aussi, mais les noms des menus sont différents.

Les noms de menus ci-dessous sont ceux de LibreOffice en français. Le nom anglais est indiqué entre parenthèses.

## Étape 1 : Ouvrez la maquette dans LibreOffice

- Si la maquette est un fichier Word, enregistrez-la d'abord en PDF dans Word : **Fichier → Enregistrer sous → PDF** (*File → Save As → PDF*). Ouvrez ensuite le PDF dans LibreOffice. Il s'ouvre dans **LibreOffice Draw**.
- Si la maquette est un PDF, ouvrez-le dans LibreOffice. Il s'ouvre dans **LibreOffice Draw**.
- S'il n'y a pas encore de maquette, créez-en une dans **LibreOffice Writer**. Réglez **Format → Style de page → Page → Orientation** (*Format → Page Style → Page → Orientation*) sur **Paysage** (*Landscape*).

Ne mettez dans la maquette aucune information qui change d'un participant à l'autre. Cela inclut le nom du participant et l'année. ePT les imprime.

## Étape 2 : Ajoutez un champ de texte pour chaque information

1. Choisissez **Affichage → Barres d'outils → Contrôles de formulaire** (*View → Toolbars → Form Controls*).
2. Cliquez sur **Mode Conception** (*Design Mode*) dans la barre d'outils Contrôles de formulaire, s'il n'est pas déjà activé.
3. Cliquez sur **Zone de texte** (*Text Box*).
4. Tracez une zone à l'endroit du nom du participant. Donnez-lui la largeur du nom le plus long.
5. Faites un clic droit sur la zone et choisissez **Propriétés du contrôle** (*Control Properties*).
6. Dans l'onglet **Général** (*General*), réglez **Nom** (*Name*) sur `participant_name`.
7. Réglez **Bordure** (*Border*) sur **Sans cadre** (*Without frame*).
8. Réglez **Alignement** (*Alignment*) sur **Centré** (*Center*).
9. Réglez **Police** (*Font*) sur la police et la taille d'impression du nom.
10. Fermez la fenêtre des propriétés.

Ajoutez de la même façon une zone pour chaque autre information affichée sur le certificat. Utilisez exactement les noms de champ ci-dessous. Un nom mal orthographié n'imprime rien.

| Nom du champ | Imprime |
|---|---|
| `participant_name` | Le nom du laboratoire, ou le nom de la personne pour les participants individuels. Obligatoire. |
| `lab_id` | L'ID du laboratoire du participant |
| `city` | La ville du participant |
| `country` | Le pays du participant |
| `scheme_name` | Le programme, par exemple « HIV Viral Load » |
| `assay` | La technique VL ou EID utilisée par le participant |
| `shipment_year` | L'année des expéditions couvertes par le certificat |
| `issue_date` | La date de génération des certificats |
| `signatory_name` | Le **Nom du signataire des certificats** défini dans les Paramètres globaux |
| `signatory_title` | La **Fonction du signataire des certificats** définie dans les Paramètres globaux |

[Voir : Modèles de certificats](../certificate-templates.md) pour l'origine de chaque valeur.

## Étape 3 : Exportez un formulaire PDF

1. Choisissez **Fichier → Exporter vers → Exporter au format PDF** (*File → Export As → Export as PDF*).
2. Dans l'onglet **Général** (*General*), cochez **Créer un formulaire PDF** (*Create PDF form*).
3. Cliquez sur **Exporter** (*Export*) et enregistrez le fichier.

## Étape 4 : Téléversez le PDF

1. Allez dans **Configurer → Modèles de certificats**.
2. Trouvez le programme.
3. Sous **Excellence** ou **Participation**, cliquez sur **Téléverser** ou **Remplacer**.
4. Déposez le PDF sur la zone de téléversement, ou cliquez sur la zone pour choisir le fichier.

ePT vérifie le PDF avant de l'enregistrer. Si la vérification échoue, la carte indique ce qu'il faut corriger. Corrigez-le dans LibreOffice, exportez de nouveau, puis téléversez de nouveau.

Si le PDF n'a pas de champ `participant_name`, ePT le traite comme une maquette et ouvre l'éditeur. Vérifiez le nom du champ à l'étape 2.

Si la carte liste des champs qu'ePT ne remplit pas, vérifiez chacun d'eux. ePT imprime ces champs tels qu'ils sont dans le modèle. Un champ prévu pour une information du participant porte alors un mauvais nom. Renommez-le à l'étape 2 et téléversez de nouveau.

## Étape 5 : Vérifiez l'aperçu

1. Cliquez sur **Aperçu avec un vrai participant**.
2. Vérifiez que chaque information est au bon endroit et tient dans sa zone.
3. Cliquez sur **Terminé**.

L'aperçu utilise un participant de la dernière expédition évaluée du programme. Si une information est coupée, élargissez sa zone dans LibreOffice et téléversez de nouveau.

Le bouton **Aperçu** de chaque carte de modèle affiche la même vérification à tout moment.
