# Inventaire des routes API

Cet inventaire décrit les contrôleurs présents dans ePT 7.6.21.
Il identifie les points d'entrée d'intégration. Il ne constitue ni un contrat client complet ni une évaluation de sécurité.
Les routes Zend par défaut utilisent `/api/{controller}/{action}`.

## Conventions des requêtes

| Convention | Implémentation |
| --- | --- |
| Corps de connexion | JSON avec `userId` et `key`, où `key` désigne le mot de passe |
| Identifiant d'authentification API | Paramètre `authToken` utilisé par de nombreuses méthodes de service |
| Paramètres de lecture | Les contrôleurs appellent généralement `getAllParams()` |
| Données d'écriture | Les contrôleurs décodent généralement `php://input` en JSON |
| Résultats applicatifs | De nombreuses réponses JSON incluent `status`, `message` et parfois `data` |
| Valeurs d'échec | Incluent `fail` et `auth-fail`, selon le service |
| Téléchargements | Les actions de téléchargement des rapports rendent des vues et ne renvoient pas toujours uniquement du JSON |

Le statut HTTP ne suffit pas à établir la réussite applicative.
Les enveloppes de réponse et les champs obligatoires varient selon le service.
Plusieurs actions ne vérifient pas explicitement la méthode HTTP.
Les entrées ci-dessous décrivent l'analyse par le contrôleur, pas des restrictions de méthode imposées.

## Connexion et initialisation

| Route | Entrée | Fonction |
| --- | --- | --- |
| `/api/login` | JSON en POST | Authentifier un gestionnaire de données |
| `/api/login/change-password` | JSON en POST | Modifier le mot de passe d'un gestionnaire de données |
| `/api/login/forget-password` | JSON en POST | Demander la récupération du mot de passe |
| `/api/login/login-details` | Paramètres de requête | Obtenir les informations de connexion |
| `/api/init/get` | Paramètres de requête | Obtenir les listes de référence |

Exemple de corps de connexion avec des identifiants fictifs :

```json
{"userId":"user@example.org","key":"EXAMPLE_PASSWORD"}
```

Une connexion réussie renvoie un `authToken` dans `data` et remplace le jeton enregistré du compte.
La rotation des jetons et les contrôles de profil suivent des chemins d'implémentation distincts.
Le délai d'inactivité web ne correspond pas à la durée de validité du jeton API.

## Routes des expéditions

| Route | Entrée | Fonction |
| --- | --- | --- |
| `/api/shipments/get` | Paramètres de requête | Obtenir les informations d'expédition |
| `/api/shipments/get-shipment-form` | Paramètres de requête | Obtenir les informations du formulaire d'expédition |
| `/api/shipments/save-form` | Corps JSON | Enregistrer les informations du formulaire d'expédition |
| `/api/shipments/dts` | Paramètres de requête | Obtenir les données d'expédition DTS |
| `/api/shipments/save-dts` | Corps JSON | Soumettre les réponses DTS |
| `/api/shipments/vl` | Paramètres de requête | Obtenir les données d'expédition de charge virale |
| `/api/shipments/save-vl` | Corps JSON | Soumettre les réponses de charge virale |
| `/api/shipments/eid` | Paramètres de requête | Obtenir les données d'expédition EID |
| `/api/shipments/save-eid` | Corps JSON | Soumettre les réponses EID |
| `/api/shipments/custom-tests` | Paramètres de requête | Obtenir les données d'expédition des tests personnalisés |
| `/api/shipments/save-custom-tests` | Corps JSON | Soumettre les réponses des tests personnalisés |

Le service partagé d'enregistrement des réponses attend un `authToken` et une collection `data`.
Les enregistrements incluent `schemeType`, `shipmentId`, `participantId` et les champs propres au programme.
Les valeurs de sélection incluent `dts`, `vl`, `eid` et `custom-tests`.
Cette dernière est une valeur de sélection API, distincte du libellé `generic` utilisé ailleurs.

La route du contrôleur ne détermine pas à elle seule le programme du corps de soumission.
Le service vérifie la possibilité de modifier l'expédition et les champs obligatoires.
Les schémas complets des corps de requête et les cas de rejet nécessitent une vérification pour chaque client prévu.

## Routes des participants et des rapports

| Route | Entrée | Fonction |
| --- | --- | --- |
| `/api/participant/get` | Paramètres de requête | Obtenir les informations du rapport individuel |
| `/api/participant/summary` | Paramètres de requête | Obtenir les informations du rapport récapitulatif |
| `/api/participant/get-filter` | Paramètres de requête | Obtenir les filtres des participants |
| `/api/participant/get-profile-check` | Paramètres de requête | Obtenir les informations de vérification du profil |
| `/api/participant/update-profile` | Corps JSON | Mettre à jour les informations du profil |
| `/api/participant/download` | Paramètres du lien généré | Rendre le contenu de téléchargement du rapport individuel |
| `/api/participant/download-summary` | Paramètres du lien généré | Rendre le contenu de téléchargement du rapport récapitulatif |
| `/api/participant/resend` | Paramètre de requête encodé | Renvoyer la vérification du courriel |
| `/api/participant/file-downloads` | Paramètres de requête | Obtenir les informations de téléchargement des certificats |
| `/api/aggregated-insights` | Le contrôleur accepte les paramètres de requête | Renvoyer des informations sur l'instance et des données agrégées |

## Limites des intégrations {#integration-boundaries}

La méthode examinée du service aggregated-insights ne vérifie pas `authToken`.
Les actions de téléchargement utilisent une gestion distincte des liens.
Affirmer que toutes les routes API exigent la même authentification n'est pas justifié.

Cet inventaire ne contient pas d'actions dédiées aux réponses TB, DBS, recency ou COVID-19.
Les données de référence recency apparaissent dans les références DTS.
Cela ne démontre pas l'existence d'une API dédiée à la soumission recency.

Aucune route implémentée ne prouve une intégration active avec un autre système national.
Le dossier d'intégration doit préciser le propriétaire du client, les routes activées, la fréquence des échanges
et des exemples de données. Il doit aussi inclure l'authentification et les tests d'acceptation exécutés.

## Sources de l'implémentation

Contrôleurs : `application/modules/api/controllers/`.
Services : `ApiServices.php`, `Shipments.php`, `Participants.php` et `DataManagers.php`.
Gestion des comptes et jetons : `application/models/DbTable/DataManagers.php`.
