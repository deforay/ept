# Contrôles de sécurité et exigences opérationnelles

[English](../security.md)

Ce référentiel décrit les chemins d'implémentation examinés dans ePT 7.6.21.
Il présente les contrôles et leurs limites. Il ne constitue ni un rapport de test d'intrusion ni une certification.

## Contrôles applicatifs

| Domaine | Comportement implémenté | Limite ou précision |
| --- | --- | --- |
| Authentification web | Sessions distinctes pour les administrateurs et les gestionnaires de données | Les fiches participants sont distinctes des comptes de connexion |
| Vérification des mots de passe | Les empreintes sont vérifiées avec `password_verify()` | La réinitialisation et la gestion des accès nécessitent aussi des contrôles opérateur |
| Inactivité de session | Les sessions web authentifiées normales expirent après 1 800 secondes d'inactivité | Les jetons API suivent un fonctionnement distinct |
| Connexion en tant que participant | L'activité de consultation par un administrateur est journalisée, avec une limite d'inactivité par défaut de 900 secondes | Il s'agit d'une fonction d'assistance privilégiée |
| Autorisation | Les privilèges administratifs et les associations de participants limitent les accès | Chaque point d'accès nécessite des tests d'accès autorisé et interdit |
| Contrôle CSRF | Les requêtes web modificatrices peuvent nécessiter un jeton de session et une comparaison à temps constant | Les requêtes XHR, API, CLI, les routes d'erreur et l'absence de jeton de session contournent cet auxiliaire |
| Authentification API | De nombreux services API utilisent le paramètre `authToken` d'un gestionnaire de données | Un composant commun à tout le module n'impose pas uniformément l'authentification |
| Enregistrement d'audit | Les points d'appel enregistrent les opérations, les acteurs et les horodatages | Cela ne prouve pas que chaque modification de la base est auditée |
| Métadonnées d'audit | Les colonnes disponibles contiennent le rôle, l'IP, l'agent utilisateur et l'empreinte de session | Les métadonnées dépendent de la version du schéma et du contexte de la requête |
| Traitement des entrées et sorties | Des mécanismes de validation, de requêtes paramétrées et d'échappement existent | Leur présence ne prouve pas la couverture de tous les chemins |

`SecurityService::checkCSRF()` et `Pt_Plugins_PreSetter::preDispatch()` définissent le comportement
des requêtes et des sessions. `Application_Model_DbTable_AuditLog` définit la capture des événements d'audit.
L'inventaire API décrit les [limites de l'authentification et des échanges](api-reference.md).

## Responsabilités d'hébergement

Ces éléments sont des exigences de déploiement. Ils ne décrivent pas l'état vérifié d'un serveur existant.

| Contrôle | Responsable | Preuve nécessaire |
| --- | --- | --- |
| HTTPS et renouvellement des certificats | Opérateur d'hébergement | Certificat actif, redirections et contrôle du renouvellement |
| Isolation de la base de données | Opérateur d'hébergement | Écoute réseau, pare-feu et connexions applicatives autorisées |
| Administration restreinte du serveur | Opérateur d'hébergement | Accès administratifs nominatifs et restrictions SSH |
| Logiciels pris en charge et correctifs | Opérateur d'hébergement | Versions installées et registre de maintenance |
| Protection des sauvegardes | Opérateur d'hébergement | Restrictions d'accès, paramètres de chiffrement et garde des clés de restauration |
| Revue des accès applicatifs | Administrateur du programme | Utilisateurs, privilèges et associations de participants approuvés |
| Gestion des incidents | Responsables du programme et de l'hébergement | Contacts, procédure d'escalade et registre des incidents |
| Approbation de la conservation | Responsable des données | Calendrier de conservation et procédure d'élimination approuvés |

Le chiffrement des disques, le pare-feu, les sauvegardes hors serveur et la surveillance de disponibilité
dépendent de l'hébergement. L'installation de l'application ne les met pas en place à elle seule.

## Données sensibles

Les fichiers de configuration contiennent des secrets. Les sauvegardes peuvent contenir des identifiants,
des informations de compte, des coordonnées et des données PT.
Le suivi API peut conserver les corps des requêtes et réponses dans `public/uploads/track-api/`,
y compris des données d'authentification sensibles.

Ces fichiers nécessitent un accès restreint et une revue avant leur inclusion dans un dossier d'assistance.
Le nettoyage des lignes de la base ne prouve pas la suppression des fichiers de suivi API correspondants.
Consultez le [référentiel de stockage et de conservation](data-lifecycle.md).

## Exigences non fonctionnelles à convenir

| Exigence | Éléments disponibles | Décision de déploiement |
| --- | --- | --- |
| Temps de réponse et accès simultanés | Recommandations de dimensionnement sans résultats de mesure | Charge convenue et seuils mesurés |
| Disponibilité | Application conçue pour une instance centrale | Horaires de service, objectif de disponibilité et fenêtres de maintenance |
| Objectif de temps de reprise | Procédure de restauration existante | Durée maximale d'interruption tolérée et temps de restauration mesuré |
| Objectif de point de reprise | Sauvegarde planifiée de la base existante | Perte maximale de données tolérée et fréquence des copies hors serveur |
| Assurance de sécurité | Contrôles d'implémentation documentés | Périmètre de revue, résultats des tests et acceptation des corrections |
| Accessibilité et clients pris en charge | Interface web | Navigateurs, appareils et périmètre des tests d'accessibilité convenus |
| Interopérabilité | API JSON et import/export de fichiers | Intégrations identifiées et contrats d'échange testés |
| Conformité aux normes | Aucune évaluation de conformité fournie avec cette revue | Normes applicables et preuves d'évaluation |

## Limites de validation

Aucune évaluation indépendante de sécurité n'accompagne cette documentation.
Le [dossier de validation](validation.md) propose des contrôles de sécurité.
La réussite des seuls tests fonctionnels ne démontre pas l'assurance de sécurité.
