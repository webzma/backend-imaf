# Secrets and cryptography

Search relevant sources such as environment files, config, source, logs, CI definitions, container layers, Git history, and build artifacts, within the approved scope.

- Never reproduce secret values. Identify type, location, and exposure context with redaction.
- Distinguish public client identifiers from privileged secrets.
- If a real credential appears exposed, recommend revocation/rotation and assess where it may have propagated.
- Review key storage, access control, rotation, expiry, and environment separation.
- Review cryptographic algorithms, modes, randomness, password hashing, and TLS configuration.
- Avoid custom cryptography.
- Do not attempt to use discovered credentials.
- Do not copy secrets into generated reports, fixtures, logs, or tool arguments.
