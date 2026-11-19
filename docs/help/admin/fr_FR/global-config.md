---
title: Global Settings
summary: System-wide settings that apply across the whole ePT app
tags: [config, global, settings, smtp, theme]
---

# Paramètres globaux

Cette page contient les **paramètres qui s'appliquent à tout le
système** de votre installation ePT : tout ce qui ne dépend pas d'un
seul programme (DTS, VL, TB) ou d'une seule page. Les modifications
faites ici touchent toute l'application.

La page est longue. Le menu latéral vous aide à trouver un
paramètre :

- **Rechercher dans les paramètres** : saisissez un mot pour afficher
  uniquement les champs dont le libellé correspond.
- **Noms des sections** : cliquez sur un nom pour aller à la section.
- **Développer tout** et **Tout réduire** ouvrent ou ferment toutes
  les sections.

La page comporte quatre sections.

## Réglages généraux

L'identité visuelle, le comportement et les valeurs par défaut de
toute l'application.

- **Domaine** : l'URL de base de l'application. Elle est remplie
  automatiquement, mais vous pouvez la modifier en cas de proxy ou de
  CDN.
- **Langue** : la langue de l'interface (anglais, français,
  vietnamien).
- **E-mail de l'administrateur** : le contact administrateur
  principal.
- **E-mail d'assistance** : facultatif. Remplit le champ de fusion
  **E-mail d'assistance** dans les e-mails aux participants.
- **Nom du programme PT** / **Nom abrégé du programme PT** : le nom
  complet et le nom abrégé de votre programme. Les deux apparaissent
  dans les en-têtes, les e-mails et les rapports.
- **Instance de formation** : réglez sur *Oui* pour transformer cette
  copie de l'application en environnement de formation ou de test.
  Avec le **Filigrane du rapport**, chaque PDF porte un filigrane
  *TRAINING / DRAFT*. Les rapports de test ne peuvent ainsi pas être
  confondus avec de vrais rapports.
- **Programmes actifs** : cochez-en au moins un. Les programmes non
  cochés ici n'apparaissent pas dans les menus.
- **Politique relative à la date limite de réponse** : la façon dont
  ePT décide qu'une date limite est passée. **Fuseau horaire de
  l'application (par défaut)** ferme à l'heure locale exacte.
  **N'importe où sur Terre (UTC-12)** garde l'expédition ouverte
  jusqu'à ce que la date limite soit passée dans tous les fuseaux
  horaires.
- **Délai de grâce pour soumission tardive (minutes)** : le nombre de
  minutes après la date limite pendant lesquelles un participant qui
  a ouvert le formulaire de réponse avant la date limite peut encore
  soumettre. La réponse est mise en attente pour votre vérification
  comme soumission tardive. Saisissez *0* pour désactiver ce délai.
- **Activer les tests personnalisés** : permet aux responsables PT de
  configurer des types de tests non standard.
- **Réévaluer avant de finaliser** : si *Oui*, ePT réévalue
  automatiquement l'expédition chaque fois qu'il génère ou finalise
  les rapports. Les scores suivent alors les paramètres de notation
  actuels. Ils peuvent donc différer de la dernière évaluation.
- **Activer le CAPA** : active le module d'actions correctives et
  préventives dans toute l'application.
- **Uniquement les kits de test approuvés pour les participants** : avec
  *Oui*, les participants ne peuvent choisir que les kits de test
  marqués **Approuvé**. Un kit déjà enregistré sur une réponse y reste.
  Valeur par défaut : *Non*.
- **Notifications par e-mail à l'administrateur** : active ou
  désactive globalement les e-mails envoyés aux administrateurs.
- **Nom de l'institut** / **Adresse de l'institut** /
  **Informations supplémentaires sur l'institut** : affichés sur les
  rapports et les documents officiels.
- **Couleur du thème** / **Instance** : l'apparence visuelle et le
  type d'environnement.
- **Alertes de fin de tâche** + **Destinataires de l'alerte de fin de
  tâche** : quand des tâches planifiées (évaluation, génération des
  rapports, finalisation) se terminent, ePT avertit cette liste
  d'adresses e-mail séparées par des virgules.
- **Générer automatiquement le code d'enquête PT** : si *Oui*, le
  code d'enquête PT est rempli automatiquement dans le formulaire
  *Ajouter une enquête PT*, à partir de la date.
- **Texte de pied de page** / **Format de date** : la ligne de pied
  de page globale et le format de date utilisé partout (par exemple,
  `d-M-Y`, `MM/DD/YYYY`).
- **E-mail des certificats** : les adresses qui reçoivent un avis
  quand un lot de certificats est généré et prêt pour approbation.
  Séparez plusieurs adresses par des virgules. Aucun e-mail n'est
  envoyé aux participants.
- **Nom du signataire des certificats** / **Fonction du signataire
  des certificats** : imprimés dans les champs `signatory_name` et
  `signatory_title` des modèles de certificat.

## Paramètres des participants

La façon dont les participants se connectent et ce qu'ils peuvent
faire.

- **Préfixe d'identifiant de connexion** : texte ajouté au début des
  identifiants des nouveaux participants (par exemple, `PT001`,
  `PT002`).
- **Longueur du mot de passe** : la longueur minimale des mots de
  passe des participants.
- **Autoriser la modification du nom** : *Oui* permet aux
  participants de changer leur nom depuis leur profil.
- **Activer les commentaires** : *Oui* affiche le module de
  commentaires et de corrections après la finalisation d'une
  expédition.
- **Connexion directe** : *Oui* donne à chaque participant son propre
  identifiant. L'association d'un gestionnaire de données devient
  facultative, et les formulaires de participant et l'importation en
  masse demandent un mot de passe. *Non* signifie que les
  participants se connectent par le compte d'un gestionnaire de
  données associé.
- **Interdiction des tentatives de connexion** : si *Oui*, trois
  champs supplémentaires apparaissent :
  - **Durée de l'interdiction temporaire (minutes)** : durée du
    blocage du compte après le premier seuil.
  - **Tentatives avant l'interdiction temporaire** : nombre d'échecs
    de connexion qui déclenche le blocage temporaire (par exemple, 3).
  - **Tentatives avant l'interdiction permanente** : nombre d'échecs
    de connexion qui désactive définitivement le compte (par exemple,
    10).

## Champs personnalisés de l'expédition

Des champs supplémentaires ajoutés à chaque formulaire de réponse.

- **Ajouter un ou plusieurs champs personnalisés ?** : *OUI* ajoute
  les champs ci-dessous.
- **Champ personnalisé 1** et **Champ personnalisé 2** : les libellés
  des champs. Si vous choisissez *OUI*, remplissez au moins le Champ
  personnalisé 1.

## Paramètres de messagerie

Les identifiants SMTP et les valeurs par défaut des e-mails sortants.
Ils sont stockés dans `application.ini` sur le serveur, et non dans la
base de données. Une copie de la base de données ne peut donc pas
envoyer d'e-mails à elle seule.

- **Hôte SMTP / Port SMTP** : nom d'hôte et port du serveur (587 pour
  TLS, 465 pour SSL).
- **Nom de l'expéditeur / E-mail de l'expéditeur** : ce que les
  participants voient dans leur boîte de réception.
- **CC / BCC** : destinataires en copie par défaut de chaque e-mail
  du système.
- **Chiffrement** : TLS, SSL, STARTTLS ou Aucun.
- **Nom d'utilisateur / Mot de passe** : identifiants SMTP. Le mot de
  passe n'est jamais affiché. Laissez-le vide pour conserver le mot
  de passe actuel.
- **Type d'authentification** : Login, Plain ou CRAM-MD5.

### Boîte de réception des rejets

La boîte IMAP qui reçoit les rapports d'échec de livraison. Toutes
les 30 minutes, ePT y lit les nouveaux rapports et marque les
adresses qui n'existent plus. Les e-mails suivants les ignorent.
Laissez **Hôte IMAP** vide pour désactiver cette fonction.

- **Hôte IMAP / Port IMAP** : pour Gmail, `imap.gmail.com` et 993.
- **Chiffrement** : SSL pour le port 993.
- **Dossier ou libellé Gmail** : par exemple `ept-bounces`. Si le
  champ est vide, ePT lit INBOX.
- **Nom d'utilisateur / Mot de passe** : laissez les deux vides pour
  lire la boîte du compte SMTP avec l'identifiant SMTP.

Avant d'enregistrer, ePT ouvre la boîte et le dossier. En cas
d'échec, un message rouge en indique la raison et rien n'est
enregistré.

Une fois la boîte configurée, un encadré au-dessus des champs indique
la date de la dernière vérification, le nombre d'adresses marquées
lors de cette vérification et le nombre total d'adresses marquées
comme rejetées. Il devient rouge si la dernière vérification a
échoué ou si aucune vérification n'a eu lieu depuis plus de deux
heures.

## Enregistrer

Cliquez sur **Mettre à jour** pour enregistrer tout le formulaire.
Les champs obligatoires sont marqués d'un `*`. Si un champ
obligatoire est vide, le formulaire n'est pas enregistré et le champ
manquant est mis en évidence.

## Conseils

- Les **Réglages de la page d’accueil** se configurent dans
  *Configurer → Réglages de la page d’accueil* (ils se trouvaient
  auparavant sur cette page).
- Activer l'**Instance de formation** sur une vraie installation de
  production ajoute un filigrane à tous les rapports. Utilisez-la
  uniquement sur des environnements de test ou de formation.
- Après avoir modifié les **Programmes actifs**, rechargez les pages
  d'administration concernées (paramètres DTS, VL, TB) pour voir les
  éléments de menu apparaître ou disparaître.
- Vous avez changé l'hôte SMTP, le nom d'utilisateur ou le mot de
  passe ? La page se connecte au serveur de messagerie avant
  d'enregistrer. Si le serveur refuse, un message rouge en explique
  la raison et rien n'est enregistré. Les anciens paramètres
  continuent donc de fonctionner.
