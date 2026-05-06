# Infrastructure Planning

A one-page reference for procurement and infrastructure teams planning an ePT deployment.

---

## Technology Stack

| Component | Technology |
| --------- | ---------- |
| Language | PHP 8.4 |
| Database | MySQL 8+ |
| Web Server | Apache 2 with `mod_rewrite` |
| OS | Ubuntu 24.04 LTS (recommended) |

Docker Compose is also supported (see [setup.md](setup.md)).

---

## Sizing

ePT is not a daily-use application — participants log in only during open shipment windows (typically a few weeks per round). The real CPU drivers are report generation and email flushes, not interactive traffic.

| Tier | Participants | vCPU | RAM | Disk |
| ---- | ------------ | ---- | --- | ---- |
| Small | up to ~1,000 participants | 2 | 2 GB | 50 GB SSD |
| **Medium (default)** | up to ~4,000 participants | 2 | 4 GB | 100 GB SSD |
| Large | 4,000+ participants | 4 | 8 GB+ | 200 GB SSD |

App + MySQL run on the same host.

---

## Storage & Backup

All ePT files (uploads, generated reports, charts, logs) live on the **local filesystem of the application host** alongside MySQL. There is no external object-storage dependency.

A typical medium-tier deployment occupies **20–40 GB** after 2–3 years.

**Backup policy:**

- Daily MySQL dump (`db-tools backup` ships with ePT).
- Weekly file-level backup of `public/uploads/` and `downloads/`.
- Copy backups off the application host (second VM, attached backup volume, or cloud object storage — any off-host destination works).

---

## Networking

- **Static IP** — required for a stable DNS record and Let's Encrypt SSL.
- **Domain** — one A-record (e.g. `ept.example.org`).
- **TLS** — free via Let's Encrypt; automated by `setup.sh`.
- **Outbound SMTP** — required for notifications and password resets.
- **Inbound ports** — 80 and 443.

---

## Security

ePT enforces per-role authentication (admin / data manager / participant) with bcrypt passwords, CSRF tokens on state-changing forms, and a full audit log capturing actor, IP, user agent, and timestamp for every mutation. See [ARCHITECTURE.md → Security](ARCHITECTURE.md#security) for details.

Infrastructure-side: keep MySQL on a private subnet, restrict SSH, enable disk encryption, and patch OS/PHP monthly. ePT itself is upgraded via `upgrade.sh`.

---

## Further Reading

- [Setup Guide](setup.md) — installation
- [Architecture Guide](ARCHITECTURE.md) — system design and security
- [CLI Tools Reference](cli-tools.md) — operational scripts
