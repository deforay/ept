# Security controls and operational requirements

This reference describes reviewed implementation paths in ePT 7.6.21.
It records controls and limitations, not penetration-test results or certification.

## Application controls

| Area | Implemented behaviour | Boundary or qualification |
| --- | --- | --- |
| Web authentication | Separate administrator and data-manager sessions | Participant records are distinct from login accounts |
| Password verification | Password hashes are checked with `password_verify()` | Account reset and access procedures also require operator controls |
| Session inactivity | Normal authenticated web sessions have a 1,800-second idle limit | API token behaviour is separate |
| Participant impersonation | Administrator view-as-participant activity is logged, with a default 900-second idle limit | This is a privileged support function |
| Authorization | Administrative privileges and participant mappings constrain application access | Endpoint-level access needs validation with positive and negative cases |
| CSRF checking | Modifying web requests can require a session token and timing-safe comparison | XHR, API, CLI, error routes and absent session tokens bypass this helper |
| API authentication | Many API services use a data-manager `authToken` parameter | Authentication is not enforced uniformly by one module-wide middleware |
| Audit recording | Call sites write statements, actors and timestamps | This is not proof that every database mutation is audited |
| Audit metadata | Available columns hold role, IP, user agent and session hash | Metadata depends on schema version and request context |
| Input and output handling | Validation, parameterized database operations and escaping helpers exist | Their presence does not establish coverage of every path |

`SecurityService::checkCSRF()` and `Pt_Plugins_PreSetter::preDispatch()` define the request
and session behaviour. `Application_Model_DbTable_AuditLog` defines audit capture.
The API inventory describes [authentication and exchange limits](api-reference.md).

## Hosting responsibilities

These are deployment requirements, not claims about an existing server.

| Control | Responsible role | Evidence needed |
| --- | --- | --- |
| HTTPS and certificate renewal | Hosting operator | Active certificate, redirect behaviour and renewal check |
| Database isolation | Hosting operator | Listener, firewall and permitted application connections |
| Restricted server administration | Hosting operator | Named administrator access and SSH restrictions |
| Supported software and patching | Hosting operator | Installed versions and maintenance record |
| Backup protection | Hosting operator | Access restrictions, encryption settings and restore-key custody |
| Application access review | Programme administrator | Approved users, privileges and participant mappings |
| Incident handling | Programme and hosting owners | Contacts, escalation process and incident record |
| Retention approval | Data owner | Approved retention schedule and disposition process |

Disk encryption, firewall rules, off-host backups and availability monitoring depend
on the hosting environment. Application installation alone does not establish them.

## Sensitive records

Configuration files contain secrets. Backups can contain credentials, account details,
contact information and PT records. API tracking can retain request and response bodies
under `public/uploads/track-api/`, including sensitive authentication material.

These files require restricted access and review before inclusion in a support package.
Database-row cleanup does not establish deletion of corresponding API tracking files.
See [storage and retention](data-lifecycle.md).

## Non-functional requirements requiring agreement

| Requirement | Current evidence | Deployment decision |
| --- | --- | --- |
| Response time and concurrency | Infrastructure sizing guidance, without benchmark results | Agreed workload and measured thresholds |
| Availability | Single-instance application design | Service hours, availability target and maintenance windows |
| Recovery time objective | Restore procedure exists | Maximum tolerated outage and measured restore time |
| Recovery point objective | Scheduled database backup exists | Maximum tolerated data loss and off-host copy frequency |
| Security assurance | Documented implementation controls | Review scope, test results and remediation acceptance |
| Accessibility and supported clients | Browser-based interface | Agreed browsers, devices and accessibility test scope |
| Interoperability | JSON API and file import/export | Named integrations and tested exchange contracts |
| Standards conformity | No conformity assessment supplied with this review | Applicable standards and supporting assessment evidence |

## Validation limits

No independent security assessment is bundled with this documentation.
The [validation package](validation.md) includes proposed security checks.
Passing functional tests alone does not establish security assurance.
