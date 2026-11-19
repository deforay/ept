# Maintenir l'envoi des e-mails

Ce guide configure les e-mails sortants, active le traitement des rejets et corrige les e-mails qui ne partent plus.

Tous les paramètres de messagerie se trouvent dans `application/configs/application.ini` sur le serveur. La base de données n'en contient aucun. Une copie de la base de production restaurée sur une autre machine ne peut donc pas envoyer d'e-mails aux participants.

## Avant de commencer {#before-you-start}

| Exigence | Détail |
| --- | --- |
| Compte administrateur | Accès à **Configurer → Paramètres globaux d'ePT** |
| Accès au serveur | Un shell sur le serveur, en tant que `root` ou avec `sudo`, pour les sections sur les rejets et la file |
| Compte de messagerie | Un compte SMTP. Pour Gmail ou Google Workspace, un [mot de passe d'application](https://support.google.com/accounts/answer/185833) et IMAP activé |
| Version d'ePT | 7.6.23 ou ultérieure |

## Définir ou modifier l'identifiant SMTP {#set-or-change-the-smtp-login}

1. Ouvrez **Configurer → Paramètres globaux d'ePT**.
2. Ouvrez la section **Paramètres de messagerie**.
3. Renseignez **Hôte SMTP**, **Port SMTP**, **Chiffrement**, **Nom d'utilisateur** et **Type d'authentification**.
4. Saisissez le nouveau mot de passe dans **Mot de passe**. Pour conserver le mot de passe actuel, laissez le champ vide.
5. Renseignez **Nom de l'expéditeur** et **E-mail de l'expéditeur**. Si **E-mail de l'expéditeur** est vide, les e-mails partent avec le nom d'utilisateur SMTP.
6. Cliquez sur **Mettre à jour**.

Avant l'enregistrement, la page se connecte au serveur de messagerie avec les nouvelles informations. Si le serveur refuse la connexion, un message rouge affiche la réponse du serveur et rien n'est enregistré. Les paramètres précédents continuent de fonctionner.

Si la page indique que `application.ini` n'est pas accessible en écriture, exécutez `sudo ept update`. La mise à jour rend le fichier accessible en écriture au serveur web.

## Activer le traitement des rejets {#turn-on-bounce-processing}

Le traitement des rejets lit les rapports d'échec de remise dans une boîte IMAP. Il marque chaque adresse qui n'existe plus. Les e-mails d'expédition, les boutons de copie des adresses et **Envoyer un e-mail aux participants** l'ignorent ensuite.

Les rejets des e-mails envoyés par ePT reviennent au compte SMTP. Les rejets des e-mails que vous envoyez depuis votre propre Gmail, après avoir copié des adresses depuis ePT, reviennent dans votre propre boîte.

1. Dans le Gmail du compte SMTP, créez un filtre. Définissez **From** sur `mailer-daemon@googlemail.com` et choisissez **Apply the label** `ept-bounces`.
2. Si vous envoyez aussi depuis votre propre Gmail, créez-y également un filtre. Définissez **From** sur `mailer-daemon@googlemail.com` et choisissez **Forward it to** le compte SMTP.
3. Ouvrez **Configurer → Paramètres globaux d'ePT → Paramètres de messagerie** et renseignez la partie **Boîte de réception des rejets** :

    | Champ | Valeur |
    | --- | --- |
    | **Hôte IMAP** | `imap.gmail.com` |
    | **Port IMAP** | `993` |
    | **Chiffrement** | SSL |
    | **Dossier ou libellé Gmail** | `ept-bounces` |
    | **Nom d'utilisateur** et **Mot de passe** | Laissez-les vides pour utiliser l'identifiant SMTP. Pour lire une autre boîte, renseignez-les |

    Cliquez sur **Mettre à jour**. La page ouvre la boîte et le dossier avant l'enregistrement. En cas d'échec, un message rouge en indique la raison et rien n'est enregistré.

4. Prévisualisez ce que le traitement marquerait :

    ```bash
    cd /var/www/ept
    sudo -u www-data php bin/process-bounces.php --dry-run --reset-state
    ```

    Chaque ligne commençant par `HARD` nomme une adresse que le traitement marquerait comme rejetée. `--reset-state` fait lire tout le dossier lors de cette première exécution.

5. Exécutez-le une fois réellement, ou attendez jusqu'à 30 minutes le planificateur. L'enregistrement d'une nouvelle boîte ou d'un nouveau dossier fait lire tout le dossier lors de l'exécution suivante.

    ```bash
    sudo -u www-data php bin/process-bounces.php --reset-state
    ```

Ensuite, le planificateur exécute le traitement toutes les 30 minutes. Il lit uniquement les nouveaux messages. Il ne les supprime pas et ne les déplace pas.

Une boîte pleine, un blocage anti-spam ou une limite de débit ne marque pas l'adresse. Seul un rapport indiquant que la boîte n'existe pas la marque.

### Vérifier que le traitement des rejets fonctionne {#check-that-bounce-processing-is-working}

Ouvrez **Configurer → Paramètres globaux d'ePT → Paramètres de messagerie**. Sous **Boîte de réception des rejets**, un encadré affiche la dernière vérification :

| Encadré | Signification | Action |
| --- | --- | --- |
| Vert, « Dernière vérification le … » | La dernière vérification a réussi. Il indique le nombre de nouveaux messages lus et le nombre d'adresses marquées | Rien. S'il indique que des messages plus anciens sont toujours en attente, le traitement parcourt la boîte |
| Jaune, « Pas encore vérifié » | Aucune vérification n'a eu lieu depuis l'enregistrement de la boîte | Attendez 30 minutes et rechargez la page |
| Rouge, « La dernière vérification a échoué » | La boîte n'a pas pu être lue. L'encadré affiche la réponse du serveur | Corrigez l'identifiant ou le dossier et cliquez sur **Mettre à jour** |
| Rouge, « Aucune vérification depuis plus de deux heures » | Le planificateur ePT ne fonctionne pas | Demandez à l'administrateur du serveur de vérifier la tâche cron qui exécute `crunz schedule:run` |

L'encadré indique aussi le nombre d'adresses marquées comme rejetées jusqu'ici. Pour savoir lesquelles, utilisez **E-mails inutilisables** dans **Configurer → Participants au PT**.

## Trouver les participants et les gestionnaires de données qui ne peuvent pas recevoir d'e-mails {#find-participants-and-data-managers-who-cannot-receive-email}

1. Ouvrez **Configurer → Participants au PT**, ou **Configurer → Gestionnaire de données (connexion des participants)**.
2. Cliquez sur **E-mails inutilisables**.
3. Ouvrez le classeur téléchargé. Il contient une feuille **Participants** et une feuille **Data Managers**.
4. Filtrez **Reachable By Email** sur `No`. Ces comptes ne reçoivent aucun e-mail.

| Problème affiché | Signification | Correction |
| --- | --- | --- |
| No email | L'adresse est vide | Ajoutez une adresse |
| Login-only address (made up at import) | L'import en masse a généré l'adresse pour que le compte puisse se connecter. Aucune boîte n'existe | Ajoutez une adresse réelle, ou laissez-la si le compte sert uniquement à se connecter |
| Bounced | Le serveur de messagerie a signalé que la boîte n'existe pas. **Bounce Reason** affiche la réponse | Obtenez une nouvelle adresse auprès du laboratoire |
| Domain does not accept mail | Le domaine n'a pas de serveur de messagerie | Corrigez le domaine |
| Not a valid email address | L'adresse est mal formée | Corrigez l'adresse |

Pour corriger un compte, modifiez son adresse e-mail dans ePT. Une adresse modifiée perd son ancien problème et elle est vérifiée de nouveau lors de la validation suivante.

## Corriger les e-mails qui partent parfois et échouent parfois {#fix-mail-that-sends-sometimes-and-fails-sometimes}

1. Ouvrez **Configurer → E-mails non envoyés**.
2. Lisez le **Motif de l'échec** des lignes récentes. Utilisez ce tableau pour trouver la cause :

    | Texte dans **Motif de l'échec** ou dans le journal | Cause | Correction |
    | --- | --- | --- |
    | `535` ou `Username and Password not accepted` | Le serveur de messagerie refuse l'identifiant SMTP | Créez un nouveau mot de passe d'application. Saisissez-le dans **Paramètres de messagerie** |
    | `Invalid emails` pour un destinataire | L'adresse a été ignorée car elle ne peut pas être remise | Voir [Trouver les participants et les gestionnaires de données qui ne peuvent pas recevoir d'e-mails](#find-participants-and-data-managers-who-cannot-receive-email) |
    | `No valid To recipients` | Tous les destinataires ont été ignorés | Corrigez l'adresse du destinataire |
    | `mail settings are still in global_config.mail` dans le journal | La mise à jour n'a pas fini de déplacer les anciens paramètres | Exécutez `ept run-once` |

3. Recherchez les erreurs de messagerie dans le journal du jour :

    ```bash
    grep -iE "smtp|mail" /var/www/ept/logs/$(date +%F)-logfile.log | tail -20
    ```

4. Trouvez dans MySQL les identifiants de file des messages en échec :

    ```sql
    SELECT temp_id, to_email, subject, updated_at
      FROM temp_mail WHERE status = 'failed' ORDER BY temp_id DESC LIMIT 20;
    ```

5. Après avoir corrigé la cause, remettez ces messages dans la file :

    ```sql
    UPDATE temp_mail SET status = 'pending', failure_reason = NULL
     WHERE status = 'failed' AND temp_id IN (10746);
    ```

    Remplacez `10746` par les valeurs `temp_id` de l'étape 4.

## Vérifier que la messagerie fonctionne {#check-that-mail-works}

1. Envoyez-vous un e-mail depuis **Envoyer un e-mail aux participants**, ou déclenchez une réinitialisation du mot de passe pour un compte de test.
2. Attendez jusqu'à une minute que la file s'exécute.
3. Vérifiez que le message ne figure pas dans **Configurer → E-mails non envoyés**.
4. Vérifiez que le message est arrivé dans la boîte de test.
