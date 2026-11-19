# Comment traduire ePT dans votre langue

ePT est livré en anglais. Un laboratoire qui lit l'interface avec les mauvais mots déclare le mauvais résultat. Ce guide explique comment traduire l'interface pour qu'elle reste exacte pour ses utilisateurs.

Les traductions se trouvent dans des catalogues GNU gettext, dans `application/languages/{locale}/{locale}.po`.

## Traduire les pages de documentation {#translate-documentation-pages}

La documentation publique est traduite page par page, en entier. Les pages françaises se trouvent dans `docs/fr/`
avec les mêmes noms de fichiers que leurs sources anglaises. Utilisez le sélecteur de langue de l'en-tête
pour passer de l'anglais au français.

1. Traduisez la page entière, y compris les titres, les tableaux, les libellés des diagrammes et les limites.
2. Appliquez les mêmes règles de rédaction technique dans les deux langues : phrases courtes, instructions directes et termes constants.
3. Conservez les commandes, les routes, les identifiants, les numéros de version, les expressions de licence et les identifiants d'exigences.
4. Faites pointer les liens vers la version française lorsqu'elle existe. Signalez comme anglais les liens vers des pages non traduites.
5. Ajoutez la page à la navigation commune et son libellé français sous `nav_translations` dans `mkdocs.yml`.
6. Vérifiez que le sélecteur de langue de l'en-tête ouvre la page traduite correspondante.
7. Exécutez `mkdocs build --strict` pour contrôler la navigation et les liens.

Pour l'aide intégrée à l'application, placez le corps traduit dans `docs/help/{audience}/fr_FR/`.
Conservez les clés anglaises de l'en-tête (frontmatter) et les chaînes source. Traduisez les valeurs affichées
au moyen du catalogue PO/MO français. Consultez [la référence de rédaction de l'aide](https://github.com/deforay/ept/blob/master/docs/help/README.md).

La section française publique couvre actuellement les douze pages système et d'exploitation
listées dans son index. Les guides plus anciens restent en anglais et sont signalés comme tels.

## Avant de commencer {#before-you-start}

- Installez [Poedit](https://poedit.net) 3.x.
- Demandez au mainteneur de l'installation d'exécuter `php bin/refresh-translations.php`. Cette commande ajoute les nouvelles chaînes anglaises à votre fichier `.po`. Consultez [Outils en ligne de commande](cli-tools.md).
- Lisez la section suivante avant de traduire la première chaîne.

## Traduire dans le contexte des laboratoires d'analyses {#translate-in-the-laboratory-testing-context}

ePT gère les essais d'aptitude (PT) des laboratoires médicaux. Un fournisseur PT envoie des échantillons préparés aux laboratoires inscrits. Chaque laboratoire analyse les échantillons et déclare ses résultats. Le fournisseur note ces résultats et publie un rapport.

Chaque chaîne a un sens dans ce contexte. Traduisez le sens, pas le mot anglais.

| Mot | Sens dans ePT | Ne signifie jamais |
| --- | --- | --- |
| PT | Essais d'aptitude (proficiency testing) | Physiothérapie, kinésithérapie, patient, temps partiel |
| Scheme | Une catégorie d'analyses, comme la sérologie VIH ou la charge virale | Un schéma, un plan ou une escroquerie |
| Shipment | Un lot d'échantillons envoyé aux laboratoires | Une livraison commerciale ou une commande |
| Round | Un cycle d'analyses d'un programme | Une forme ronde ou une tournée de boissons |
| Panel | L'ensemble des échantillons d'une expédition | Une zone de l'écran ou un panneau de configuration |
| Participant | Un laboratoire inscrit | Une personne |
| Outstanding | N'a pas encore répondu | Excellent, ou une destination |
| Result | Ce qu'un laboratoire a déclaré pour un échantillon | Un résultat au sens général |
| Evaluate | Noter les résultats déclarés par un laboratoire | Examiner ou estimer |
| Finalize | Verrouiller un cycle pour qu'il ne puisse plus changer | Terminer au sens général |

## Garder un seul mot pour une seule chose {#keep-one-word-for-one-thing}

Choisissez une traduction pour chaque terme. Utilisez-la dans chaque chaîne, sur chaque écran.

Alterner entre deux mots pour la même chose rend l'interface illisible. Un laboratoire qui voit trois mots différents pour « shipment » ne peut pas savoir s'ils désignent trois choses différentes.

Voici les termes fixés. Respectez-les dans chaque chaîne.

| Anglais | Français (fr_FR) | Vietnamien (vi_VN) |
| --- | --- | --- |
| Scheme | Programme | Chương trình |
| Shipment | Expédition | Vòng ngoại kiểm |
| Round | Cycle | Vòng |
| PT survey | Enquête PT | Khảo sát PT |
| Participant | Participant | Đơn vị tham gia |
| Laboratory, lab | Laboratoire | Phòng xét nghiệm |
| Lab ID | ID du laboratoire | Mã phòng xét nghiệm |
| Data manager | Gestionnaire de données | Người quản lý dữ liệu |
| PTCC | PTCC | PTCC |
| Enroll | Inscrire | Đăng ký |
| Sample | Échantillon | Mẫu |
| Panel (of samples) | Panel | Bộ mẫu |
| Panel (screen area) | Panneau | Khung |
| Dried tube specimen | Échantillon sur tube séché | Mẫu ống khô |
| Test kit | Kit de test | Bộ sinh phẩm |
| Assay | Technique | Phương pháp xét nghiệm |
| Platform (instrument) | Plateforme | Hệ thống máy |
| Viral load | Charge virale | Tải lượng virus |
| Result | Résultat | Kết quả |
| Response | Réponse | Phản hồi |
| Deadline | Date limite | Hạn nộp kết quả |
| Expiry date | Date d'expiration | Ngày hết hạn |
| Outstanding | En attente | Chưa phản hồi |
| Evaluate | Évaluer | Đánh giá |
| Not evaluated | Non évalué | Chưa được đánh giá |
| Pass | Réussi | Đạt |
| Fail | Échec | Không đạt |
| Satisfactory | Satisfaisant | Đạt |
| Unsatisfactory | Insatisfaisant | Không đạt |
| Excluded | Exclu | Loại trừ |
| Finalize | Finaliser | Hoàn tất |
| Report | Rapport | Báo cáo |
| Score | Score | Điểm |
| Certificate | Certificat | Giấy chứng nhận |
| Corrective action | Action corrective | Hành động khắc phục |
| Email | E-mail | Email |
| Email template | Modèle d'e-mail | Mẫu email |
| Login (account name) | Identifiant | Tên đăng nhập |
| Log in | Se connecter | Đăng nhập |

Remarques sur les termes qui se lisent autrement que le mot anglais :

- Un participant est un laboratoire, pas une personne. Le vietnamien utilise « Đơn vị tham gia », la formulation habituelle en évaluation externe de la qualité.
- Le vietnamien « Phòng thí nghiệm » désigne un laboratoire de recherche. Un laboratoire d'analyses se dit « Phòng xét nghiệm ».
- « Deadline » est le dernier jour pour soumettre les résultats. Le vietnamien « Ngày hết hạn » signifie une date d'expiration. Réservez-le à l'expiration des kits et des échantillons.
- « Excluded » signifie écarté de l'évaluation. Le vietnamien « Không bao gồm » signifie « non inclus » et se lit comme un élément manquant.
- « Finalize » verrouille le cycle. Le vietnamien « Hoàn thiện » signifie « parfaire » ou « peaufiner ». Utilisez « Hoàn tất ».
- Le français utilise « Laboratoire » en entier. N'écrivez pas « labo ».
- Conservez les noms de produits, de techniques, de kits de test et d'instruments dans leur forme d'origine. Ce sont des noms de marque.

Vous ajoutez une langue ? Remplissez d'abord ce tableau pour elle. Traduisez ensuite.

## Étapes {#steps}

1. Ouvrez `application/languages/{locale}/{locale}.po` dans Poedit.
2. Triez par état de traduction pour afficher d'abord les entrées non traduites.
3. Traduisez chaque entrée vide. Lorsque le sens n'est pas clair, consultez le chemin du fichier source affiché dans le panneau latéral.
4. Vérifiez chaque entrée que Poedit signale comme à revoir. Consultez la section ci-dessous.
5. Enregistrez le fichier. Poedit écrit le `.po` et compile le `.mo` à côté.
6. Vérifiez votre travail. Consultez la section ci-dessous.

## Vérifier chaque entrée signalée comme à revoir {#check-every-entry-marked-as-needing-work}

Poedit signale une entrée en orange lorsqu'il a copié la traduction d'une chaîne anglaise similaire. Ces copies sont des suppositions. Elles sont assez souvent fausses pour que vous deviez les lire toutes.

Exemples réels trouvés dans les catalogues livrés :

| Anglais | Traduction supposée | Ce qu'elle signifiait | Correction |
| --- | --- | --- | --- |
| Outstanding | destination | destination | En attente |
| Next action | extraction | extraction | Action suivante |
| unable to test | Activer les tests personnalisés | Activer les tests personnalisés | non réalisés |
| Rounds completed in | Các cột được đánh dấu trong | Les colonnes marquées dans | Số vòng đã hoàn thành trong |
| No finalized rounds yet. | Chưa có tệp nhật ký nào | Il n'y a aucun fichier journal | Chưa có vòng nào được hoàn tất |

Lisez l'anglais. Lisez la supposition. Si la supposition ne dit pas la même chose, remplacez-la et retirez le signalement.

## Conserver les variables et le balisage tels quels {#keep-placeholders-and-markup-exactly-as-they-are}

Recopiez ces éléments sans les modifier :

- Les variables de nombre et de texte : `%d`, `%s`, `%1$s`
- Les balises HTML : `<strong>`, `<br>`, `<a href="...">`
- Les adresses e-mail et les URL

`%d` devient un nombre au chargement de la page. Le supprimer retire le nombre. Le modifier casse la page.

Pour réordonner une phrase qui contient deux variables, numérotez-les. `%s uploaded %d files` devient `%2$d fichiers envoyés par %1$s`.

## Écrire pour un lecteur professionnel {#write-for-a-professional-reader}

- Utilisez le registre formel. Le français utilise « vous ». Le vietnamien utilise des formes professionnelles neutres.
- Mettez les articles. Écrivez « Générer les rapports », pas « Générer rapports ».
- Respectez le style de majuscules de la chaîne anglaise.
- Utilisez les accents et les signes diacritiques corrects de votre langue.
- Ne laissez aucun espace au début ni à la fin d'une traduction.

## Vérifier votre travail {#verify-your-work}

1. Vérifiez que Poedit indique zéro entrée non traduite et zéro entrée à revoir.
2. Exécutez `msgfmt --check-format -o /dev/null application/languages/{locale}/{locale}.po`. La commande n'affiche rien lorsque les variables sont intactes. Sinon, elle indique la ligne en cause.
3. Connectez-vous à ePT, passez l'interface dans votre langue et ouvrez le tableau de bord d'administration.
4. Lisez le tableau des cycles. Chaque en-tête de colonne et chaque statut doivent se lire comme le dirait un professionnel de laboratoire.

Si une chaîne s'affiche encore en anglais, le fichier `.mo` n'est pas à jour. Demandez au mainteneur de relancer `php bin/refresh-translations.php --locale={locale}`.

## Origine des chaînes {#where-strings-come-from}

Deux sources alimentent les catalogues. Savoir laquelle est en jeu explique pourquoi une chaîne apparaît.

| Source | Exemple | Remarques |
| --- | --- | --- |
| Code de l'application | En-têtes de colonnes, boutons, messages | Extraites des fichiers `.phtml` et `.php` |
| Tables de référence de la base de données | Noms des résultats de test, types d'échantillons | Extraites des tables `r_*` |

Chaque installation a ses propres données de référence. Le catalogue contient donc les valeurs de toutes les installations. Attendez-vous à des chaînes pour des tests et des types d'échantillons que votre installation n'utilise pas. Traduisez-les quand même. Les laisser vides ne coûte rien, mais les traduire aide le pays suivant.

## Signaler un problème {#reporting-a-problem}

Signalez sur [deforay/ept](https://github.com/deforay/ept) toute chaîne anglaise impossible à traduire correctement, ou absente du catalogue. Indiquez le texte anglais et l'écran où il apparaît.
