# Comment gérer les accès utilisateurs

Créez et examinez les comptes ePT avec les droits nécessaires aux tâches attribuées.

## Avant de commencer

Obtenez le nom du titulaire, ses responsabilités approuvées et les programmes ou participants autorisés.
Utilisez un compte administrateur disposant des privilèges requis de configuration ou de gestion des participants.
Gardez les fiches participants distinctes des comptes qui saisissent leurs résultats.

## Ajouter un responsable PT

1. Ouvrez **Configurer → Responsables PT**.
2. Cliquez sur **Ajouter un nouveau gestionnaire PT**.
3. Saisissez le nom, le courriel principal, les coordonnées et le mot de passe initial.
4. Sélectionnez les programmes actifs que la personne peut gérer.
5. Sélectionnez uniquement les privilèges approuvés.
6. Définissez le statut prévu du compte, puis soumettez le formulaire.
7. Organisez la transmission sécurisée des identifiants initiaux au titulaire.
8. Confirmez que le titulaire peut se connecter et effectuer le changement de mot de passe requis.

Le formulaire administrateur utilise des attributions de programmes et des privilèges individuels.
Ne déduisez pas les accès effectifs d'un libellé de rôle générique.
Consultez le [référentiel des privilèges, en anglais](../AdminModuleGuide.md#privilege-system).

## Attribuer les accès au portail participant

1. Ouvrez **Configurer → Gestionnaire de données (connexion des participants)**.
2. Trouvez le compte, ou cliquez sur **Ajouter un nouveau gestionnaire de données (identifiant du participant)** pour le créer.
3. Ouvrez **Configurer → Correspondance participants/identifiants de connexion**.
4. Choisissez le gestionnaire de données.
5. Cliquez sur chaque participant dont le compte saisit les résultats, pour le faire passer de **Non associés** à **Associés**.
6. Cliquez sur **Enregistrer les modifications**.
7. Confirmez l'inscription du participant au programme et à l'expédition concernés.
8. Connectez-vous avec le compte du gestionnaire, ou cliquez sur **Voir comme** dans la liste des gestionnaires. **Voir comme** n'apparaît que pour les administrateurs ayant le privilège de consultation en tant que participant. Vérifiez que le compte voit l'expédition et le participant prévus.
9. Vérifiez qu'un participant non associé n'apparaît pas.

Consultez la [formation à la gestion des participants, en anglais](../training/part1-setup-and-participants.md), pour la séquence de configuration.

## Modifier ou retirer un accès

1. Consignez le changement approuvé dans le registre local des accès.
2. Modifiez le statut du compte, les programmes, les privilèges ou les associations de participants.
3. Demandez au titulaire de se déconnecter puis de se reconnecter pour tester un changement de privilèges.
4. Vérifiez les accès révisés, y compris le rejet d'une opération interdite.
5. En cas de désactivation, vérifiez le rejet d'une nouvelle connexion.
6. Si le compte utilise des clients API, incluez les jetons et l'accès de ces clients dans la vérification.

Ne supprimez pas l'historique d'un participant pour retirer l'accès d'une personne.
Ne supposez pas qu'un changement de champ du compte web invalide toutes les sessions clientes existantes.

## Rétablir l'accès à un compte

1. Vérifiez l'identité du demandeur selon la procédure locale d'assistance.
2. Utilisez la récupération de mot de passe ou les [outils de réinitialisation autorisés, en anglais](../cli-tools.md#password-reset).
3. Confirmez que le titulaire peut s'authentifier avec les nouveaux identifiants.
4. Consignez l'intervention terminée sans enregistrer le mot de passe.

## Examiner les accès périodiquement

1. Comparez les comptes actifs à la liste actuelle du personnel et des responsabilités.
2. Examinez les privilèges administratifs, programmes actifs, associations de participants et accès de coordination.
3. Retirez les accès que le responsable du programme n'approuve plus.
4. Consignez le relecteur, la date, les changements et les résultats des vérifications.
