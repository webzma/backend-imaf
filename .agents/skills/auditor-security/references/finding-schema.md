# Finding schema and evidence rules

## Required fields

Each finding should contain:

| Field | Meaning |
|---|---|
| `id` | Stable identifier, e.g. `AS-AUTHZ-001` |
| `title` | Concise description |
| `category` | Catalog category |
| `status` | Confirmed, probable, potential, misconfiguration, hardening, informational |
| `confidence` | High, medium, low |
| `severity` | Critical, high, medium, low, none, or unknown |
| `priority` | P0-P4 or unassigned |
| `component` | Affected service/module |
| `locations` | File paths and line ranges, endpoint, or configuration key |
| `evidence` | Redacted code/configuration behavior and reasoning |
| `preconditions` | Attacker access and required conditions |
| `impact` | Realistic confidentiality, integrity, availability, or business impact |
| `recommendation` | Specific contextual remediation |
| `verification` | Reproducible test and current status |
| `references` | Applicable CWE, OWASP, advisory, or official docs |
| `limitations` | Missing evidence or assumptions |

## Status definitions

- `confirmed`: evidence demonstrates the vulnerability in the assessed context.
- `probable`: strong evidence exists, but a decisive condition or reproduction is missing.
- `potential`: a suspicious pattern needs further investigation.
- `misconfiguration`: an insecure configuration is demonstrated in context.
- `hardening`: preventive improvement; no exploitable vulnerability is established.
- `informational`: relevant observation without demonstrated security impact.

## Confidence

- `high`: direct evidence and relevant control-flow/context have been reviewed.
- `medium`: credible evidence, but some environmental or execution detail is unknown.
- `low`: limited indicators; further inspection is necessary.

Confidence is not severity. A low-confidence issue can still deserve investigation, but must not be presented as confirmed.

## Evidence rules

- Cite exact paths and line ranges where possible.
- Explain the relevant behavior, not just a suspicious line.
- Include safe, minimal reproduction steps only within authorized scope.
- Redact secrets and sensitive personal data.
- Distinguish static inference from executed verification.
- Record tool name/version and command only when useful and safe.
- Never fabricate line numbers, outputs, HTTP responses, or test results.

## Identifier convention

Use `AS-<DOMAIN>-<NNN>`, for example:
- `AS-AUTH-001`
- `AS-AUTHZ-001`
- `AS-INJ-001`
- `AS-API-001`
- `AS-SECRET-001`

Maintain stable IDs within a report and when tracking the same finding across revisions. Do not reuse an ID for a different root cause.
