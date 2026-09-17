# API route inventory

This inventory describes the controllers present in ePT 7.6.21.
It identifies integration entry points, not a complete client contract or a security assessment.
Default Zend routes use `/api/{controller}/{action}`.

## Request conventions

| Convention | Implementation |
| --- | --- |
| Login body | JSON with `userId` and `key`, where `key` is the password |
| API credential | `authToken` parameter used by many service methods |
| Read parameters | Controllers generally call `getAllParams()` |
| Write payloads | Controllers generally decode `php://input` as JSON |
| Application outcomes | Many JSON responses include `status`, `message` and sometimes `data` |
| Failure values | Include `fail` and `auth-fail`, depending on the service |
| Downloads | Report download actions render views and are not uniformly JSON-only |

HTTP status alone does not establish application success. Response envelopes and
required fields vary by service. Several actions lack an explicit HTTP method check.
The inputs below describe controller parsing, not enforced method restrictions.

## Login and initialization

| Route | Input | Purpose |
| --- | --- | --- |
| `/api/login` | POST JSON | Authenticate a data manager |
| `/api/login/change-password` | POST JSON | Change a data-manager password |
| `/api/login/forget-password` | POST JSON | Request password recovery |
| `/api/login/login-details` | Request parameters | Obtain login details |
| `/api/init/get` | Request parameters | Obtain reference lists |

Example login body, using illustrative credentials:

```json
{"userId":"user@example.org","key":"EXAMPLE_PASSWORD"}
```

A successful login returns an `authToken` in `data` and replaces the account's stored
token. Token rotation and profile checks have separate implementation paths.
The web idle timeout is not the API token lifetime.

## Shipment routes

| Route | Input | Purpose |
| --- | --- | --- |
| `/api/shipments/get` | Request parameters | Retrieve shipment details |
| `/api/shipments/get-shipment-form` | Request parameters | Retrieve shipment form information |
| `/api/shipments/save-form` | JSON body | Save shipment form information |
| `/api/shipments/dts` | Request parameters | Retrieve DTS shipment data |
| `/api/shipments/save-dts` | JSON body | Submit DTS responses |
| `/api/shipments/vl` | Request parameters | Retrieve viral-load shipment data |
| `/api/shipments/save-vl` | JSON body | Submit viral-load responses |
| `/api/shipments/eid` | Request parameters | Retrieve EID shipment data |
| `/api/shipments/save-eid` | JSON body | Submit EID responses |
| `/api/shipments/custom-tests` | Request parameters | Retrieve custom-test shipment data |
| `/api/shipments/save-custom-tests` | JSON body | Submit custom-test responses |

The shared response-save service expects an `authToken` and a `data` collection.
Records include `schemeType`, `shipmentId`, `participantId` and scheme-specific fields.
The dispatch values include `dts`, `vl`, `eid` and `custom-tests`.
The last value is an API dispatch value, not the `generic` label used elsewhere.

The controller route alone does not set the save payload's scheme.
Shipment editability and mandatory-field checks occur in the service path.
Full payload schemas and negative cases require verification for each intended client.

## Participant and reporting routes

| Route | Input | Purpose |
| --- | --- | --- |
| `/api/participant/get` | Request parameters | Retrieve individual-report information |
| `/api/participant/summary` | Request parameters | Retrieve summary-report information |
| `/api/participant/get-filter` | Request parameters | Retrieve participant filters |
| `/api/participant/get-profile-check` | Request parameters | Retrieve profile-check information |
| `/api/participant/update-profile` | JSON body | Update profile information |
| `/api/participant/download` | Generated link parameters | Render individual-report download content |
| `/api/participant/download-summary` | Generated link parameters | Render summary-report download content |
| `/api/participant/resend` | Encoded request parameter | Resend email verification |
| `/api/participant/file-downloads` | Request parameters | Retrieve certificate download information |
| `/api/aggregated-insights` | Controller accepts request parameters | Return instance and aggregate information |

## Integration boundaries

The aggregated-insights service does not perform an `authToken` check in its reviewed
method. Download actions use separate link handling. A blanket claim that every API
route requires the same authentication is unsupported.

There are no dedicated TB, DBS, recency or COVID-19 response controller actions in this
inventory. Reference data for recency appears under the DTS reference payload.
That does not establish a dedicated recency submission API.

No implemented route establishes a live integration with another national system.
A deployment integration record needs client ownership, enabled routes, exchange
frequency, sample payloads, authentication handling and executed acceptance cases.

## Implementation sources

Controllers: `application/modules/api/controllers/`.
Services: `ApiServices.php`, `Shipments.php`, `Participants.php` and `DataManagers.php`.
Account and token behaviour: `application/models/DbTable/DataManagers.php`.
