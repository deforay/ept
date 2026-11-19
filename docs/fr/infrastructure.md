# Planification de l'infrastructure

Une page de référence pour les équipes d'achat et d'infrastructure qui préparent un déploiement d'ePT.

---

## Pile technologique {#technology-stack}

| Composant | Technologie |
| --------- | ---------- |
| Langage | PHP 8.4.27 ou ultérieur, ou PHP 8.5.12 ou ultérieur |
| Base de données | MySQL 8+ |
| Serveur web | Apache 2 avec `mod_rewrite` |
| Système d'exploitation | Ubuntu 26.04 LTS |
| Moteur de graphiques | Node.js 22 ou ultérieur, avec des dépendances npm verrouillées |

Docker Compose est aussi pris en charge (voir [setup.md](setup.md)).

---

## Dimensionnement {#sizing}

ePT n'est pas une application d'usage quotidien. Les participants se connectent seulement pendant les périodes d'expédition ouvertes (en général quelques semaines par cycle). La charge processeur vient surtout de la génération des rapports et des envois groupés d'e-mails, et non du trafic interactif.

| Niveau | Participants | vCPU | RAM | Disque |
| ---- | ------------ | ---- | --- | ---- |
| Petit | jusqu'à ~1 000 participants | 2 | 2 Go | 50 Go SSD |
| **Moyen (par défaut)** | jusqu'à ~4 000 participants | 2 | 4 Go | 100 Go SSD |
| Grand | plus de 4 000 participants | 4 | 8 Go ou plus | 200 Go SSD |

L'application et MySQL s'exécutent sur le même hôte.

Ces niveaux décrivent le plan par défaut sur un seul hôte. Ce sont des estimations, et non des garanties
de capacité mesurées. Les tests d'acceptation demandent une charge de travail représentative et des résultats mesurés.

---

## Stockage et sauvegarde {#storage-and-backup}

Tous les fichiers d'ePT (fichiers téléversés, rapports générés, graphiques, journaux) se trouvent sur le **système de fichiers local de l'hôte de l'application**, à côté de MySQL. ePT ne dépend d'aucun stockage d'objets externe.

L'espace de stockage prévu à titre provisoire pour le niveau moyen est de **20 à 40 Go** après 2 à 3 ans.
L'utilisation réelle dépend des fichiers téléversés, des rapports, des journaux et des sauvegardes conservées. Aucun jeu de données
de mesure n'accompagne cette estimation.

**Politique de sauvegarde :**

- Export MySQL quotidien (`db-tools backup` est fourni avec ePT).
- Sauvegarde hebdomadaire des fichiers de `public/uploads/` et `downloads/`.
- Copiez les sauvegardes hors de l'hôte de l'application (deuxième machine virtuelle, volume de sauvegarde attaché ou stockage d'objets dans le cloud : toute destination externe convient).

---

## Réseau {#networking}

- **IP statique** : nécessaire pour un enregistrement DNS stable et pour le certificat SSL Let's Encrypt.
- **Domaine** : un enregistrement A (par exemple `ept.example.org`).
- **TLS** : gratuit avec Let's Encrypt, automatisé par `setup.sh`.
- **SMTP sortant** : nécessaire pour les notifications et la réinitialisation des mots de passe.
- **Ports entrants** : 80 et 443.

---

## Sécurité {#security}

ePT met en œuvre des sessions web authentifiées, des contrôles de droits, le hachage des mots de passe, des contrôles CSRF
sur les requêtes couvertes et l'enregistrement d'un journal d'audit applicatif. Les exemptions CSRF et les contrôles
propres à certains points d'accès de l'API limitent ces contrôles. La couverture de l'audit dépend des appels dans le code.
Elle ne garantit pas que chaque modification est enregistrée. Voir [Contrôles de sécurité](security.md).

Côté infrastructure : placez MySQL sur un sous-réseau privé, limitez l'accès SSH, activez le chiffrement des disques et appliquez chaque mois les correctifs du système d'exploitation et de PHP. ePT lui-même est mis à jour avec `upgrade.sh` (voir [Mettre à jour une installation ePT](updating.md)).

---

## Pour aller plus loin {#further-reading}

- [Guide d'installation](setup.md) : installation
- [Guide d'architecture](ARCHITECTURE.md) : conception du système et sécurité
- [Référence des outils en ligne de commande](cli-tools.md) : scripts d'exploitation
