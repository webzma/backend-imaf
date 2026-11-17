# Severity and remediation prioritization

## Keep severity and priority separate

**Severity** estimates the technical impact and exploitability of a vulnerability. Use CVSS v4.0 when enough information is available and the metric is appropriate.

**Priority** determines remediation order in the actual project. Consider exposure, asset criticality, exploit preconditions, active exploitation evidence, business impact, compensating controls, and remediation cost.

## CVSS v4.0

Use the official FIRST specification and calculator. Do not invent metrics to obtain a score. If required information is unavailable, report severity as unknown or provide a qualitative assessment with rationale.

Common CVSS v4.0 qualitative bands:
- Critical: 9.0-10.0
- High: 7.0-8.9
- Medium: 4.0-6.9
- Low: 0.1-3.9
- None: 0.0

Always identify the CVSS version. Do not mix CVSS v3.1 and v4.0 scores without labeling them.

## Priority scale

- `P0`: urgent containment/remediation; severe, actively exploitable, or catastrophic exposure.
- `P1`: high priority; significant impact or broadly reachable attack path.
- `P2`: normal planned remediation; meaningful risk with constraints or narrower exposure.
- `P3`: low-risk issue or defense-in-depth improvement.
- `P4`: informational or optional hardening.

Priority is contextual, not mechanically derived from CVSS. Explain the reason for unusual priority/severity combinations.

## Avoid score inflation

- A missing header is not automatically a high-severity vulnerability.
- A dependency advisory is not automatically exploitable in the application.
- A scanner severity is not automatically the project's risk rating.
- A theoretical attack without a reachable path should not be presented as confirmed.
- Do not assign a CVSS score when material metrics are unknown.
