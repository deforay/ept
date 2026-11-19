---
title: Failed Emails
summary: Find emails that ePT could not send, and see why each one failed
tags: [email, failed, bounce, smtp, queue, delivery]
---

# E-mails non envoyés

Cette page liste les e-mails qu'ePT n'a pas pu envoyer. Chaque
ligne correspond à un e-mail de la file d'attente. La page montre
ce qui n'a pas fonctionné. Elle ne renvoie pas et ne supprime pas
les e-mails.

## Trouver pourquoi un e-mail n'est pas arrivé

1. Ouvrez **Configurer → E-mails non envoyés**.
2. Saisissez l'adresse du destinataire dans la zone de recherche.
3. Lisez le **Type de défaillance** et le **Motif de l'échec** de
   la ligne.
4. Trouvez la cause dans le tableau ci-dessous.
5. Corrigez la cause.

Si l'e-mail n'apparaît pas, réglez **Statut** sur **Tout** et
recherchez de nouveau. La ligne indique si l'e-mail attend
encore, a été envoyé ou a été arrêté.

## Signification des types de défaillance

Les valeurs de la colonne **Type de défaillance** s'affichent en
anglais.

| Type de défaillance | Cause | Que faire |
| --- | --- | --- |
| **Smtp Auth** | Le serveur de messagerie a refusé la connexion d'ePT | Corrigez l'identifiant dans la section **Paramètres de messagerie** de **Configurer → Paramètres globaux d'ePT**. Cliquez ensuite sur **Vérifier la configuration de messagerie** |
| **Connectivity** | ePT n'a pas pu joindre le serveur de messagerie | Vérifiez **Hôte SMTP** et **Port SMTP** dans **Paramètres de messagerie**. Demandez à votre administrateur serveur de vérifier le réseau |
| **Invalid Dsn** | Les paramètres de messagerie enregistrés sont inutilisables | Corrigez les paramètres dans **Paramètres de messagerie** |
| **Bad Recipient** | Le serveur de messagerie a refusé l'adresse du destinataire | Corrigez l'adresse du participant ou du gestionnaire de données |
| **Rate Limit** | Le compte de messagerie a atteint sa limite d'envoi | Patientez, ou demandez à votre fournisseur de messagerie d'augmenter la limite |
| **Content** | Le serveur de messagerie a bloqué l'e-mail comme spam | Demandez à votre fournisseur de messagerie pourquoi l'e-mail a été bloqué |
| **Expired** | L'e-mail a attendu plus de 7 jours dans la file d'attente. ePT ne l'a donc pas envoyé | Envoyez de nouveau l'e-mail depuis la page qui l'a créé |
| **Dev Trap Block** | Un paramètre de messagerie de test sur le serveur bloque tous les e-mails | Demandez à votre administrateur serveur de corriger ce paramètre |
| **Other** | Toute autre erreur | Lisez le **Motif de l'échec**. Demandez à votre administrateur serveur s'il n'est pas clair |

## Filtrer par statut

Par défaut, la liste affiche **Échecs et ignorés**. Choisissez une
autre valeur sous **Statut** pour voir d'autres e-mails.

| Statut | Signification |
| --- | --- |
| **Échec** | ePT a essayé d'envoyer l'e-mail et le serveur de messagerie l'a refusé |
| **Ignorés** | ePT n'a pas essayé d'envoyer l'e-mail, par exemple parce que les paramètres de messagerie sont inutilisables ou que l'e-mail a expiré |
| **En attente** | L'e-mail attend dans la file d'attente |
| **Envoyé** | Le serveur de messagerie a accepté l'e-mail |
| **Retenu (en pause)** | L'e-mail fait partie d'un envoi suspendu dans **Envoyer un e-mail aux participants** |
| **Annulée** | L'e-mail fait partie d'un envoi annulé dans **Envoyer un e-mail aux participants** |
| **Tout** | Tous les e-mails de la file d'attente |

## Colonnes

| Colonne | Contenu |
| --- | --- |
| **E-mail du destinataire** | L'adresse du destinataire |
| **Objet** | L'objet de l'e-mail |
| **Message** | Le début du message. Cliquez sur **View** pour lire l'e-mail complet |
| **Statut** | Le statut de l'e-mail |
| **Type de défaillance** | Le type de problème |
| **Motif de l'échec** | L'erreur du serveur de messagerie, ou la raison pour laquelle ePT a ignoré l'e-mail |
| **Mis à jour le** | La dernière modification du statut |

## Après avoir corrigé les paramètres de messagerie

ePT ne renvoie pas les e-mails en échec depuis cette page.
Envoyez de nouveau l'e-mail depuis la page qui l'a créé, comme
**Envoyer un e-mail aux participants** ou la réinitialisation du
mot de passe d'un gestionnaire de données. Votre administrateur
serveur peut aussi remettre les e-mails en échec dans la file
d'attente.

Si ePT ne peut envoyer aucun e-mail, le **Tableau de bord**
affiche une barre qui le signale. Cliquez sur **Ouvrir les
paramètres de messagerie** dans cette barre pour corriger les
paramètres.
