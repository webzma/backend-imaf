# Tool selection and safe use

Tools supplement manual review; they do not replace contextual validation. First detect what is installed and supported. Do not install automatically.

| Tool | Typical role | Important limitations |
|---|---|---|
| Semgrep | SAST, pattern and taint analysis | Rule quality and language support vary; validate alerts |
| Trivy | Dependency, image, filesystem, IaC, secret, and SBOM scanning | Confirm which scanners and targets are enabled |
| Gitleaks | Secret detection in working tree and Git history | Redact output; verify allowlists and false positives |
| OWASP ZAP | DAST and passive/active web scanning | Active scanning can alter state or affect availability; scope and approval required |
| CodeQL | Semantic code analysis for supported languages | Check current language support; do not claim PHP/Laravel analysis |

## Decision sequence

1. Identify the stack and target type.
2. Check whether a compatible tool is already available.
3. Inspect tool version, configuration, and relevant command help.
4. Choose the least invasive scan that answers the question.
5. Explain network access, telemetry, file writes, and output risks.
6. Run only within the allowed execution level.
7. Validate alerts manually and record exact tool/version/results.

## Tool result handling

- A clean scan does not prove the project is secure.
- A scanner alert is a candidate, not automatically a confirmed finding.
- Report skipped scanners and unsupported languages.
- Do not expose secrets in command output or reports.
- Do not upload private code to hosted analysis services without permission.
- Avoid broad active scans against production.
