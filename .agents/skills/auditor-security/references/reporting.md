# Reporting requirements

Produce both Markdown and JSON. They must describe the same audit, scope, findings, and verification results.

## Markdown report sections

1. Title, date, project identifier.
2. Executive summary.
3. Scope, exclusions, environment, and execution level.
4. Project profile: technologies, versions, version confidence, and coverage tiers.
5. Findings summary by status, severity, and priority.
6. Detailed findings, sorted by priority and severity.
7. Tools and tests actually run, with versions and results.
8. Checks not performed and reasons.
9. Limitations, assumptions, and residual risk.
10. Remediation queue and approval state.

## JSON report

Conform to `templates/audit-report.schema.json`. Keep the schema version explicit. Use null/unknown values for unavailable facts rather than fabricating them. Do not include raw secrets or sensitive personal data.

## Consistency

- Every finding in Markdown must exist in JSON and vice versa.
- IDs, statuses, severity, priority, locations, and verification states must match.
- Summary counts must reconcile with the findings array.
- Tool results must distinguish passed, failed, skipped, and not run.
- Do not write report files into the audited repository without a user-specified or approved destination.
