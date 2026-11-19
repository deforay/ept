---
title: Evaluate PT Survey
summary: Score participant responses, then generate and finalize reports
tags: [evaluate, evaluation, reports, finalize, re-evaluate]
---

# Évaluer l'enquête PT

Dès que les participants commencent à envoyer leurs réponses, venez
ici pour les **évaluer**. L'évaluation applique les règles de
notation et calcule la réussite ou l'échec et les scores de chaque
participant.

Vous pouvez réévaluer autant de fois que nécessaire. Chaque passage
utilise les réponses et les règles les plus récentes. L'évaluation
est interne : **les participants ne voient rien tant que vous n'avez
pas généré et finalisé les rapports.**

## Étape 1 : choisir une enquête PT

Le tableau du haut liste toutes les enquêtes PT du système.

- Trouvez l'enquête voulue.
- Cliquez sur **Voir** dans la colonne Action.
- La ligne est mise en surbrillance et le panneau **Expéditions dans
  le cadre de l'enquête PT ...** s'ouvre **directement sous la
  ligne**.
- Cliquez de nouveau sur **Voir** dans la même ligne pour le
  refermer. Cliquer sur **Voir** dans une autre ligne remplace le
  panneau ouvert : une seule enquête est ouverte à la fois.

Le système mémorise la dernière enquête choisie. Quand vous revenez,
le même panneau des expéditions se rouvre automatiquement.

## Étape 2 : le panneau des expéditions

Le panneau ouvert liste toutes les expéditions de cette enquête, avec :

- **Code de l'expédition** / **Programme** : l'expédition concernée.
- **Nombre d'échantillons** : les échantillons du panel.
- **Nombre de participants** : les laboratoires de cette expédition.
- **Nombre de réponses** : le nombre de réponses reçues à ce jour.
- **% de réponses** : un aperçu rapide du taux de réponse.
- **Nombre de réussites** : le nombre de réussites selon la dernière
  évaluation. Zéro tant que vous n'avez pas évalué.
- **État de l'expédition** : l'étape où en est l'expédition :
  *Pending response* → *Evaluated* → *Reports Generated* →
  *Finalized*.
- **Action** : des boutons qui changent selon l'état (voir
  ci-dessous).

## La colonne Action : quels boutons, à quel moment

Les boutons de cette colonne **changent selon l'état de
l'expédition**. Le parcours est le suivant :

> **Réponses reçues** → **Évaluer** → **Générer les rapports** →
> **Finaliser**

**Une fois l'expédition finalisée, vous ne pouvez plus la
réévaluer.** Les boutons Réévaluer, Générer les rapports et
Finaliser disparaissent tous : seul **Voir** reste. Vérifiez que les
rapports sont corrects *avant* de finaliser, car la finalisation
verrouille l'expédition et envoie les rapports aux participants.

### La première fois : au moins une réponse reçue, jamais évaluée

- **Voir** *(œil vert)* : ouvre le détail par participant (voir
  « Afficher / Réévaluer l'expédition »).
- **Évaluer** *(crayon vert)* : met une tâche d'évaluation en file
  d'attente. Les scores de l'expédition sont calculés peu après. Un
  message indique que la tâche est en file d'attente, puis la page se
  recharge.
- **E-mail N Participants sans réponse** *(enveloppe orange)* :
  apparaît quand certains participants n'ont pas encore répondu.
  Envoie un e-mail de rappel uniquement à ces laboratoires.
  Le libellé indique leur nombre (par exemple, *E-mail 24
  Participants sans réponse*).

Si aucun participant n'a encore répondu, seul un bouton **Voir**
désactivé est affiché.

### Après l'évaluation

- **Voir** : affiche maintenant la dernière évaluation.
- **Réévaluer** : recalcule les scores de l'expédition. Utilisez-le
  après une correction des règles, après des réponses tardives, ou
  chaque fois que vous voulez un nouveau passage.
- **Générer les rapports** *(✓ bleu)* : produit les rapports PDF de
  chaque participant. Désactivé pendant qu'une tâche est en file
  d'attente ou en cours.
- **Plage VL** : uniquement pour les expéditions **VL**. Ouvre
  l'éditeur manuel des plages de référence.
- **Relancer** *(avec le nombre de laboratoires)* : envoie un e-mail
  aux laboratoires qui n'ont pas répondu. Il s'affiche uniquement
  tant que les réponses sont ouvertes : avant la date limite, et
  avant la finalisation ou l'annulation de l'expédition.

### Après la génération des rapports

- **Voir les rapports** *(œil bleu clair)* : ouvre les rapports
  provisoires pour les vérifier.
- **Régénérer les rapports** *(flèche orange)* : reconstruit les
  rapports à partir de la dernière évaluation. Utilisez-le après
  chaque modification.
- **Finaliser** *(✓ bleu)* : verrouille les rapports et les rend
  visibles aux participants. Désactivé tant que la date de
  l'expédition n'est pas atteinte.
- Réévaluer, Voir, Plage VL et le bouton d'e-mail restent
  disponibles.

### Après la finalisation

- **Voir** : seul Voir reste. L'expédition est verrouillée : plus
  d'évaluation, de régénération ni de finalisation. **Les
  participants peuvent désormais voir les rapports** sur leur
  tableau de bord.

## Pendant l'exécution d'une tâche

Pendant l'évaluation ou la génération des rapports, l'expédition
affiche un état temporaire (*draft*, *ready*, *queued*,
*processing*) et **tous les boutons d'action sont désactivés**. Cela
vous empêche de lancer deux fois la même tâche. Rechargez la page pour
afficher le nouvel état.

Si une expédition en état *queued* semble bloquée, le bouton
**Évaluer** est réactivé au bout de 15 minutes pour que vous puissiez
réessayer.

## Conseils

- **Réévaluez librement.** Chaque passage remplace les scores
  précédents. Les participants ne voient que ce que vous finalisez.
- **Générez les rapports avant de finaliser** pour pouvoir vérifier
  les PDF. La finalisation est irréversible.
- Pour les expéditions VL, définissez la **Plage VL** avant de
  générer les rapports : les plages influencent la réussite ou
  l'échec.
- Utilisez le bouton d'e-mail aux **Participants sans réponse** avec
  prudence : chaque clic envoie un e-mail.
