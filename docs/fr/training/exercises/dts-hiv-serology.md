# Exercice : sérologie du VIH sur DTS

Cet exercice parcourt un cycle PT complet pour le programme **Échantillon sur tube séché - Sérologie du VIH**. À la fin, vous aurez créé une enquête PT, inscrit des participants, recueilli des résultats, évalué les réponses et généré les rapports finaux.

**Programme :** Échantillon sur tube séché (DTS) - Sérologie du VIH

**Durée :** 20 à 30 minutes

**Prérequis :**
- Accès à une installation ePT de formation ou de test
- Identifiants de connexion d'un administrateur (administrateur PT ou responsable PT)
- Le modèle Excel d'importation groupée des participants (téléchargé à l'étape 1)

---

## Vue d'ensemble de l'exercice {#exercise-overview}

| Étape | Action | Rôle |
|------|--------|------|
| 1 | Ajouter 8 nouveaux participants (importation groupée) | Administrateur |
| 2 | Inscrire les participants au programme de sérologie du VIH | Administrateur |
| 3 | Créer une nouvelle enquête PT | Administrateur |
| 4 | Ajouter une expédition de sérologie du VIH sur DTS avec ses échantillons | Administrateur |
| 5 | Inscrire les participants à l'expédition | Administrateur |
| 6 | Expédier l'enquête PT | Administrateur |
| 7 | Soumettre des résultats en tant que participants | Participant |
| 8 | Évaluer, générer les rapports et finaliser | Administrateur |
| 9 | Examiner les rapports de performance | Administrateur |
| 10 | Télécharger les rapports en tant que participant | Participant |

---

## Partie administrateur {#admin-section}

### Étape 1 : Ajouter de nouveaux participants (importation groupée) {#step-1-add-new-participants-bulk-import}

1. Connectez-vous en tant qu'**Administrateur PT / PT Manager**
2. Allez dans **Configurer → Participants au PT**
3. Cliquez sur **Importation groupée des participants**
4. Téléchargez le modèle Excel en cliquant sur le lien du côté droit de la page
5. Ouvrez le fichier et remplissez les informations de **8 nouveaux participants** :
   - Les champs à **en-tête rouge** sont obligatoires (ID du participant, nom, etc.)
   - Utilisez une convention de nommage facile à reconnaître ensuite, par exemple de `TRAIN-001` à `TRAIN-008`
   - Définissez un mot de passe dont vous vous souviendrez pour l'étape 7. Si le mot de passe est vide, le participant reçoit le mot de passe par défaut partagé
6. Enregistrez le fichier Excel et téléversez-le sur la même page
7. Vérifiez les lignes sur l'écran de vérification. Rien n'est encore enregistré.
8. Cliquez sur **Importer … ligne(s)**
9. Vérifiez que les participants apparaissent dans la liste **Participants au PT**

> **Astuce :** si vous définissez un mot de passe personnalisé, utilisez le même pour les 8 participants. L'étape 7 sera beaucoup plus rapide. Notez les ID des participants et les mots de passe pour la suite.

### Étape 2 : Inscrire les participants au programme {#step-2-enroll-participants-in-the-scheme}

L'inscription au programme est une configuration unique. Lorsque vous inscrivez des participants à une expédition DTS, les participants inscrits au programme de sérologie du VIH sont sélectionnés par défaut.

1. Allez dans **Configurer → Inscriptions au programme**
2. Dans l'onglet **Listes**, trouvez le groupe **Échantillon sur tube séché - Sérologie du VIH**
3. Cliquez sur **Modifier** pour sa liste **Par défaut**
4. Saisissez une partie du nom d'un participant dans la zone de recherche au-dessus de **Pas sur cette liste** (à gauche)
5. Cliquez sur chacun de vos 8 nouveaux participants pour le déplacer vers **Sur cette liste** (à droite)
6. Cliquez sur **Enregistrer les modifications**

> **Aucun groupe de sérologie du VIH dans l'onglet Listes ?** Personne n'est encore inscrit au programme. Cliquez sur **Nouvelle liste**, choisissez le programme, laissez le nom vide et cliquez sur **Choisir les participants**. Reprenez ensuite à l'étape 4.

> **Vous ne voyez pas vos participants ?** Vérifiez que vous avez bien téléversé le fichier à l'étape 1 et que les participants ont le statut **Actif**. Contrôlez-le dans **Configurer → Participants au PT**.

### Étape 3 : Créer une nouvelle enquête PT {#step-3-create-a-new-pt-survey}

1. Allez dans **Gérer → Enquête PT**
2. Cliquez sur **Ajouter une nouvelle enquête PT**
3. Saisissez un **Code de l'enquête PT** (par exemple, `TRAIN-2026-01`) : lettres, chiffres et traits d'union uniquement
4. Sélectionnez la date du jour comme **Date de l'enquête PT**
5. Cliquez sur **Ajouter**

### Étape 4 : Ajouter une expédition de sérologie du VIH sur DTS {#step-4-add-a-dts-hiv-serology-shipment}

1. Votre nouvelle enquête PT doit apparaître dans la liste. Cliquez sur **Ajouter une expédition**. La liste des expéditions de l'enquête s'ouvre.
2. Sélectionnez **Échantillon sur tube séché - Sérologie du VIH** dans **Type de programme**, puis cliquez sur **New Shipment**
3. Faites défiler la page vers le bas et configurez :
   - **Code de l'expédition** : gardez la valeur par défaut ou modifiez-la
   - **Date limite de soumission des résultats** : choisissez une date située au moins quelques jours plus tard
   - **Échantillons** : ajoutez **5 échantillons** avec le bouton **+Échantillon/Contrôle**
4. Pour chaque échantillon, définissez le **résultat de référence attendu**. Variez les résultats pour que les participants puissent être évalués correctement :

   | Échantillon | Résultat attendu suggéré |
   |--------|--------------------------|
   | Échantillon 1 | VIH positif |
   | Échantillon 2 | VIH négatif |
   | Échantillon 3 | VIH positif |
   | Échantillon 4 | VIH négatif |
   | Échantillon 5 | VIH positif |

5. Cliquez sur **Ajouter une expédition**

> **À propos des échantillons DTS :** en sérologie du VIH, les participants testent chaque échantillon selon l'algorithme national de dépistage (en général un dépistage par le Test-1, puis des Test-2 et Test-3 de confirmation si nécessaire). Le résultat attendu est l'interprétation finale de chaque échantillon.

### Étape 5 : Inscrire les participants à l'expédition {#step-5-enroll-participants-in-the-shipment}

1. Dans la liste des expéditions, cliquez sur le bouton rouge **Inscrire** de votre expédition. Si vous avez quitté la page, allez dans **Gérer → Enquête PT** et cliquez sur **Ajouter des participants** à côté de votre enquête PT pour la rouvrir.
2. Vérifiez que vos 8 participants sont dans la liste de droite. Ils y figurent parce que vous les avez inscrits au programme à l'étape 2. S'il en manque un, trouvez-le à gauche et cliquez dessus.
3. Cliquez sur **Inscrire**

> **Ne cliquez pas sur Tout sélectionner.** Ce bouton place tous les participants actifs du système sur cette expédition.

### Étape 6 : Expédier l'enquête PT {#step-6-ship-the-pt-survey}

1. Allez dans **Gérer → Enquête PT**
2. Trouvez votre enquête PT dans le tableau (elle devrait figurer près du haut)
3. Cliquez sur **Expédier maintenant**
4. Confirmez en cliquant sur **Oui**

Votre enquête PT est maintenant expédiée. Les participants peuvent commencer à soumettre leurs résultats.

> **Que vient-il de se passer ?** L'expédition est maintenant visible dans le portail participant. Dans une situation réelle, c'est à ce moment que les échantillons DTS physiques sont distribués aux sites de dépistage, en même temps que la notification ePT.

---

## Partie participant {#participant-section}

### Étape 7 : Soumettre des résultats en tant que participants {#step-7-submit-results-as-participants}

Répétez cette étape pour **au moins 3 des 8 participants** que vous avez créés. Pour que l'évaluation soit parlante, faites soumettre des résultats corrects par certains participants et des résultats volontairement incorrects par d'autres.

**Pour chaque participant :**

1. Ouvrez la page **Connexion des participants**
2. Connectez-vous avec les identifiants du participant (de l'étape 1)
3. Cliquez sur **Envoi des résultats du PT** dans le menu de gauche
4. Trouvez l'expédition créée à l'étape 4
5. Cliquez sur **Saisir la réponse**
6. Remplissez le formulaire de résultats pour chaque échantillon :
   - **Kit de test** : sélectionnez n'importe quel kit de test dans la liste déroulante pour le Test-1 (et pour les Test-2 et Test-3, le cas échéant)
   - **Résultats de chaque test** : sélectionnez RÉACTIF ou NON RÉACTIF pour chaque test
   - **Interprétation finale** : sélectionnez POSITIF ou NÉGATIF
   - **Champs de documentation** : renseignez le numéro de lot, la date d'expiration, etc. (ils comptent pour le score de documentation)
7. Cliquez sur **Envoyer**
8. Si une page de vérification s'ouvre, vérifiez les réponses, puis cliquez sur le bouton vert **Confirmer la réponse** en bas de la page
9. Déconnectez-vous et recommencez avec le participant suivant

**Modèle de résultats suggéré pour l'exercice :**

| Participant | Soumettre des résultats corrects ? | Objectif |
|-------------|------------------------|---------|
| Participant 1 | Oui : identiques aux résultats attendus | Doit réussir l'évaluation |
| Participant 2 | Oui : identiques aux résultats attendus | Doit réussir l'évaluation |
| Participant 3 | Non : soumettre quelques interprétations finales incorrectes | Doit échouer, pour montrer le calcul du score |

> **Astuce :** remplissez les champs de documentation (numéros de lot, dates d'expiration) pour au moins un participant et laissez-les vides pour un autre. Vous verrez ainsi qu'ePT note la documentation séparément de l'exactitude des tests.

> **Guide vidéo :** https://youtu.be/RE_rS5GlcJs

---

## Partie administrateur (suite) {#admin-section-continued}

### Étape 8 : Évaluer, générer les rapports et finaliser {#step-8-evaluate-generate-reports-and-finalize}

**8a. Évaluer :**

1. Revenez à l'interface d'administration
2. Allez dans **Analyse → Évaluer les réponses**
3. Trouvez votre expédition (utilisez la zone de recherche) et cliquez sur **Voir**
4. Faites défiler jusqu'en bas de la page et cliquez sur le bouton vert **Évaluer**
5. Examinez le tableau des résultats. Vous devez voir :
   - les participants ayant soumis des résultats corrects marqués **Réussi**
   - les participants ayant soumis des résultats incorrects marqués **Échec**
   - un **Score de réponse** (exactitude des tests) et un **Score de documentation** pour chaque participant

> **Remarque :** vous pouvez lancer l'évaluation plusieurs fois, et elle reste invisible pour les participants. Utilisez-la pour vérifier que les résultats sont correctement notés avant de générer les rapports.

**8b. Générer les rapports :**

1. Allez dans **Analyse → Générer les rapports**
2. Trouvez votre expédition et cliquez sur **Voir**
3. Faites défiler la page vers le bas et cliquez sur le bouton vert **Rapport**
4. Cliquez sur **Generate Participant Reports**
5. Patientez un instant, puis actualisez la page. Les rapports sont générés en arrière-plan.

**8c. Finaliser :**

1. Allez dans **Analyse → Finaliser les rapports**
2. Trouvez votre expédition et cliquez sur **Voir**
3. Faites défiler la page vers le bas et cliquez sur le bouton vert **Finaliser**
4. Cliquez sur **Generate Reports and Finalize Shipment**
5. Patientez un instant, puis actualisez la page

> **Important :** la finalisation est définitive. Après la finalisation, les participants peuvent télécharger leur rapport individuel. Dans une situation réelle, vérifiez que l'évaluation et les rapports sont corrects avant cette étape.

### Étape 9 : Examiner les rapports de performance {#step-9-review-performance-reports}

1. Allez dans **Rapports → Rapport sur les expéditions**
2. Trouvez votre expédition et cliquez sur le **lien vert du code de l'expédition**
3. Ouvrez le fichier Excel téléchargé. Il contient cinq feuilles, dont les noms restent en anglais :
   - **Participant List** : le code, le nom et les coordonnées de chaque participant
   - **Results Reported** : les kits de test et les résultats déclarés par chaque participant pour chaque échantillon
   - **Panel Score** : les réponses correctes par échantillon, leur nombre et le pourcentage de réponses correctes
   - **Documentation Score** : le score de documentation de chaque participant
   - **Total Score** : le score du panel, le score de documentation, le score total, la performance globale et les motifs d'échec

---

## Partie participant (suite) {#participant-section-continued}

### Étape 10 : Télécharger les rapports en tant que participant {#step-10-download-reports-as-a-participant}

1. Ouvrez la page **Connexion des participants** et connectez-vous avec l'un des participants qui ont soumis des résultats
2. Cliquez sur **Voir le résultat du PT → Rapport individuel** dans le menu de gauche
3. Recherchez votre expédition et téléchargez le rapport
4. Ouvrez le PDF. Il contient les résultats du participant, ses scores et les éventuelles actions correctives.

> **Guide vidéo :** https://youtu.be/oUsc9-CgvxI

---

## Liste de contrôle de l'exercice {#exercise-checklist}

Utilisez cette liste pour vérifier que vous avez terminé chaque étape :

- [ ] 8 participants ajoutés par importation groupée
- [ ] 8 participants inscrits au programme de sérologie du VIH
- [ ] Enquête PT créée avec un code facile à reconnaître
- [ ] Expédition de sérologie du VIH sur DTS ajoutée avec 5 échantillons et leurs résultats attendus
- [ ] 8 participants inscrits à l'expédition
- [ ] Enquête PT expédiée
- [ ] Résultats soumis pour au moins 3 participants (mélange de résultats corrects et incorrects)
- [ ] Expédition évaluée : résultats réussi ou échec visibles
- [ ] Rapports des participants générés
- [ ] Expédition finalisée
- [ ] Rapport de performance téléchargé et examiné (administrateur)
- [ ] Rapport individuel téléchargé en tant que participant

---

## Dépannage {#troubleshooting}

| Problème | Solution |
|---------|----------|
| Les participants n'apparaissent pas dans l'inscription au programme | Vérifiez qu'ils ont bien été importés dans **Configurer → Participants au PT** et qu'ils ont le statut Actif |
| Des participants ne sont pas dans la liste de droite à l'étape 5 | Trouvez-les à gauche et cliquez dessus. Pour qu'ils soient sélectionnés par défaut la prochaine fois, inscrivez-les au programme (étape 2) |
| Le participant ne voit pas l'expédition après connexion | L'enquête PT doit d'abord être expédiée (étape 6). Vérifiez aussi que le participant est inscrit à l'expédition (étape 5) |
| L'évaluation n'affiche aucun résultat | Vérifiez qu'au moins un participant a soumis des résultats (étape 7) |
| Les rapports ne se génèrent pas | Patientez un instant et actualisez la page. La génération des rapports s'exécute en arrière-plan |
| Le participant ne peut pas télécharger les rapports | L'expédition doit d'abord être finalisée (étape 8c) |
| Réponse non enregistrée après **Envoyer** | Cliquez sur le bouton vert **Confirmer la réponse** sur la page de vérification (étape 7) |
