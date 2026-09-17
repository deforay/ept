# Inventaire des logiciels et licences

Cet inventaire consigne les métadonnées de licence du code ePT 7.6.21 au 17 septembre 2026.
Il décrit les paquets verrouillés. Il ne résulte pas d'une inspection des logiciels installés sur un serveur.

## Application et environnement d'exécution

| Composant | Référence enregistrée |
| --- | --- |
| ePT | `composer.json` déclare `AGPL-3.0-only`. Le dépôt contient `LICENSE.md` |
| PHP | `composer.json` exige des versions compatibles avec PHP 8.4 |
| Node.js | `package.json` exige Node.js 22 ou ultérieur pour produire les graphiques |
| Base de données et serveur web | L'inventaire du déploiement fournit les versions réelles et les informations de licence des distributions |

## Paquets PHP

Source : `composer.lock`. Les expressions de licence reproduisent les métadonnées des paquets.

| Paquet | Version verrouillée | Licence déclarée | Périmètre |
| --- | --- | --- | --- |
| amitdugar/archiveutil | 2.0.0 | MIT | Exécution |
| amitdugar/db-tools | 3.3.0 | MIT | Exécution |
| carbonphp/carbon-doctrine-types | 3.2.1 | MIT | Exécution |
| composer/pcre | 3.4.0 | MIT | Exécution |
| crunzphp/crunz | v3.9.4 | MIT | Exécution |
| dflydev/dot-access-data | v3.0.3 | MIT | Exécution |
| doctrine/lexer | 3.0.1 | MIT | Exécution |
| dragonmantank/cron-expression | v3.6.0 | MIT | Exécution |
| egulias/email-validator | 4.0.4 | MIT | Exécution |
| gregwar/captcha | v2.1.1 | MIT | Exécution |
| hackzilla/password-generator | 1.7.0 | MIT | Exécution |
| laravel/serializable-closure | v2.0.16 | MIT | Exécution |
| league/commonmark | 2.10.1 | BSD-3-Clause | Exécution |
| league/config | v1.2.0 | BSD-3-Clause | Exécution |
| maennchen/zipstream-php | 2.4.0 | MIT | Exécution |
| markbaker/complex | 3.0.2 | MIT | Exécution |
| markbaker/matrix | 3.0.1 | MIT | Exécution |
| mitoteam/jpgraph | 10.5.4 | QPL-1.0 | Exécution |
| monolog/monolog | 3.12.0 | MIT | Exécution |
| mpdf/mpdf | v8.3.1 | GPL-2.0-only | Exécution |
| mpdf/psr-http-message-shim | 1.0.0 | MIT | Exécution |
| mpdf/psr-log-aware-trait | v3.0.0 | MIT | Exécution |
| myclabs/deep-copy | 1.14.0 | MIT | Exécution |
| myclabs/php-enum | 1.8.5 | MIT | Exécution |
| nesbot/carbon | 3.14.0 | MIT | Exécution |
| nette/schema | v1.3.6 | BSD-3-Clause, GPL-2.0-only, GPL-3.0-only | Exécution |
| nette/utils | v4.1.5 | BSD-3-Clause, GPL-2.0-only, GPL-3.0-only | Exécution |
| paragonie/random_compat | v9.99.100 | MIT | Exécution |
| phpmyadmin/sql-parser | 6.0.0 | GPL-2.0-or-later | Exécution |
| phpoffice/math | 0.3.0 | MIT | Exécution |
| phpoffice/phpspreadsheet | 5.10.0 | MIT | Exécution |
| phpoffice/phpword | 1.4.0 | LGPL-3.0-only | Exécution |
| psr/clock | 1.0.0 | MIT | Exécution |
| psr/container | 2.0.2 | MIT | Exécution |
| psr/event-dispatcher | 1.0.0 | MIT | Exécution |
| psr/http-message | 1.1 | MIT | Exécution |
| psr/log | 3.0.2 | MIT | Exécution |
| psr/simple-cache | 3.0.0 | MIT | Exécution |
| setasign/fpdf | 1.9.0 | MIT | Exécution |
| setasign/fpdi | v2.6.8 | MIT | Exécution |
| shardj/zf1-future | 1.25.2 | BSD-3-Clause | Exécution |
| symfony/clock | v8.1.0 | MIT | Exécution |
| symfony/config | v8.1.5 | MIT | Exécution |
| symfony/console | v8.1.7 | MIT | Exécution |
| symfony/dependency-injection | v8.1.7 | MIT | Exécution |
| symfony/deprecation-contracts | v3.7.1 | MIT | Exécution |
| symfony/event-dispatcher | v8.1.5 | MIT | Exécution |
| symfony/event-dispatcher-contracts | v3.7.1 | MIT | Exécution |
| symfony/filesystem | v8.1.6 | MIT | Exécution |
| symfony/finder | v8.1.7 | MIT | Exécution |
| symfony/lock | v8.1.6 | MIT | Exécution |
| symfony/mailer | v8.1.7 | MIT | Exécution |
| symfony/mime | v8.1.7 | MIT | Exécution |
| symfony/polyfill-ctype | v1.37.0 | MIT | Exécution |
| symfony/polyfill-deepclone | v1.42.0 | MIT | Exécution |
| symfony/polyfill-intl-grapheme | v1.41.0 | MIT | Exécution |
| symfony/polyfill-intl-idn | v1.42.0 | MIT | Exécution |
| symfony/polyfill-intl-normalizer | v1.42.0 | MIT | Exécution |
| symfony/polyfill-mbstring | v1.38.2 | MIT | Exécution |
| symfony/polyfill-php80 | v1.37.0 | MIT | Exécution |
| symfony/polyfill-php81 | v1.38.1 | MIT | Exécution |
| symfony/polyfill-php82 | v1.38.1 | MIT | Exécution |
| symfony/polyfill-php83 | v1.41.0 | MIT | Exécution |
| symfony/polyfill-php85 | v1.41.0 | MIT | Exécution |
| symfony/polyfill-uuid | v1.37.0 | MIT | Exécution |
| symfony/process | v8.1.7 | MIT | Exécution |
| symfony/service-contracts | v3.7.3 | MIT | Exécution |
| symfony/string | v8.1.7 | MIT | Exécution |
| symfony/translation | v8.1.5 | MIT | Exécution |
| symfony/translation-contracts | v3.7.1 | MIT | Exécution |
| symfony/uid | v8.1.5 | MIT | Exécution |
| symfony/var-exporter | v8.1.6 | MIT | Exécution |
| symfony/yaml | v8.1.6 | MIT | Exécution |
| tecnickcom/tcpdf | 6.11.4 | LGPL-3.0-or-later | Exécution |
| clue/ndjson-react | v1.3.0 | MIT | Développement |
| composer/semver | 3.4.4 | MIT | Développement |
| composer/xdebug-handler | 3.0.5 | MIT | Développement |
| ergebnis/agent-detector | 1.2.0 | MIT | Développement |
| evenement/evenement | v3.0.2 | MIT | Développement |
| fidry/cpu-core-counter | 1.3.0 | MIT | Développement |
| friendsofphp/php-cs-fixer | v3.95.25 | MIT | Développement |
| league/iso3166 | 4.5.0 | MIT | Développement |
| phpstan/phpstan | 2.2.14 | MIT | Développement |
| react/cache | v1.2.0 | MIT | Développement |
| react/child-process | v0.6.7 | MIT | Développement |
| react/dns | v1.14.0 | MIT | Développement |
| react/event-loop | v1.6.0 | MIT | Développement |
| react/promise | v3.3.0 | MIT | Développement |
| react/socket | v1.17.0 | MIT | Développement |
| react/stream | v1.4.0 | MIT | Développement |
| sebastian/diff | 9.0.1 | BSD-3-Clause | Développement |
| symfony/options-resolver | v8.1.0 | MIT | Développement |
| symfony/polyfill-php84 | v1.38.1 | MIT | Développement |
| symfony/stopwatch | v8.1.0 | MIT | Développement |
| symfony/var-dumper | v5.4.48 | MIT | Développement |

## Paquets Node

Source : `package-lock.json`. Les emplacements imbriqués restent distincts lorsque le fichier contient plusieurs installations.

| Emplacement du paquet | Version verrouillée | Licence déclarée |
| --- | --- | --- |
| `node_modules/@kurkle/color` | 0.3.4 | MIT |
| `node_modules/@sgratzl/boxplots` | 2.0.0 | MIT |
| `node_modules/@sgratzl/chartjs-chart-boxplot` | 4.4.5 | MIT |
| `node_modules/agent-base` | 7.1.4 | MIT |
| `node_modules/chart.js` | 4.5.1 | MIT |
| `node_modules/debug` | 4.4.3 | MIT |
| `node_modules/detect-libc` | 2.1.2 | Apache-2.0 |
| `node_modules/follow-redirects` | 1.16.0 | MIT |
| `node_modules/https-proxy-agent` | 7.0.6 | MIT |
| `node_modules/ms` | 2.1.3 | MIT |
| `node_modules/parenthesis` | 3.1.8 | MIT |
| `node_modules/skia-canvas` | 3.0.8 | MIT |
| `node_modules/string-split-by` | 1.0.0 | MIT |

## Limites de l'inventaire

Les fichiers JavaScript, CSS, polices, images et bibliothèques anciennes intégrés manuellement nécessitent un inventaire distinct.
Les tableaux ne démontrent ni la compatibilité des licences ni le respect des obligations de distribution.
Les métadonnées des paquets ne fournissent ni contrats d'assistance ni dates de renouvellement.

L'hébergement, les domaines, les certificats, les services commerciaux et les accords fournisseurs relèvent de l'inventaire local.
Aucune obligation de renouvellement n'est déduite du nom d'un paquet.

Le dossier de remise consigne le commit déployé et les copies des manifestes, fichiers de verrouillage et notices de licence correspondants.
Consultez la [remise du déploiement](deployment-handover.md).
