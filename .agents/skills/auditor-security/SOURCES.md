# Security reference sources

This file records authoritative reference families for the skill. Always check the current version/status before relying on a requirement. A link in this file is not proof that a control applies to every project.

| Source | Purpose | Authority / status notes | URL |
|---|---|---|---|
| OWASP Top 10 | High-level web application risk categories | Awareness document; not a complete testing standard | https://owasp.org/www-project-top-ten/ |
| OWASP ASVS | Verifiable application security requirements | Pin the version used by the audit; current project release should be checked | https://owasp.org/www-project-application-security-verification-standard/ |
| OWASP WSTG | Web security testing methodology | Distinguish stable releases from work-in-progress versions | https://owasp.org/www-project-web-security-testing-guide/ |
| OWASP Cheat Sheet Series | Implementation guidance | Check individual sheet freshness and technology context | https://cheatsheetseries.owasp.org/ |
| OWASP API Security Top 10 | API-specific risk taxonomy | 2023 edition is a published reference; check for newer releases | https://owasp.org/www-project-api-security/ |
| MITRE CWE | Weakness taxonomy | Map only when the weakness is sufficiently specific | https://cwe.mitre.org/ |
| FIRST CVSS v4.0 | Vulnerability severity assessment | Use official specification and calculator; do not fabricate missing metrics | https://www.first.org/cvss/v4.0/ |
| NIST SSDF SP 800-218 | Secure software development practices | Verify publication status; distinguish final documents from drafts | https://csrc.nist.gov/Projects/ssdf |
| SLSA | Build provenance and supply-chain integrity | Apply according to build ecosystem and threat model | https://slsa.dev/ |
| PortSwigger Web Security Academy | Educational attack scenarios and labs | Use labs and authorized environments only | https://portswigger.net/web-security |
| Semgrep documentation | Static analysis and taint analysis | Verify language/rule support and scan configuration | https://semgrep.dev/docs/ |
| Trivy documentation | Vulnerability, misconfiguration, secret, and SBOM scanning | Verify enabled scanners and target type | https://trivy.dev/ |
| Gitleaks documentation | Secret detection | Understand allowlists and false-positive behavior | https://github.com/gitleaks/gitleaks |
| OWASP ZAP documentation | Web application DAST | Active scanning requires authorization and scope | https://www.zaproxy.org/docs/ |
| GitHub CodeQL documentation | Code analysis | Verify supported languages; do not assume PHP support | https://codeql.github.com/docs/ |
| Framework documentation | Framework-specific security behavior | Prefer official documentation for the detected version | Use the framework's official documentation site |

## Review policy

For each audit:
1. Record the standard/tool/framework version actually consulted when material.
2. Prefer official sources over secondary summaries.
3. Check whether a cited document is final, stable, draft, or deprecated.
4. Do not apply a control mechanically when the architecture or threat model makes it irrelevant.
5. Record the review date in audit metadata when sources are checked live.
