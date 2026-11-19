# Partie 1 : Configuration du système et gestion des participants (~10 min)

[← Retour au parcours de formation](README.md) | [Suivant : Partie 2 — Gestion des enquêtes PT et des expéditions →](part2-surveys-and-shipments.md)

**Public :** administrateur

**Objectif :** comprendre l'interface d'administration, et savoir ajouter et inscrire des participants.

---

## 1.1 Connexion et présentation du tableau de bord {#11-logging-in-dashboard-overview}

- Ouvrez la page de connexion de l'administration
- Connectez-vous avec les identifiants d'un administrateur PT ou d'un responsable PT
- Faites une visite rapide du tableau de bord et de la barre de menus en haut de l'écran :
  - **Configurer** : paramètres du système, participants, inscriptions
  - **Gérer** : enquêtes PT et expéditions
  - **Analyse** : évaluation, rapports, finalisation
  - **Rapports** : rapports sur les expéditions et les performances

## 1.2 Ajouter des participants {#12-adding-participants}

**Un seul participant :**
- Allez dans **Configurer → Participants au PT → Ajouter un nouveau participant**
- Remplissez les champs obligatoires (nom, identifiant unique, coordonnées)
- Enregistrez

**Importation groupée (recommandée pour les grands groupes) :**
- Allez dans **Configurer → Participants au PT → Importation groupée des participants**
- Téléchargez le modèle Excel (lien sur le côté droit de la page)
- Remplissez les informations des participants. Les champs à en-tête rouge sont obligatoires.
- Définissez un mot de passe pour chaque participant. Si le mot de passe est vide, le participant reçoit le mot de passe par défaut partagé.
- Téléversez le fichier rempli
- Vérifiez les lignes sur l'écran de vérification. Rien n'est encore enregistré.
- Cliquez sur **Importer … ligne(s)** pour enregistrer les participants

## 1.3 Inscription au programme {#13-scheme-enrollment}

Un participant est inscrit à un programme lorsqu'il figure sur au moins une des **listes d'inscription** de ce programme. Chaque programme a une liste **Par défaut**. Vous pouvez tenir d'autres listes pour un cycle ou une région.

- Allez dans **Configurer → Inscriptions au programme**
- Trouvez le groupe du programme dans l'onglet **Listes**
- Cliquez sur **Modifier** pour la liste **Par défaut**
- Cliquez sur des participants dans **Pas sur cette liste** (à gauche) pour les déplacer vers **Sur cette liste** (à droite)
- Cliquez sur **Enregistrer les modifications**

Pour créer une nouvelle liste, cliquez sur **Nouvelle liste**. Pour en créer une à partir d'une liste existante, ouvrez **Plus** sur cette liste et cliquez sur **Dupliquer la liste**. La copie peut aller dans le même programme ou dans un autre programme.

Pour un grand nombre de participants, utilisez **Importation en masse d'inscriptions**. Vous téléversez un fichier Excel, vous vérifiez les changements prévus, puis vous importez.

> **Notion clé :** l'inscription au programme détermine qui est sélectionné par défaut lorsque vous inscrivez des participants à une expédition de ce programme. Vous pouvez toujours ajouter ou retirer des participants pour chaque expédition.

---

## Notes pour le formateur {#trainer-notes}
- Préparez les données des participants dans le modèle Excel avant la séance
- Insistez sur la différence entre l'inscription au programme (configuration unique) et l'inscription à l'expédition (pour chaque enquête)
- Si le déploiement utilise des gestionnaires de données, expliquez brièvement que chaque participant doit avoir au moins un gestionnaire de données associé (**Configurer → Correspondance participants/identifiants de connexion**)
- Montrez que **Télécharger**, dans le menu **Plus** d'une liste, produit un fichier que **Importation en masse d'inscriptions** accepte en retour

---

[← Retour au parcours de formation](README.md) | [Suivant : Partie 2 — Gestion des enquêtes PT et des expéditions →](part2-surveys-and-shipments.md)
