# Activation tests

Test whether the skill activates for:
- "Audit this repository for security vulnerabilities." (should activate)
- "Review my API authorization controls." (should activate)
- "Explain what SQL injection means." (should not require project audit workflow)
- "Translate this security article." (should not activate)
- "Fix finding AS-AUTHZ-001." (should activate remediation flow and request/confirm finding-specific approval before edits if not already explicit)
