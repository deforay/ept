# Normes d'ingénierie

ePT est une application Zend Framework 1 ancienne, maintenue par une petite équipe et
déployée comme une installation centrale unique. Cela détermine la nature de ce document : un
niveau d'exigence pour les modifications, pas un guide de style. Il consigne les quelques invariants
qu'un lecteur ne peut pas déduire du code, car ce sont ceux qui sont cassés.

## 1. La passe de revue {#1-the-review-pass}

Toute modification non triviale fait l'objet d'une lecture critique avant son intégration, selon le
brief ci-dessous. Lancez-la avant de pousser, pas après qu'on vous l'a demandé :

```bash
bin/dev/review                  # the working branch against master
bin/dev/review <commit-sha>     # one commit
bin/dev/review --uncommitted    # the working tree, before committing
bin/dev/review --since-last     # every commit not yet recorded as reviewed
bin/dev/review --pending        # list commits not yet recorded as reviewed
bin/dev/review --mark [sha]     # record a commit (default HEAD) as reviewed
bin/dev/review --verify <...>   # add a pass that tries to refute the first one's findings
```

`--verify` existe parce qu'une passe unique qui trouve et rapporte n'a rien qui la contredise,
et c'est là que se trouvent les faux positifs. La seconde passe reçoit les constats et une seule
tâche : les réfuter. Elle rapporte uniquement ce qui résiste, avec le fichier et la ligne qui
tranchent chaque point. Elle double le coût d'une exécution. Elle est donc facultative.
`REVIEW_VERIFY=1` dans `.env` en fait le comportement par défaut.

Le registre utilisé par `--since-last`, `--pending` et `--mark` se trouve dans
`$(git rev-parse --git-common-dir)/reviewed-commits.txt`. Il est propre à chaque clone, partagé par
ses worktrees et jamais commité. `REVIEW_LEDGER` dans `.env` le déplace. Un commit est
enregistré uniquement lorsque toutes les passes de sa revue sont terminées. Un relecteur qui échoue,
ou qui se termine sans afficher de revue, n'enregistre rien.

L'outil en ligne de commande de revue est désigné par `REVIEW_AGENT` et `REVIEW_AGENT_ARGS`, depuis
le shell ou le fichier `.env` non suivi (voir `.env.example`). Aucun nom d'outil n'entre ainsi dans le
dépôt.

**Le brief de revue** : source unique de vérité. Le script le lit tel quel dans la version anglaise de cette page (`docs/engineering-standards.md`), il ne peut donc pas diverger. Il reste en anglais ici :
> "You are reviewing a change to ePT: a Zend Framework 1 proficiency-testing platform running as a single central instance, where administrators configure shipments and participating laboratories submit results that per-scheme algorithms evaluate into reports. Do not summarize the code. Find: (1) any admin grid or listing query that derives the data manager from the admin session directly instead of `Common::getScopedDmId()`, and any participant, shipment or result query that drops the active-record restriction; (2) any evaluation path that writes `final_result` as something other than 1/2/3/4 (Pass/Fail/Excluded/NotEvaluated), scores a non-responder as a failure without going through `Evaluation::excludeNonResponder()`, or branches on the report-layout template name where it should read `global_config.instance`; (3) a migration under `database/migrations/` that is not re-runnable — `migrate.php` re-runs the current version by design, so DDL needs `handle_idempotent_ddl` or name-based idempotence, and `PREPARE`/`EXECUTE` are silently dropped by the parser — or a schema change made by editing `sql/init.sql`, or a migration that fails to bump both `APP_VERSION` in `constants.php` and `system_config.app_version`; (4) output reaching the wrong escaping helper: `jsTranslate` for JavaScript context, `htmlTranslate` for HTML attributes, `safeTranslate` as the dispatcher, and any participant-supplied text interpolated into a `.phtml` unescaped; (5) a participant response write that does not check `isShipmentEditable`, or code reading the defunct `evaluation_status` / `response_after_evaluate` columns instead of the response deadline; (6) wiring that fails silently — a new `scheduled-jobs` script missing from `ALLOWED_JOB_SCRIPTS`, a mass-email action without an admin `confirm()`, or a per-participant feature added to only one of the three per-shipment listing pages (admin evaluate, reports distribution, reports finalize); (7) a change to report or chart generation verified only at component level, where the real failure mode is a malformed final PDF; (8) a defect fixed in one copy of code that has near-identical siblings — one scheme's `response.phtml`, one scheme's response save in `application/services/Shipments.php`, or one layout under `scheduled-jobs/report-layouts/` — while the other copies keep it; (9) tests or test-harness expectations that assert the happy path but would still pass if the invariant they guard were deleted. Carry the evidence: every claim about code names the file and line and quotes the line it rests on. If you cannot find the line that proves a claim, report the finding as UNVERIFIED and say what you looked for — that is a third outcome alongside a finding and a clear, and it is always preferable to asserting on thin evidence. When a hand-maintained list is missing an entry, trace the whole path and report every entry it is missing, not the first. If the commit message or a comment already addresses a concern, say why that reasoning is insufficient rather than restating the concern. Report a defect outside the diff separately, not ranked among the diff's findings. Rank findings by consequence, not by feel: P1 is wrong scores or reports reaching participants, lost participant or shipment data, or an instance unable to upgrade; everything else starts at P2. If you find nothing in a category, say 'clear' — don't pad, and never reach for a finding to avoid an empty category."

**Là où le second avis compte le plus :** les algorithmes d'évaluation propres à chaque programme,
chaque migration, les fonctions d'aide de restriction par gestionnaire et d'exclusion des non-répondants dans
`application/services/`, les écritures des réponses des participants, et la génération des rapports et des graphiques.
Les modifications CRUD courantes et les changements de texte n'en ont pas besoin. N'en faites pas
un rituel coûteux pour une équipe de cette taille.

**Ce qui constitue un constat :** un défaut accompagné d'un scénario d'échec, c'est-à-dire des entrées
ou un état concrets, et la sortie erronée ou la perte de données qui en résulte. Un compromis que ce
code a déjà fait délibérément est une réfutation, pas une correction. Dites-le plutôt que de
le signaler. Traitez ou réfutez explicitement chaque constat avant l'intégration. Une
réfutation est une phrase qui explique pourquoi le code est correct, pas un silence.

**Règle d'arrêt :** une passe qui ne renvoie aucun constat P1. Pas aucun constat, mais aucun
P1. Les constats moins graves sont corrigés ensuite sur `master`, comme un travail ordinaire.

**La correction est le commit le plus risqué.** La correction d'un constat de revue est du code nouveau
écrit sous pression, et c'est là qu'apparaît le plus souvent le P1 suivant. Faites aussi relire
la correction. Sauter cette relecture parce que l'original a déjà passé la revue est la pire
économie possible.

**Depuis un worktree, faites la revue commit par commit.** Sans argument, le script compare
avec la branche `master` locale, qui peut contenir des commits non poussés d'une autre session.
Leurs modifications apparaissent alors comme des constats dans des fichiers que votre branche n'a jamais touchés.

## 2. Invariants à signaler au relecteur {#2-invariants-a-reviewer-needs-told}

Ils sont listés ici pour qu'il n'y ait qu'un seul endroit à mettre à jour lorsqu'ils changent. Le
brief ci-dessus les nomme. Ce tableau indique où chacun est défini.

| Invariant | Emplacement |
| --- | --- |
| Restriction par gestionnaire de données pour les grilles d'administration | `Common::getScopedDmId()` dans `application/services/Common.php` |
| Non-répondants exclus du calcul des scores | `Evaluation::excludeNonResponder()` dans `application/services/Evaluation.php` |
| Possibilité de modifier une réponse | `isShipmentEditable()` dans `application/models/DbTable/ShipmentParticipantMap.php` |
| Échappement selon le contexte de sortie | `library/Pt/Commons/TranslateUtility.php`, voir [Traduction](TranslationGuide.md) |
| Les changements de schéma sont des migrations | `database/migrations/` ; `sql/init.sql` est un jeu initial, jamais modifié pour effectuer un changement |
| Synchronisation des versions | `APP_VERSION` dans `constants.php` et `system_config.app_version`, contrôlés par `bin/check-version-sync.php` |
| Liste blanche de la file des tâches | `ALLOWED_JOB_SCRIPTS` dans `scheduled-jobs/execute-job-queue.php` |

## 3. Faire une modification {#3-making-a-change}

Lisez la documentation d'architecture avant de modifier une partie que vous ne connaissez pas. Comprenez le
modèle existant plutôt que d'en ajouter un second.

- Gardez les contrôleurs légers. Placez la logique dans `application/services/`.
- Réutilisez les fonctions d'aide de `library/Pt/Commons/` et `application/services/` avant d'en écrire de nouvelles.
- Placez chaque changement de schéma dans `database/migrations/`. Ne modifiez jamais `sql/init.sql` pour effectuer un changement.
- Mettez à jour la documentation dans la même modification, pas après.

## 4. Conventions de documentation {#4-documentation-conventions}

Les pages existantes ne les suivent pas toutes. Appliquez-les à tout ce que vous écrivez ou
modifiez en profondeur, plutôt que de reformater des pages que vous ne touchez pas
par ailleurs.

| Règle | Détail |
| --- | --- |
| Type de page | Un seul objectif par page. Un guide pratique donne des étapes. Une référence liste des options. Ne mélangez pas les deux sur une même page. |
| Titres | Majuscule initiale uniquement. Écrivez « Supported test schemes », pas « Supported Test Schemes ». Conservez les majuscules des noms de produits et des sigles. |
| Phrases | Courtes et directes. Au présent. Commencez les instructions par le verbe. |
| Nouveaux noms de fichiers | En kebab-case, par exemple `backup-and-migration.md`. `ARCHITECTURE.md` et `SchemeArchitecture.md` gardent leur nom, car les renommer casse des URL publiées. |
| Encadrés | Utilisez des citations `> **Title:**`. Les admonitions MkDocs (`!!! warning`) s'affichent comme du texte brut sur GitHub, et ces pages y sont lues autant que sur le site. |
| HTML brut | Évitez-le. Le Markdown à l'intérieur d'un bloc HTML ne s'affiche pas sur GitHub, donc les grilles de cartes Material cassent l'affichage GitHub. |
| Ajout d'une page | Ajoutez-la au `nav` de `mkdocs.yml` et au tableau « Where to start » de [l'index de la documentation](README.md). Une page absente de l'un ou de l'autre est difficile à trouver. |
| Noms des programmes | Respectez la table `scheme_list`. Le tableau des [programmes pris en charge](README.md#supported-test-schemes) fait référence. |

Exécutez `mkdocs build --strict` avant de pousser. Le workflow de déploiement l'utilise. Un
lien cassé ou une référence erronée fait donc échouer la construction au lieu d'être publié.

## 5. Maintenir les références versionnées {#5-maintaining-versioned-references}

Mettez à jour la version revue sur les pages de référence concernées lorsque l'implémentation change.
Séparez le comportement implémenté des exigences proposées et des preuves d'exécution.

| Modification | Références à revoir |
| --- | --- |
| Migration du schéma | Dictionnaire principal et instantané des colonnes physiques |
| Contrôleur ou contenu d'API | Inventaire des routes d'API et cas d'acceptation d'intégration |
| Authentification ou privilèges | Référence de sécurité et gestion des utilisateurs |
| Planificateur ou nettoyage | Architecture, stockage et conservation, procédures de sauvegarde |
| Fichier de verrouillage des dépendances | Inventaire des logiciels et des licences |
| Processus ou évaluation | Exigences fonctionnelles, guide de l'utilisateur et cas de validation |

Pour actualiser les colonnes physiques, relevez en lecture seule les métadonnées d'une base de
référence de même version. Incluez la table, la colonne, la position ordinale, le type SQL, la possibilité de valeur nulle,
la valeur par défaut, la clé de colonne et les attributs supplémentaires de `information_schema.COLUMNS`.
Relevez les correspondances de colonnes des clés étrangères dans `information_schema.KEY_COLUMN_USAGE`.
Consignez la version de l'application, la version du serveur et la date du relevé.
Conservez la distinction entre les types physiques et les définitions métier approuvées par le programme.

Pour actualiser les licences, utilisez le nom, la version et le tableau des licences de chaque paquet PHP dans
`composer.lock`. Utilisez l'emplacement, la version et la licence de chaque paquet non racine dans
`package-lock.json`. Signalez explicitement les métadonnées manquantes et conservez la limitation relative aux ressources embarquées.

Conservez les notes d'évaluation propres à un pays en dehors de `docs/`. Les procédures publiques utilisent des
exemples génériques. Les inventaires locaux, les accords, les preuves de test réalisées et les approbations restent
des documents de déploiement distincts.
