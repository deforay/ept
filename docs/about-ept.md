# About ePT

ePT is a web application for running proficiency testing programs. A PT provider uses it to enroll laboratories and record shipments of test panels. The same system collects results, scores them against reference values, and returns a report to each laboratory. ePT is free and open source under AGPL-3.0.

This page explains why ePT exists and what it sets out to do. To install it, read [Install](setup.md). To learn the workflow, read [Training](training/README.md).

## What proficiency testing is

Proficiency testing measures whether a laboratory produces correct results. An external organization sends a panel of samples whose true values only that organization knows. The laboratory tests the panel alongside its routine work and reports what it found. The PT provider scores each report against the reference values and tells the laboratory how it performed. A laboratory that scores poorly takes corrective action before the next round.

ePT gives these entities names, and the rest of the documentation uses them.

- A **participant** is a laboratory enrolled in the program.
- A **scheme** is a test type, such as HIV serology or viral load.
- A **shipment** is one round of panels sent to participants.
- A **data manager** is a person who enters results for the participants mapped to that account.

## Why the paper cycle fails

Proficiency testing is a quality measure, but the work of running it is data work. A national program can hold thousands of participants spread across the country. Each round produces a result form per participant, and every form has to be read, entered, scored, and answered.

Programs that run this on paper describe the same sequence. Forms arrive by post over several weeks. Staff key them into a spreadsheet. Someone applies the scoring rules by hand. Reports go out through a mail merge. Programs report that this takes more than three months.

The delay defeats the purpose. Corrective action is the reason for testing a laboratory. A laboratory that learns about a failure three months late has already run patient samples with the same problem. Slow feedback also hides the program's own faults. If a test kit lot performs badly across a region, nobody sees the pattern until long after the lot is in use.

Response rates suffer for a second reason. On paper, the provider does not know who has replied until the forms stop arriving. Chasing non-responders is guesswork, so the participation rate drops.

## What ePT sets out to do

ePT addresses the data work, not the science. It has three goals.

**Cut the turnaround.** Participants submit results through the web, so postal delay leaves the response half of the cycle. A data manager enters results for laboratories that cannot submit their own. Evaluation runs as a batch job over a shipment rather than as manual scoring. Reports are generated and distributed from the same system that holds the results.

**Make evaluation repeatable.** The scoring rules live in code and configuration instead of in one staff member's spreadsheet. Two people evaluating the same shipment get the same verdicts. When a rule changes, it changes in one place.

**Keep the history.** Every response, score, and report stays in the database. A provider can look at how one laboratory has performed across rounds, and at how a district or a test kit has performed across laboratories. That history is what turns individual scores into a picture of where the program needs training.

Around those goals, ePT tracks the response rate while a shipment is open. It sends reminders to participants who have not replied, closes shipments at a deadline, and issues participation certificates.

## Why the algorithm lives in configuration

National testing algorithms differ. HIV diagnosis in one country needs one reactive screening test, and in another it needs three tests in sequence. Vietnam adds a further rule, where a screening laboratory may run all three tests but may not report a positive conclusion. The correct answer for a panel therefore depends on the country, and so does the score.

A program could fix its own algorithm in the code. That choice is what produces a fork per country, and forks split maintenance until a bug fixed in one is left standing in the others.

ePT takes the other route. Schemes carry their own configuration, and a database overlay adjusts the defaults per installation. A country picks its algorithm variant instead of editing scoring code. Report layouts work the same way, because a national program has its own report format and its own signatories.

The limit of this approach is honest to state. A genuinely new algorithm still needs code. Configuration covers the variants ePT already models, and adding a new scheme is a development task, described in [Schemes](SchemeArchitecture.md).

## Designing for where it runs

ePT is built for laboratory networks where connectivity is uneven. Pages stay light, because a participant on a slow link still has to submit a panel response. The application also exposes an API module, so a mobile client can log in and submit results over a phone connection.

Connectivity is not universal, and the design accepts that. A remote laboratory can report results to a better-connected site. That site holds a data manager account and enters the results on the laboratory's behalf. The laboratory stays a participant with its own history and its own report. Only the data entry moves.

## What ePT is not

ePT holds proficiency testing data and nothing else. It is not a laboratory information system, and it stores no patient results. It does not manufacture or track panels, so panel production and courier logistics stay outside it. It does not replace on-site assessment, because proficiency testing answers one question about a laboratory and an audit answers others.

## Alternatives

Spreadsheets and a mail merge remain the common alternative, and they are free and familiar. They break on volume and on memory. Scoring rules survive only as long as the person who wrote the formulas, and last year's results sit in a file nobody can find.

Commercial proficiency testing platforms solve the same problem. They usually price per participant, which is a poor fit for a national program with thousands of laboratories and a fixed budget. Their evaluation logic is also built around the vendor's assumptions rather than a national algorithm.

ePT is the third option. One open-source codebase carries the schemes, and country differences live in configuration and report layouts. Report layouts ship for programs in Ghana, Jamaica, Malawi, Myanmar, Vietnam, and Zimbabwe, alongside a default layout for everyone else.

## Read next

- [Install](setup.md) to set up a server.
- [Training](training/README.md) to learn the admin and participant workflow.
- [Architecture](ARCHITECTURE.md) to see how the code fits together.
- [Schemes](SchemeArchitecture.md) to add or change a test scheme.
