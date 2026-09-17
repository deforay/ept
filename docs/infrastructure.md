# Infrastructure Planning

A one-page reference for procurement and infrastructure teams planning an ePT deployment.

---

## Technology stack

| Component | Technology |
| --------- | ---------- |
| Language | PHP 8.4 |
| Database | MySQL 8+ |
| Web Server | Apache 2 with `mod_rewrite` |
| OS | Ubuntu 24.04 LTS (recommended) |
| Chart renderer | Node.js 22 or later, with locked npm dependencies |

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

These tiers describe the default single-host plan. They are estimates, not benchmarked
capacity guarantees. Acceptance testing needs a representative workload and measured results.

---

## Storage and backup

All ePT files (uploads, generated reports, charts, logs) live on the **local filesystem of the application host** alongside MySQL. There is no external object-storage dependency.

A provisional medium-tier storage allowance is **20–40 GB** after 2–3 years.
Actual usage depends on uploads, reports, logs and retained backups. No benchmark dataset
is attached to this estimate.

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

ePT implements authenticated web sessions, privilege checks, password hashing, CSRF
checks on covered requests and application audit recording. CSRF exemptions and
endpoint-specific API checks limit these controls. Audit coverage is call-site based,
not a guarantee that every mutation is recorded. See [Security controls](security.md).

Infrastructure-side: keep MySQL on a private subnet, restrict SSH, enable disk encryption, and patch OS/PHP monthly. ePT itself is upgraded via `upgrade.sh` (see [Updating an ePT installation](updating.md)).

---

## Further reading

- [Setup Guide](setup.md) — installation
- [Architecture Guide](ARCHITECTURE.md) — system design and security
- [CLI Tools Reference](cli-tools.md) — operational scripts
