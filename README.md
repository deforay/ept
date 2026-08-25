# ePT

Open source software for running proficiency testing programs. A PT provider uses ePT to enroll laboratories, record shipments of test panels, collect results, score them against reference values, and return a report to each laboratory.

## What ePT is for

Proficiency testing measures whether a laboratory produces correct results. The science is straightforward. The data work is not. A national program can hold thousands of participating laboratories, and running the round on paper and spreadsheets takes more than three months from panel to report. By the time a laboratory hears about a failure, it has run the next round of patient samples with the same problem.

ePT compresses that cycle.

- Participants submit results through the web, so the post leaves the response half of the cycle. A data manager enters results for laboratories that cannot submit their own.
- Evaluation runs as a batch job over a shipment. Scoring rules live in code and configuration, not in one staff member's spreadsheet.
- Reports are generated and distributed from the same system that holds the results.
- Every response, score, and report stays in the database, so performance history survives across rounds.

National testing algorithms differ between countries, so schemes carry their own configuration and report layouts. A program picks its algorithm variant instead of forking the code.

Built-in schemes cover HIV serology (DTS and DBS), viral load, early infant diagnosis, recency (RTRI), SARS-CoV-2, and tuberculosis, alongside custom tests you configure yourself.

Read [About ePT](https://deforay.github.io/ept/about-ept/) for the longer version.

## Install

### Ubuntu server

For a server that runs real PT rounds. Requires Ubuntu 22.04 LTS or later, and 24.04 LTS is preferred. The script installs Apache, PHP 8.4, MySQL 8, and Node.js, sets up the cron scheduler, and can issue a Let's Encrypt certificate if the machine has a public domain.

```bash
cd ~
sudo wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh
sudo chmod +x ept-setup.sh
sudo ./ept-setup.sh
```

Pass an existing database with `--db /path/to/ept.sql.gz` (a URL works too). Update later with `sudo ept-update`.

### Docker

For local development, demos, and evaluation. Nothing to install but Docker itself.

```bash
git clone https://github.com/deforay/ept.git
cd ept
docker compose up --build -d
docker compose exec ept php bin/seed-admin.php
```

Access the admin panel at [http://localhost/admin](http://localhost/admin). Change the default database password in `docker-compose.yml` before putting this on a network.

### Windows

WampServer, for development only. See the [setup guide](https://deforay.github.io/ept/setup/).

## Documentation

Setup guides, backup and recovery, architecture notes, CLI tools, and training material:

**[deforay.github.io/ept](https://deforay.github.io/ept/)**

## License

[GNU Affero General Public License v3.0](LICENSE.md) (AGPL-3.0)

## Contact

[amit@deforay.com](mailto:amit@deforay.com) | [GitHub Issues](https://github.com/deforay/ept/issues)
