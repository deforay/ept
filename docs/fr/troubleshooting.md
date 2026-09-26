# Comment examiner les problèmes opérationnels

Identifiez le composant défaillant avant de modifier une installation ePT.
Ces procédures concernent ePT 7.6.21 et nécessitent les droits d'accès correspondant au composant concerné.

## Consigner l'incident

1. Relevez l'heure, le fuseau horaire, l'URL concernée, la version applicative et l'erreur visible.
2. Relevez le code d'expédition et l'identifiant du participant, si nécessaire.
3. Précisez si l'incident touche un utilisateur, une expédition ou tout le service.
4. Recueillez les journaux pertinents sans mots de passe, jetons ni fiches de participants sans rapport avec l'incident.

## Examiner le symptôme

| Symptôme | Premiers contrôles | Action suivante | Vérification |
| --- | --- | --- | --- |
| Site indisponible | Service web, connexion à la base, espace disque et certificat | Rétablissez le composant défaillant selon la procédure d'hébergement | Chargez le site et connectez-vous |
| Versions du code et de la base différentes | `php bin/check-version-sync.php` | Suivez la [procédure de correction, en anglais](../updating.md#fix-a-version-mismatch) | Le contrôle de version réussit |
| Participants ou expéditions absents | Statut du compte, associations et inscriptions | Corrigez l'attribution par l'administration autorisée | Le compte voit uniquement les données prévues |
| Réponse non modifiable | État de l'expédition, commutateur de réponse, échéance et droits | Demandez à l'administrateur du programme d'examiner l'état de collecte | Une réponse de test autorisée s'enregistre et se rouvre |
| Réponse tardive en attente de revue | Parcours de soumission pris en charge et état de retard | Faites examiner la réponse par un administrateur autorisé | Le résultat approuvé apparaît dans le processus prévu |
| Évaluation ou rapport bloqué en file | Planificateur, état des tâches, erreurs PHP et espace disque | Examinez la tâche défaillante avant de demander une nouvelle exécution | La file se termine et la sortie concerne la bonne expédition |
| Courriel non livré | Résultat en file, configuration SMTP, adresse destinataire et rejet du fournisseur | Corrigez la cause précise de l'échec. Voir [Keeping email delivery working](../email.md) (anglais) | Un message de test arrive dans la boîte prévue |
| Rapport indisponible | Génération, finalisation, association et fichiers produits | Terminez le processus de rapport autorisé | Le bon compte télécharge le rapport attendu |
| Sauvegarde absente | Planificateur, droits du répertoire cible, espace disque et configuration | Rétablissez les sauvegardes et les copies hors serveur | La nouvelle archive réussit la vérification et un test de restauration |

## Examiner les éléments applicatifs

1. Consultez les fichiers récents de `logs/` correspondant à l'heure de l'incident.
2. Faites examiner le journal d'erreurs du serveur web par l'opérateur d'hébergement.
3. Contrôlez le planificateur configuré et sa sortie d'exécution.
4. Consultez le suivi des tâches ou les résultats des courriels dans l'application, lorsqu'ils sont disponibles.

Lancez d'abord `ept check` sur le serveur. Cette commande vérifie en une fois la base de données, la version du schéma, la dernière sauvegarde, l'espace disque, les dossiers accessibles en écriture et le cron.
Utilisez `ept scripts` pour repérer les outils d'administration pris en charge.
Consultez les [outils CLI, en anglais](../cli-tools.md), pour leurs paramètres.
Ne modifiez pas directement les lignes de file ou d'évaluation pour masquer une tâche défaillante.

## Transmettre un incident non résolu

1. Fournissez la version relevée et les étapes de reproduction sans données sensibles.
2. Joignez les identifiants d'erreur et de tâche pertinents.
3. Précisez le processus concerné et l'impact opérationnel.
4. Consignez le responsable, l'action suivante et le contrôle de résolution dans le registre local des incidents.

Après réparation, répétez le processus concerné avec des données contrôlées.
Pour un défaut de rapport, examinez le PDF final, pas uniquement le code de sortie du script de génération.
