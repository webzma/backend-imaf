# Auditor Security

An advanced, framework-aware Agent Skill for auditing frontend, backend, APIs, databases, and infrastructure.

## What it does

- Discovers project architecture, technologies, and versions.
- Plans an audit around real attack surfaces and trust boundaries.
- Reviews source code, configuration, dependencies, and deployment artifacts.
- Identifies and validates security weaknesses with evidence.
- Classifies findings by status, confidence, severity, and remediation priority.
- Produces consistent Markdown and JSON reports.
- Guides remediation one finding at a time, only after explicit approval.

## Safety model

The initial audit is read-only. Active testing requires explicit authorization and a defined scope. The skill does not reveal detected secrets, install tools automatically, upload private code without permission, or perform destructive actions.

## Installation

### Agent Skills (Preferred)

Install via skills.sh:

```sh
npx skills add emcahell/auditor-security-skill
```

This installs to `.agents/skills/auditor-security-skill/SKILL.md`.

### Manual

Place the `auditor-security` directory in an agent-supported skills directory:

- Codex: `.agents/skills/auditor-security/`
- Claude Code: `.claude/skills/auditor-security/`
- OpenCode: `.opencode/skills/auditor-security/`

The same skill directory and `SKILL.md` are shared. Agent-specific behavior should be documented only when a real compatibility difference is verified.

## Usage examples

- "Audit this project for security vulnerabilities."
- "Review the authentication and authorization implementation."
- "Check this API for BOLA and broken access control."
- "Audit dependencies and deployment configuration."
- "Explain finding AS-AUTHZ-001 and propose a fix."

An audit request does not authorize code changes. Each remediation requires separate approval.

## Reports

The skill produces:
- `audit-report.md`: readable audit report.
- `audit-report.json`: machine-readable report using `templates/audit-report.schema.json`.

Reports are not written into the project unless the user specifies or approves a destination.

## Coverage tiers

- Tier 1 — Verified: procedures tested against representative fixtures or projects.
- Tier 2 — Supported: specific guidance exists, with partial automation or validation.
- Tier 3 — Best effort: general security analysis without a specialized coverage claim.

Do not treat the initial release as Tier 1 across every stack. Update coverage only after tests have actually been run.

## Structure

See `SKILL.md` for the entry point and `references/` for methodology, safety rules, finding definitions, domains, and framework-specific guidance.

## Version

Initial implementation: `0.1.0` (development baseline). Compatibility and coverage claims remain subject to testing.
