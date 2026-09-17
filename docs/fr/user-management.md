# Comment gérer les accès utilisateurs

[English](../user-management.md)

Créez et examinez les comptes ePT 7.6.21 avec les droits nécessaires aux tâches attribuées.

## Avant de commencer

Obtenez le nom du titulaire, ses responsabilités approuvées et les programmes ou participants autorisés.
Utilisez un compte administrateur disposant des privilèges requis de configuration ou de gestion des participants.
Gardez les fiches participants distinctes des comptes qui saisissent leurs résultats.

## Ajouter un responsable PT

1. Ouvrez **Configurer → Responsables PT**.
2. Sélectionnez **Ajouter**.
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

1. Créez ou sélectionnez le compte du gestionnaire de données dans la gestion des participants.
2. Attribuez les fiches prévues par le processus d'association participant-gestionnaire.
3. Définissez les options approuvées de consultation seule ou de coordination disponibles pour le compte.
4. Confirmez l'inscription du participant au programme et à l'expédition concernés.
5. Vérifiez que le compte voit l'expédition et le participant prévus.
6. Vérifiez qu'un participant non associé reste inaccessible.

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
