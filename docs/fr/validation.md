# Dossier de validation

[English](../validation.md)

Ce référentiel propose une base d'acceptation pour ePT 7.6.21.
Il contient des définitions de tests et un format de preuve.
Il ne consigne ni tests exécutés ni approbation du programme.

## Couverture automatisée existante

| Outil | Couverture | Limite |
| --- | --- | --- |
| `test-harness/bin/workbook` | Matrice des interprétations acceptées au Vietnam | Comportement d'algorithme sélectionné, sans base de données ni génération de rapports |
| `test-harness/bin/dts` | Cas DTS du Vietnam et de l'algorithme actualisé à trois tests | Variantes sélectionnées, pas toutes les configurations nationales |
| `test-harness/bin/custom-test` | Évaluation qualitative des tests personnalisés | Programme personnalisé existant sélectionné |
| `composer phpstan` | Analyse statique | Ne prouve pas l'exactitude du processus ou de la notation |
| `composer cs-check` | Contrôle du style de code | Ne constitue pas une validation fonctionnelle |

Les outils de test utilisant la base créent des données fictives et lancent l'évaluation.
Ils nécessitent `APPLICATION_ENV=development` ou `testing`.
Le mode de rattachement à une expédition existante fonctionne différemment et n'offre pas la même couverture d'assertions.
Les instructions d'exécution de référence figurent dans
le [README des outils de test, en anglais](https://github.com/deforay/ept/blob/master/test-harness/README.md).

La matrice du Vietnam consigne des divergences d'interprétation connues.
Ces cas ne prouvent pas l'acceptation par le programme d'un autre pays.

## Cas d'acceptation proposés

Tous les cas ci-dessous ont le statut **Non exécuté dans le cadre de cette revue documentaire**.
Les résultats attendus sont des critères proposés. Ils ne signifient pas que le code actuel réussit les tests.
Les tests utilisent des comptes, participants et échantillons PT fictifs dans un environnement isolé.

| ID | Exigences | Scénario de test | Résultat attendu |
| --- | --- | --- | --- |
| VAL-01 | FR-01 | Importer des participants valides, puis des lignes invalides et des doublons | Les fiches valides sont identifiables. Les rejets indiquent des erreurs exploitables, sans doublons involontaires |
| VAL-02 | FR-02 | Se connecter avec deux comptes associés à des participants différents | Chaque compte accède uniquement aux données autorisées, y compris par URL directe et appel API |
| VAL-03 | FR-03, FR-04 | Créer une enquête, une expédition, un panel de référence et des inscriptions | Les données enregistrées correspondent au jeu de test approuvé |
| VAL-04 | FR-05, FR-10 | Évaluer des réponses correctes et incorrectes connues pour chaque programme local actif | Les scores et résultats correspondent aux attentes approuvées indépendamment |
| VAL-05 | FR-06, FR-07 | Soumettre et rouvrir une réponse complète avant fermeture | Les valeurs persistent et restent associées au bon participant et à la bonne expédition |
| VAL-06 | FR-08, FR-11 | Comparer les déclarations de test non réalisé, les absences de réponse et les échecs après test | Les catégories restent distinctes dans l'évaluation et les rapports |
| VAL-07 | FR-09 | Dépasser l'échéance configurée avec fermeture automatique activée | La fermeture respecte le fuseau configuré et met l'évaluation prévue en file |
| VAL-08 | FR-09 | Tester un parcours de soumission tardive pris en charge avec et sans délai de grâce | Les soumissions autorisées attendent une revue. L'approbation conserve l'historique du retard |
| VAL-09 | FR-09 | Tenter des écritures sur des expéditions finalisées ou annulées | Chaque parcours de soumission activé rejette les écritures non autorisées |
| VAL-10 | FR-12, FR-13 | Générer, examiner, finaliser et télécharger les rapports | Le contenu PDF, l'identité du participant, les scores, la présentation et les signataires correspondent aux attentes |
| VAL-11 | FR-14 | Comparer les totaux récapitulatifs à un jeu de données connu | Les totaux de réponses, tests non réalisés, réussites, échecs et exclusions concordent |
| VAL-12 | FR-15 | Mettre des messages en file vers une adresse de test | Les réussites et échecs de livraison sont visibles, sans courriels dupliqués involontairement |
| VAL-13 | FR-16 | Tester chaque API activée avec des identifiants valides, absents ou invalides et des données mal formées | Les réponses respectent le contrat convenu et les limites d'accès |
| VAL-14 | FR-17 | Restaurer une base, une configuration et des fichiers sur un serveur isolé | La connexion, les rapports historiques et un cycle PT fictif fonctionnent dans les objectifs de reprise convenus |
| VAL-15 | FR-18 | Effectuer les actions de compte, de réponse et d'administration sélectionnées | Les entrées d'audit attendues identifient l'action et son auteur |
| VAL-16 | FR-02 | Tester les comptes désactivés, l'expiration des sessions, les contournements CSRF et les changements de privilèges | Les résultats respectent les exigences de sécurité approuvées. Les écarts sont consignés |
| VAL-17 | FR-14, FR-17 | Exécuter la charge simultanée et le volume de rapports convenus | Les temps de réponse et de traitement mesurés respectent les seuils convenus |

## Fiche d'exécution

Chaque cas exécuté nécessite les champs suivants. Une fiche vide ne prouve pas la réussite d'un test.

| Champ | Contenu requis |
| --- | --- |
| Identifiant du cas | Cas d'acceptation et variante locale |
| Référence de version | Version applicative, commit ou identifiant de livraison, version de la base |
| Environnement | Instance de test, versions d'exécution, paramètres actifs des programmes et fuseau horaire |
| Conditions préalables | Comptes, rôles, associations et état de l'expédition |
| Données de test | Entrées fictives et résultats attendus approuvés indépendamment |
| Exécution | Étapes réalisées, exécutant et horodatage |
| Résultat réel | Valeurs et comportement observés |
| Preuves | Captures, journaux, rapports exportés et sorties des tests, sans données sensibles |
| Verdict | Réussi, échec ou bloqué, avec motif |
| Anomalie | Référence de suivi, gravité et résultat du nouveau test |
| Revue | Relecteur, date et décision d'acceptation |

## Acceptation d'une version

Le responsable du programme approuve les programmes actifs, la notation attendue, le contenu des rapports et les processus.
Le responsable de l'hébergement approuve les preuves opérationnelles et de reprise.
Le responsable concerné doit statuer explicitement sur chaque constat de sécurité.

Les anomalies ouvertes, exceptions et cas non exécutés restent visibles dans le dossier d'acceptation.
Un test technique ne remplace ni l'acceptation utilisateur ni une évaluation indépendante de sécurité.
