# ePT Translation Guide

This guide provides standards and terminology for translating the ePT (e-Proficiency Testing) platform. It ensures consistency across all language translations with proper context for medical/laboratory terminology.

---

## Project Context

ePT is a **medical proficiency testing platform** for laboratory quality assurance. It enables organizations to:

- Create and manage PT (Proficiency Testing) shipments
- Enroll participant laboratories and track their responses
- Evaluate results against reference data
- Generate reports for analysis and certification

### Critical Context

**"PT" = Proficiency Testing** (laboratory quality assurance testing)

PT does NOT mean:
- Physical Therapy
- Physiotherapy
- Patient
- Part-Time

Always translate PT in the context of laboratory quality control and testing proficiency.

---

## Core Terminology

### Platform Concepts

| English Term | Definition | Translation Notes |
|--------------|------------|-------------------|
| **Proficiency Testing (PT)** | A program where laboratories test unknown samples to assess their testing accuracy | Core concept - never confuse with medical therapy |
| **PT Program** | An organized testing program managed through the platform | The main organizational unit |
| **PT Manager** | Administrator role managing PT programs | A user role, not a software feature |
| **Scheme** | A category of tests (e.g., HIV, TB, COVID-19) | Use "program" or "testing program" sense, not "diagram" |
| **Shipment** | A batch of samples sent to participating laboratories | Physical delivery of test materials |
| **Round** | A testing cycle within a scheme | Time-based testing period |
| **Panel** | A set of samples within a shipment | Collection of test specimens |
| **Participant** | An enrolled laboratory taking part in PT | Not an individual person, but a lab/facility |

### Test Schemes (Abbreviations)

Keep abbreviations in English, but translate the full names:

| Abbreviation | Full Name | Context |
|--------------|-----------|---------|
| **DTS** | Dried Tube Specimen | HIV Serology rapid testing |
| **VL** | Viral Load | HIV quantitative testing |
| **EID** | Early Infant Diagnosis | PCR-based infant HIV testing |
| **TB** | Tuberculosis | Molecular and microscopy testing |
| **Recency** | HIV Recency Testing | Recent infection detection |
| **COVID-19** | Coronavirus Disease 2019 | Keep as "COVID-19" in all languages |
| **DBS** | Dried Blood Spot | EIA and Western Blot testing |

### Laboratory & Testing Terms

| English | Definition |
|---------|------------|
| Laboratory | Facility performing the tests |
| Sample / Specimen | Material being tested |
| Result | Test outcome |
| Reference Result | The correct/expected result |
| Reported Result | What the participant laboratory submitted |
| Concordance | Agreement between reported and reference results |
| Discordance | Disagreement between reported and reference results |
| Score | Numerical evaluation of performance |
| Evaluation | Assessment of laboratory performance |
| Algorithm | Testing procedure/workflow |
| Assay | A test or testing procedure |
| Reagent | Chemical substance used in testing |

### User Interface Terms

| English | Usage Context |
|---------|---------------|
| Download | Retrieving files from server |
| Upload | Sending files to server |
| Submit | Sending form data / responses |
| Save | Storing changes |
| Cancel | Aborting an action |
| Delete | Removing permanently |
| Edit | Modifying existing data |
| View | Displaying without editing |
| Report | Generated document with results |
| Dashboard | Overview/summary page |
| Settings | Configuration options |
| Establishment | Institution/facility (use formal term) |

### User Roles

| English | Context |
|---------|---------|
| Administrator | Full system access |
| Admin | Abbreviated form of Administrator |
| Data Manager | Manages participant data |
| Participant | Laboratory user submitting results |
| User | Generic system user |

### Status Terms

| English | Context |
|---------|---------|
| Pending | Waiting for action |
| In Progress | Currently being processed |
| Completed | Finished successfully |
| Active | Currently enabled |
| Inactive | Currently disabled |
| Approved | Accepted/validated |
| Rejected | Declined/invalid |
| Finalized | Locked and complete |
| Shipped | Samples sent to participants |
| Evaluated | Results have been assessed |

---

## Translation Rules

### 1. Preserve Technical Elements

Never translate or modify:
- Variable placeholders: `%s`, `%d`, `%1$s`, `{0}`, `{{name}}`
- HTML tags: `<strong>`, `<br>`, `<a href="...">`
- Email addresses and URLs
- Code/technical identifiers

Example:
```
Source: "Hello %s, you have %d messages"
Translation: "[Greeting] %s, [you have] %d [messages]"
```

### 2. Maintain Placeholder Order

Some languages require different word order. Use positional placeholders when reordering:
```
Source: "%s uploaded %d files"
Translation (if reordering needed): "%2$d [files uploaded by] %1$s"
```

### 3. Context Over Literal Translation

Translate meaning, not words:
- "No. of Responses" = "Number of Responses" = [Count of responses submitted]
- "PT Manager" = [Person who manages proficiency testing programs]

### 4. Consistency

Use the same translation for the same term throughout:
- Pick ONE translation for "Email" and use it everywhere
- Pick ONE translation for "Scheme" and use it everywhere
- Document your choices in language-specific notes

### 5. Formality

Use formal register throughout the application:
- Use formal "you" forms where applicable (vous, Sie, usted, etc.)
- Use professional/technical language
- Avoid slang or colloquialisms

### 6. Completeness

- Never leave `msgstr` empty for strings that need translation
- Translate the complete meaning, including implied articles/prepositions
- Match the tone and intent of the original

---

## Common Mistakes

### 1. Wrong Context for "PT"

| Wrong | Correct |
|-------|---------|
| Physical Therapy | Proficiency Testing |
| Physiotherapy | Proficiency Testing |
| Patient | (PT never means Patient here) |

### 2. Wrong Context for "Scheme"

| Wrong | Correct |
|-------|---------|
| Diagram/Blueprint | Testing Program/Category |
| Plan/Plot | Testing Program/Category |

### 3. Wrong Context for "Panel"

| Wrong | Correct |
|-------|---------|
| UI Panel/Section | Set of test samples |
| Control Panel | Set of test samples |

### 4. Placeholder Errors

| Wrong | Correct |
|-------|---------|
| Removing `%s` | Keep `%s` exactly |
| Translating `%d` | Keep `%d` exactly |
| Changing `{name}` to `{nom}` | Keep `{name}` exactly |

---

## Quality Checklist

Before submitting translations:

- [ ] All strings have translations (no empty `msgstr`)
- [ ] Placeholders preserved exactly (`%s`, `%d`, `{0}`, etc.)
- [ ] HTML tags preserved exactly
- [ ] "PT" translated as Proficiency Testing, not therapy
- [ ] "Scheme" translated as testing program, not diagram
- [ ] Terminology consistent throughout the file
- [ ] Formal register used (formal "you")
- [ ] No trailing or leading spaces in translations
- [ ] Proper punctuation and capitalization for target language
- [ ] Accents and special characters correct for target language

---

## File Format

### PO File Structure

Translation files use GNU gettext format (`.po`):

```po
# Comment providing context
#: path/to/source/file.php:123
msgid "Original English text"
msgstr "Translated text"
```

- `msgid`: Source text (DO NOT MODIFY)
- `msgstr`: Your translation
- `#:` Reference to source file location
- `#` Comments provide context

### Plural Forms

Handle plurals according to target language rules:

```po
msgid "1 result"
msgid_plural "%d results"
msgstr[0] "singular form"
msgstr[1] "plural form"
```

Note: Some languages have more than 2 plural forms (e.g., Russian, Arabic).

---

## Adding a New Language

1. Copy `application/languages/en_US/en_US.po` as template
2. Create new directory: `application/languages/{locale}/{locale}.po`
3. Update the PO file header with language metadata
4. Translate all `msgstr` entries
5. Run quality checks using this guide
6. Test in the application

---

## Language-Specific Notes

### French (fr_FR)
- Use formal "vous" throughout
- Include articles: "Générer les rapports" not "Générer rapports"
- Use proper accents: É, è, ê, ç, à
- "Email" → "Courriel" or "E-mail" (pick one)

### Spanish (es_ES)
- Use formal "usted" throughout
- "Email" → "Correo electrónico"

### Portuguese (pt_BR / pt_PT)
- Distinguish Brazilian vs European Portuguese
- Use formal register

*(Add notes for other languages as needed)*

---

## Contact

For translation questions or to report issues:

GitHub: [deforay/ept](https://github.com/deforay/ept)
