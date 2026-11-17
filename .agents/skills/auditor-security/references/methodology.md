# Audit methodology

## 1. Reconnaissance

Build a project profile before testing:
- Repository structure and entry points.
- Languages, frameworks, runtime versions, package managers, and lockfiles.
- Frontend/backend boundaries and API routes.
- Authentication providers, session/token mechanisms, and authorization model.
- Databases, queues, object storage, caches, and external integrations.
- Deployment topology, containers, reverse proxies, cloud services, and CI/CD.
- Sensitive data types and trust boundaries.
- Available tests, linters, scanners, and local environments.

Use multiple indicators to identify a stack. Record uncertainty and version confidence. Do not assume production configuration matches local configuration.

## 2. Scope and planning

Define:
- Components and directories in scope.
- Excluded components and reasons.
- Environment: local, staging, or production.
- Allowed execution level (L0-L4).
- Whether network access is permitted.
- Whether external services may receive any data.
- High-value assets, threat actors, entry points, and trust boundaries.

Prioritize high-risk flows: identity, authorization, sensitive data, file processing, payments, administrative functions, integrations, and deployment secrets.

## 3. Inspection

Review relevant source and configuration. Trace security-sensitive data from input to use:
- Sources: request parameters, headers, cookies, uploaded files, messages, external APIs, environment/configuration.
- Transformations: validation, parsing, normalization, encoding, authorization checks.
- Sinks: SQL/NoSQL queries, shell commands, HTML/DOM, templates, filesystem, network requests, redirects, logs, deserialization, and privileged operations.

Check both positive controls and bypass paths. Inspect middleware, route groups, framework policies, service layers, background jobs, and alternate entry points.

## 4. Candidate validation

For each candidate:
1. Confirm the affected code path is reachable.
2. Identify attacker-controlled inputs and required privileges.
3. Trace relevant data/control flow.
4. Check sanitization, parameterization, authorization, and compensating controls.
5. Consider framework defaults and deployment context.
6. Seek counter-evidence.
7. Determine whether safe reproduction is possible within the authorized scope.

A pattern match alone is not proof. If validation is incomplete, downgrade status and state the missing evidence.

## 5. Classification

Use the statuses and confidence definitions in `finding-schema.md`. Assign severity only with enough impact and exploitability context. Assign remediation priority separately.

Deduplicate findings by root cause. A single missing authorization policy affecting multiple routes may be one root-cause finding with multiple affected locations, unless distinct impact or remediation makes separate findings clearer.

## 6. Reporting

Report:
- Scope and exclusions.
- Project profile and detected versions.
- Coverage tiers.
- Executive summary and finding counts.
- Findings sorted by remediation priority, then severity.
- Tests/tools actually run and their results.
- Checks not run and why.
- Limitations, assumptions, and residual risks.
- Remediation recommendations and approval status.

Produce Markdown and JSON with consistent content.

## 7. Guided remediation

Propose one finding at a time. State the intended code/configuration change, files expected to change, potential side effects, and verification steps. Wait for explicit approval. After approval, make a minimal patch and inspect the diff.

## 8. Post-fix verification

Re-run the most relevant tests or scans, add regression tests where appropriate, inspect the diff, and confirm the original attack path is blocked without breaking legitimate behavior. Use statuses:
- `not_run`
- `passed`
- `failed`
- `partially_passed`
- `blocked`
- `not_applicable`

A fix is not verified merely because the code was changed.
