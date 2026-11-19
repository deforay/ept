# À propos d'ePT

ePT est une application web pour gérer des programmes d'essais d'aptitude. Un fournisseur PT l'utilise pour inscrire des laboratoires et enregistrer les expéditions de panels de test. Le même système recueille les résultats, les évalue par rapport aux valeurs de référence et renvoie un rapport à chaque laboratoire. ePT est un logiciel libre et open source, sous licence AGPL-3.0.

Cette page explique pourquoi ePT existe et ce qu'il cherche à accomplir. Pour l'installer, lisez [Installation](setup.md). Pour apprendre le flux de travail, lisez [Formation](training/README.md).

## Ce que sont les essais d'aptitude {#what-proficiency-testing-is}

Les essais d'aptitude mesurent si un laboratoire produit des résultats corrects. Un organisme externe envoie un panel d'échantillons dont lui seul connaît les vraies valeurs. Le laboratoire teste le panel en même temps que son travail de routine et déclare ce qu'il a trouvé. Le fournisseur PT évalue chaque déclaration par rapport aux valeurs de référence et indique au laboratoire sa performance. Un laboratoire qui obtient un mauvais score prend une action corrective avant le cycle suivant.

ePT donne un nom à ces entités, et le reste de la documentation utilise ces noms.

- Un **participant** est un laboratoire inscrit au programme.
- Un **programme** est un type de test, par exemple la sérologie VIH ou la charge virale.
- Une **expédition** est un cycle de panels envoyés aux participants.
- Un **gestionnaire de données** est une personne qui saisit les résultats des participants associés à son compte.

## Pourquoi le cycle papier échoue {#why-the-paper-cycle-fails}

Les essais d'aptitude sont une mesure de qualité, mais leur gestion est un travail sur les données. Un programme national peut compter des milliers de participants répartis dans tout le pays. Chaque cycle produit un formulaire de résultats par participant. Chaque formulaire doit être lu, saisi, évalué et suivi d'une réponse.

Les programmes qui fonctionnent sur papier décrivent tous la même séquence. Les formulaires arrivent par la poste pendant plusieurs semaines. Le personnel les saisit dans un tableur. Une personne applique les règles d'évaluation à la main. Les rapports partent par publipostage. Selon les programmes, ce processus prend plus de trois mois.

Ce délai va à l'encontre de l'objectif. L'action corrective est la raison même de tester un laboratoire. Un laboratoire qui apprend un échec trois mois plus tard a déjà analysé des échantillons de patients avec le même problème. Un retour lent cache aussi les défauts du programme lui-même. Si un lot de kits de test donne de mauvais résultats dans toute une région, personne ne voit le phénomène avant que le lot soit utilisé depuis longtemps.

Le taux de réponse souffre pour une deuxième raison. Sur papier, le fournisseur ne sait pas qui a répondu tant que les formulaires continuent d'arriver. Relancer les non-répondants se fait à l'aveugle, et le taux de participation baisse.

## Ce qu'ePT cherche à accomplir {#what-ept-sets-out-to-do}

ePT traite le travail sur les données, pas la partie scientifique. Il a trois objectifs.

**Réduire le délai.** Les participants envoient leurs résultats par le web. Le délai postal disparaît donc de la moitié « réponse » du cycle. Un gestionnaire de données saisit les résultats des laboratoires qui ne peuvent pas les envoyer eux-mêmes. L'évaluation s'exécute comme une tâche groupée sur une expédition, et non comme une évaluation manuelle. Les rapports sont générés et distribués depuis le système qui contient les résultats.

**Rendre l'évaluation reproductible.** Les règles d'évaluation se trouvent dans le code et la configuration, et non dans le tableur d'un seul membre du personnel. Deux personnes qui évaluent la même expédition obtiennent les mêmes verdicts. Quand une règle change, elle change à un seul endroit.

**Conserver l'historique.** Chaque réponse, score et rapport reste dans la base de données. Un fournisseur peut voir la performance d'un laboratoire sur plusieurs cycles. Il peut aussi voir la performance d'un district ou d'un kit de test sur plusieurs laboratoires. Cet historique transforme des scores individuels en une vue des besoins de formation du programme.

Autour de ces objectifs, ePT suit le taux de réponse tant qu'une expédition est ouverte. Il envoie des rappels aux participants qui n'ont pas répondu, ferme les expéditions à la date limite et délivre des certificats de participation.

## Pourquoi l'algorithme se trouve dans la configuration {#why-the-algorithm-lives-in-configuration}

Les algorithmes nationaux de dépistage diffèrent. Dans un pays, le diagnostic du VIH demande un seul test de dépistage réactif. Dans un autre, il demande trois tests successifs. Le Vietnam ajoute une règle : un laboratoire de dépistage peut effectuer les trois tests, mais ne peut pas conclure à un résultat positif. La bonne réponse pour un panel dépend donc du pays, et le score aussi.

Un programme pourrait figer son propre algorithme dans le code. Ce choix produit une copie divergente (fork) par pays. Ces copies divisent la maintenance, jusqu'à ce qu'un bogue corrigé dans l'une reste présent dans les autres.

ePT suit l'autre voie. Chaque programme porte sa propre configuration, et une surcouche en base de données ajuste les valeurs par défaut pour chaque installation. Un pays choisit sa variante d'algorithme au lieu de modifier le code d'évaluation. Les présentations des rapports fonctionnent de la même façon, car un programme national a son propre format de rapport et ses propres signataires.

Cette approche a une limite, qu'il faut énoncer clairement. Un algorithme réellement nouveau demande toujours du code. La configuration couvre les variantes qu'ePT modélise déjà. Ajouter un nouveau programme est une tâche de développement, décrite dans [Programmes](SchemeArchitecture.md).

## Conçu pour son lieu d'utilisation {#designing-for-where-it-runs}

ePT est conçu pour des réseaux de laboratoires où la connectivité est inégale. Les pages restent légères, car un participant avec une connexion lente doit quand même envoyer la réponse à un panel. L'application fournit aussi un module API. Une application mobile peut ainsi se connecter et envoyer des résultats par une connexion téléphonique.

La connectivité n'est pas universelle, et la conception l'accepte. Un laboratoire isolé peut transmettre ses résultats à un site mieux connecté. Ce site dispose d'un compte de gestionnaire de données et saisit les résultats pour le compte du laboratoire. Le laboratoire reste un participant, avec son propre historique et son propre rapport. Seule la saisie des données change de lieu.

## Ce qu'ePT n'est pas {#what-ept-is-not}

ePT contient les données des essais d'aptitude et rien d'autre. Ce n'est pas un système d'information de laboratoire, et il ne stocke aucun résultat de patient. Il ne fabrique pas et ne suit pas les panels. La production des panels et la logistique de transport restent donc hors d'ePT. Il ne remplace pas l'évaluation sur site. Les essais d'aptitude répondent à une question sur un laboratoire, et un audit répond à d'autres.

## Autres solutions {#alternatives}

Les tableurs et le publipostage restent la solution courante. Ils sont gratuits et connus. Ils ne tiennent pas face au volume ni dans la durée. Les règles d'évaluation ne survivent qu'aussi longtemps que la personne qui a écrit les formules. Les résultats de l'année précédente se trouvent dans un fichier que personne ne retrouve.

Les plateformes commerciales d'essais d'aptitude résolvent le même problème. Leur prix est généralement fixé par participant. Ce modèle convient mal à un programme national qui compte des milliers de laboratoires et dispose d'un budget fixe. Leur logique d'évaluation repose aussi sur les hypothèses du fournisseur, et non sur un algorithme national.

ePT est la troisième option. Un seul code source ouvert contient les programmes, et les différences entre pays se trouvent dans la configuration et les présentations des rapports. Des présentations de rapports sont fournies pour les programmes du Ghana, de la Jamaïque, du Malawi, du Myanmar, du Vietnam et du Zimbabwe, avec une présentation par défaut pour tous les autres.

## À lire ensuite {#read-next}

- [Installation](setup.md) pour installer un serveur.
- [Formation](training/README.md) pour apprendre le flux de travail de l'administrateur et du participant.
- [Architecture](ARCHITECTURE.md) pour comprendre l'organisation du code.
- [Programmes](SchemeArchitecture.md) pour ajouter ou modifier un programme de test.
