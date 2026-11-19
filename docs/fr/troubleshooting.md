# Comment examiner les problèmes opérationnels

Commencez par le premier tableau lorsqu'un participant ou un gestionnaire de données signale un problème.
Pour une panne du serveur, consignez d'abord l'incident. Identifiez le composant défaillant avant de modifier une installation ePT.
Ces procédures nécessitent les droits d'accès correspondant au composant concerné.

## Résoudre les problèmes signalés par les participants {#fix-problems-participants-report}

La plupart des appels viennent des participants et des gestionnaires de données. Vérifiez d'abord ces causes.

| Problème | Cause à vérifier | Solution |
| --- | --- | --- |
| Connexion impossible | Mot de passe erroné, ou compte inactif | Cliquez sur **Mot de passe oublié ?** sur la page de connexion. Si aucun e-mail n'arrive, un administrateur réinitialise le mot de passe dans **Configurer → Gestionnaire de données (connexion des participants)**. Sur la même page, il peut repasser un compte inactif à **Actif** |
| Expédition absente de **Envoi des résultats du PT** | L'enquête PT n'est pas expédiée, le participant n'est pas sur l'expédition, ou le gestionnaire de données n'est pas associé au participant | Expédiez-la dans **Gérer → Enquête PT**. Pour ajouter un participant après l'expédition, ouvrez **Gérer → Expéditions**, cliquez sur **Inscriptions** pour l'expédition, puis utilisez l'onglet **Non inscrit à cette expédition**. Associez le gestionnaire de données dans **Configurer → Correspondance participants/identifiants de connexion** |
| Réponse non modifiable | La date limite de réponse est passée, l'expédition est finalisée, ou le compte est en consultation seule | Demandez au fournisseur PT de prolonger la date limite ou d'accepter une réponse tardive |
| L'expédition affiche encore **Saisir la réponse** après **Envoyer** | Le participant n'a pas cliqué sur **Confirmer la réponse** sur la page de vérification | Cliquez de nouveau sur **Saisir la réponse** et terminez par **Confirmer la réponse** |
| Aucun rapport dans **Voir le résultat du PT → Rapport individuel** | Les rapports ne sont pas générés, ou l'expédition n'est pas finalisée | Générez les rapports, puis finalisez l'expédition dans **Analyse → Finaliser les rapports** |

## Consigner l'incident {#capture-the-incident}

1. Relevez l'heure, le fuseau horaire, l'URL concernée, la version applicative et l'erreur visible.
2. Relevez le code d'expédition et l'identifiant du participant, si nécessaire.
3. Précisez si l'incident touche un utilisateur, une expédition ou tout le service.
4. Recueillez les journaux pertinents sans mots de passe, jetons ni fiches de participants sans rapport avec l'incident.

## Examiner le symptôme {#follow-the-symptom}

| Symptôme | Premiers contrôles | Action suivante | Vérification |
| --- | --- | --- | --- |
| Site indisponible | Service web, connexion à la base, espace disque et certificat | Rétablissez le composant défaillant selon la procédure d'hébergement | Chargez le site et connectez-vous |
| Versions du code et de la base différentes | `php bin/check-version-sync.php` | Suivez la [procédure de récupération après mise à jour](updating.md#fix-a-version-mismatch) | Le contrôle de version réussit |
| La page indique « ePT requires PHP 8.4.27 or later » | `php -v` sur le serveur | Exécutez `sudo ept update`, qui met PHP à jour. Sous Windows, passez WampServer à PHP 8.4.27 ou ultérieur | Le site se charge et `ept check` réussit |
| Participants ou expéditions absents | Statut du compte, associations et inscriptions | Corrigez l'attribution par l'administration autorisée | Le compte voit uniquement les données prévues |
| Réponse non modifiable | État de l'expédition, commutateur de réponse, date limite et droits | Demandez à l'administrateur du programme d'examiner l'état de collecte | Une réponse de test autorisée s'enregistre et se rouvre |
| Réponse tardive en attente de revue | Parcours de soumission pris en charge et état de retard | Faites examiner la réponse par un administrateur autorisé | Le résultat approuvé apparaît dans le processus prévu |
| Évaluation ou rapport bloqué en file | Planificateur, état des tâches, erreurs PHP et espace disque | Examinez la tâche défaillante avant de demander une nouvelle exécution | La file se termine et la sortie concerne la bonne expédition |
| E-mail non livré | Résultat en file, configuration SMTP, adresse destinataire et rejet du fournisseur | Corrigez la cause précise de l'échec. Voir [Maintenir l'envoi des e-mails](email.md) | Un message de test arrive dans la boîte prévue |
| Rapport indisponible | Génération, finalisation, association et fichiers produits | Terminez le processus de rapport autorisé | Le bon compte télécharge le rapport attendu |
| Sauvegarde absente | Planificateur, droits du répertoire cible, espace disque et configuration | Rétablissez les sauvegardes et les copies hors serveur | La nouvelle archive réussit la vérification et un test de restauration |

## Examiner les éléments applicatifs {#inspect-application-evidence}

1. Consultez les fichiers récents de `logs/` correspondant à l'heure de l'incident.
2. Faites examiner le journal d'erreurs du serveur web par l'opérateur d'hébergement.
3. Contrôlez le planificateur configuré et sa sortie d'exécution.
4. Consultez le suivi des tâches ou les résultats d'envoi des e-mails dans l'application, lorsqu'ils sont disponibles.

Lancez d'abord `ept check` sur le serveur. Cette commande vérifie en une fois la base de données, la version du schéma, la dernière sauvegarde, l'espace disque, les dossiers accessibles en écriture et le cron.
Utilisez `ept scripts` pour repérer les outils d'administration pris en charge.
Consultez les [outils CLI](cli-tools.md) pour leurs paramètres.
Ne modifiez pas directement les lignes de file ou d'évaluation pour masquer une tâche défaillante.

## Transmettre un incident non résolu {#escalate-an-unresolved-incident}

1. Fournissez la version relevée et les étapes de reproduction sans données sensibles.
2. Joignez les identifiants d'erreur et de tâche pertinents.
3. Précisez le processus concerné et l'impact opérationnel.
4. Consignez le responsable, l'action suivante et le contrôle de résolution dans le registre local des incidents.

Après réparation, répétez le processus concerné avec des données contrôlées.
Pour un défaut de rapport, examinez le PDF final, pas uniquement le code de sortie du script de génération.
