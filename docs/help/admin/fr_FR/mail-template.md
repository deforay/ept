---
title: Mail Template
summary: Edit the sender, subject and message of the emails ePT sends about shipments and reports
tags: [config, email, mail template, notifications]
---

# Modèle d'e-mail

Cette page contient les e-mails standard qu'ePT envoie, comme l'e-mail
de nouvelle expédition et le rappel aux participants qui n'ont pas
répondu. Chaque e-mail a son propre modèle.

Ouvrez **Configurer → Modèle d'e-mail**.

## Modifier un modèle

1. Cliquez sur le nom du modèle pour l'ouvrir. L'onglet **Modèle
   d'e-mail** liste ces modèles :
   - **Modèle d'e-mail pour les nouvelles expéditions**
   - **Modèle d'e-mail de non-réponse**
   - **Modèle d'e-mail de prolongation de la date limite**
   - **Modèle d'e-mail pour le rapport de non-participation**
   - **Modèle d'e-mail pour l'envoi du rapport au participant**
   - **Modèle d'e-mail d'annonce**
2. Dans **Destiné à**, choisissez à qui s'adresse la version.
3. Saisissez le **Nom de l'expéditeur** et l'**E-mail de
   l'expéditeur**.
4. Facultatif : saisissez des adresses dans **Cc** et **Bcc**.
   Séparez plusieurs adresses par des virgules.
5. Saisissez l'**Objet**.
6. Rédigez le **Message**. Insérez des clés de la liste **Clé** pour
   fusionner des informations comme le nom du participant ou le code
   de l'expédition.
7. Facultatif : rédigez un **Pied de page**.
8. Cliquez sur **Mettre à jour**. La page affiche *Mis à jour avec
   succès.*

**Mettre à jour** enregistre uniquement le modèle auquel le bouton
appartient.

## Versions pour chaque public

**Destiné à** propose trois choix : **Participants**, **Gestionnaires
de données** et **Gestionnaires PTCC**. Chaque choix est une version
distincte du modèle.

- **Participants** : ePT utilise cette version pour les e-mails qu'il
  envoie de lui-même. **Envoyer un e-mail aux participants** la
  propose aussi.
- **Gestionnaires de données** et **Gestionnaires PTCC** : **Envoyer
  un e-mail aux participants** propose ces versions. Une version
  existe dès que vous l'enregistrez.

## Clés

La liste **Clé** affiche les clés valables dans le modèle ouvert.
Copiez une clé dans le message exactement comme elle apparaît.

Il existe deux styles de clés :

- La plupart des modèles utilisent des clés comme `##NAME##` et
  `##SHIPCODE##`.
- Le **Modèle d'e-mail de non-réponse**, le **Modèle d'e-mail de
  prolongation de la date limite** et les versions pour les
  gestionnaires de données et les gestionnaires PTCC utilisent des
  clés comme `{{participant_name}}` et `{{shipment_code}}`.

Les versions pour les gestionnaires de données et les gestionnaires
PTCC proposent aussi `{{lab_table}}`, un tableau des laboratoires du
destinataire.

Pour envoyer un e-mail ponctuel, utilisez
[Envoyer un e-mail aux participants](email-participants.md).
