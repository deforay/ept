# Software and license inventory

[Français](fr/software-licenses.md)

This inventory records license metadata in the ePT 7.6.21 checkout on 17 September 2026.
It describes locked packages, not an inspection of installed software on a server.

## Application and runtime

| Component | Recorded basis |
| --- | --- |
| ePT | `composer.json` declares `AGPL-3.0-only`. The repository contains `LICENSE.md` |
| PHP | `composer.json` requires PHP 8.4-compatible versions |
| Node.js | `package.json` requires Node.js 22 or later for chart rendering |
| Database and web server | Deployment inventory supplies actual versions and distribution license records |

## PHP packages

Source: `composer.lock`. License expressions are reproduced from package metadata.

| Package | Locked version | Declared license | Scope |
| --- | --- | --- | --- |
| amitdugar/archiveutil | 2.0.0 | MIT | Runtime |
| amitdugar/db-tools | 3.3.0 | MIT | Runtime |
| carbonphp/carbon-doctrine-types | 3.2.1 | MIT | Runtime |
| composer/pcre | 3.4.0 | MIT | Runtime |
| crunzphp/crunz | v3.9.4 | MIT | Runtime |
| dflydev/dot-access-data | v3.0.3 | MIT | Runtime |
| doctrine/lexer | 3.0.1 | MIT | Runtime |
| dragonmantank/cron-expression | v3.6.0 | MIT | Runtime |
| egulias/email-validator | 4.0.4 | MIT | Runtime |
| gregwar/captcha | v2.1.1 | MIT | Runtime |
| hackzilla/password-generator | 1.7.0 | MIT | Runtime |
| laravel/serializable-closure | v2.0.16 | MIT | Runtime |
| league/commonmark | 2.10.1 | BSD-3-Clause | Runtime |
| league/config | v1.2.0 | BSD-3-Clause | Runtime |
| maennchen/zipstream-php | 2.4.0 | MIT | Runtime |
| markbaker/complex | 3.0.2 | MIT | Runtime |
| markbaker/matrix | 3.0.1 | MIT | Runtime |
| mitoteam/jpgraph | 10.5.4 | QPL-1.0 | Runtime |
| monolog/monolog | 3.12.0 | MIT | Runtime |
| mpdf/mpdf | v8.3.1 | GPL-2.0-only | Runtime |
| mpdf/psr-http-message-shim | 1.0.0 | MIT | Runtime |
| mpdf/psr-log-aware-trait | v3.0.0 | MIT | Runtime |
| myclabs/deep-copy | 1.14.0 | MIT | Runtime |
| myclabs/php-enum | 1.8.5 | MIT | Runtime |
| nesbot/carbon | 3.14.0 | MIT | Runtime |
| nette/schema | v1.3.6 | BSD-3-Clause, GPL-2.0-only, GPL-3.0-only | Runtime |
| nette/utils | v4.1.5 | BSD-3-Clause, GPL-2.0-only, GPL-3.0-only | Runtime |
| paragonie/random_compat | v9.99.100 | MIT | Runtime |
| phpmyadmin/sql-parser | 6.0.0 | GPL-2.0-or-later | Runtime |
| phpoffice/math | 0.3.0 | MIT | Runtime |
| phpoffice/phpspreadsheet | 5.10.0 | MIT | Runtime |
| phpoffice/phpword | 1.4.0 | LGPL-3.0-only | Runtime |
| psr/clock | 1.0.0 | MIT | Runtime |
| psr/container | 2.0.2 | MIT | Runtime |
| psr/event-dispatcher | 1.0.0 | MIT | Runtime |
| psr/http-message | 1.1 | MIT | Runtime |
| psr/log | 3.0.2 | MIT | Runtime |
| psr/simple-cache | 3.0.0 | MIT | Runtime |
| setasign/fpdf | 1.9.0 | MIT | Runtime |
| setasign/fpdi | v2.6.8 | MIT | Runtime |
| shardj/zf1-future | 1.25.2 | BSD-3-Clause | Runtime |
| symfony/clock | v8.1.0 | MIT | Runtime |
| symfony/config | v8.1.5 | MIT | Runtime |
| symfony/console | v8.1.7 | MIT | Runtime |
| symfony/dependency-injection | v8.1.7 | MIT | Runtime |
| symfony/deprecation-contracts | v3.7.1 | MIT | Runtime |
| symfony/event-dispatcher | v8.1.5 | MIT | Runtime |
| symfony/event-dispatcher-contracts | v3.7.1 | MIT | Runtime |
| symfony/filesystem | v8.1.6 | MIT | Runtime |
| symfony/finder | v8.1.7 | MIT | Runtime |
| symfony/lock | v8.1.6 | MIT | Runtime |
| symfony/mailer | v8.1.7 | MIT | Runtime |
| symfony/mime | v8.1.7 | MIT | Runtime |
| symfony/polyfill-ctype | v1.37.0 | MIT | Runtime |
| symfony/polyfill-deepclone | v1.42.0 | MIT | Runtime |
| symfony/polyfill-intl-grapheme | v1.41.0 | MIT | Runtime |
| symfony/polyfill-intl-idn | v1.42.0 | MIT | Runtime |
| symfony/polyfill-intl-normalizer | v1.42.0 | MIT | Runtime |
| symfony/polyfill-mbstring | v1.38.2 | MIT | Runtime |
| symfony/polyfill-php80 | v1.37.0 | MIT | Runtime |
| symfony/polyfill-php81 | v1.38.1 | MIT | Runtime |
| symfony/polyfill-php82 | v1.38.1 | MIT | Runtime |
| symfony/polyfill-php83 | v1.41.0 | MIT | Runtime |
| symfony/polyfill-php85 | v1.41.0 | MIT | Runtime |
| symfony/polyfill-uuid | v1.37.0 | MIT | Runtime |
| symfony/process | v8.1.7 | MIT | Runtime |
| symfony/service-contracts | v3.7.3 | MIT | Runtime |
| symfony/string | v8.1.7 | MIT | Runtime |
| symfony/translation | v8.1.5 | MIT | Runtime |
| symfony/translation-contracts | v3.7.1 | MIT | Runtime |
| symfony/uid | v8.1.5 | MIT | Runtime |
| symfony/var-exporter | v8.1.6 | MIT | Runtime |
| symfony/yaml | v8.1.6 | MIT | Runtime |
| tecnickcom/tcpdf | 6.11.4 | LGPL-3.0-or-later | Runtime |
| clue/ndjson-react | v1.3.0 | MIT | Development |
| composer/semver | 3.4.4 | MIT | Development |
| composer/xdebug-handler | 3.0.5 | MIT | Development |
| ergebnis/agent-detector | 1.2.0 | MIT | Development |
| evenement/evenement | v3.0.2 | MIT | Development |
| fidry/cpu-core-counter | 1.3.0 | MIT | Development |
| friendsofphp/php-cs-fixer | v3.95.25 | MIT | Development |
| league/iso3166 | 4.5.0 | MIT | Development |
| phpstan/phpstan | 2.2.14 | MIT | Development |
| react/cache | v1.2.0 | MIT | Development |
| react/child-process | v0.6.7 | MIT | Development |
| react/dns | v1.14.0 | MIT | Development |
| react/event-loop | v1.6.0 | MIT | Development |
| react/promise | v3.3.0 | MIT | Development |
| react/socket | v1.17.0 | MIT | Development |
| react/stream | v1.4.0 | MIT | Development |
| sebastian/diff | 9.0.1 | BSD-3-Clause | Development |
| symfony/options-resolver | v8.1.0 | MIT | Development |
| symfony/polyfill-php84 | v1.38.1 | MIT | Development |
| symfony/stopwatch | v8.1.0 | MIT | Development |
| symfony/var-dumper | v5.4.48 | MIT | Development |

## Node packages

Source: `package-lock.json`. Nested locations remain separate when the lockfile contains multiple installations.

| Package location | Locked version | Declared license |
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

## Inventory boundaries

Manually bundled JavaScript, CSS, fonts, images and legacy libraries require a separate asset inventory.
The tables do not establish license compatibility or fulfillment of distribution obligations.
Package metadata does not provide support contracts or renewal dates.

Hosting, domain registration, certificates, commercial services and vendor agreements belong
in the local deployment inventory. No renewal obligation is inferred from a package name.

A release handover records the deployed commit and copies of the corresponding manifests,
lockfiles and license notices. See [deployment handover](deployment-handover.md).
