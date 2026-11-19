---
title: Guides (internal — schema notes)
summary: How workflow guides are structured. NOT shown in the help drawer.
internal: true
---

# Schéma des fichiers de guide

Chaque guide est un fichier `.md` de ce dossier. Le frontmatter sert
d'index des étapes. Le corps contient un titre H2 par étape, dans le
même ordre.

```yaml
---
title: How to add a shipment            # shown in chip + topic list
summary: One-line description for the guides list
audience: admin                          # admin | participant
estimated_minutes: 5                     # rough total time
tags: [guide, shipment, workflow]
steps:
  - id: 1
    title: Create or open the PT Survey
    # target_pages: controller/action slugs where this step is "active".
    # The drawer matches these against the current page to show
    # "✓ You're here" vs the "Open this screen" button.
    target_pages: [distributions/index, distributions/add]
    # open_url: optional. Where "Open this screen" goes when the first
    # target page is outside the admin module or needs parameters.
    # Site-relative paths only, for example /reports/certificates.
  - id: 2
    title: Click "Add Shipment"
    target_pages: [distributions/index]
  ...
---
```

Section du corps pour chaque étape (utilisez les mêmes titres que dans
le frontmatter) :

```markdown
## Step 1: Create or open the PT Survey

Plain-language explanation of what the user does on this screen.

Bullets, **bold UI labels**, short paragraphs.

## Step 2: Click "Add Shipment"

...
```

## Conventions

- Les slugs de `target_pages` ont la forme `controller/action`, comme la
  table des slugs de `application/layouts/scripts/admin.phtml`. Utilisez
  `controller/index` pour couvrir toutes les actions de ce contrôleur.
- Gardez chaque étape sous 150 mots environ. L'utilisateur lit une étape
  à la fois.
- La première étape n'a souvent pas de `target_pages` quand elle dit
  « Vérifiez que vous avez X avant de commencer ». C'est normal. Le
  panneau d'aide n'affiche alors pas de repère « ✓ You're here ».
- Employez un langage simple. Pas de « modal », « configuré »,
  « mappé ». Écrivez « fenêtre », « réglé », « relié ». Les règles sont
  les mêmes que pour l'aide de chaque page. Seule exception : un libellé
  que l'écran affiche lui-même. Si la pastille d'état affiche
  *Configuré*, appelez-la *Configuré* et expliquez-la.
- Pour renvoyer vers l'aide d'une page, utilisez
  `[Voir : Ajouter une expédition](../shipment-add.md)` quand une étape
  s'explique mieux sur sa propre page.

## Ce que fait le panneau d'aide avec ces fichiers

1. Il lit le frontmatter. Il connaît ainsi la liste des étapes et
   l'étape en cours.
2. Il découpe le corps aux titres H2. Il n'affiche que l'étape en cours.
3. Il lit les `target_pages` de l'étape en cours. Si le slug de la page
   actuelle figure dans cette liste, il affiche « ✓ You're here ».
   Sinon, il affiche un lien « Open this screen ».
4. Il enregistre `sessionStorage.ept.help.activeGuide` et
   `ept.help.guideStep`. Le guide reste ainsi épinglé quand
   l'utilisateur change de page.

## Nommage des fichiers

Commencez par un verbe d'action, en kebab-case. Deux guides existent
aujourd'hui :

- `add-a-shipment.md`
- `enroll-participants.md`

Les guides encore à écrire suivent la même forme, par exemple
`evaluate-and-finalize.md` et `set-up-a-new-pt-programme.md`.
