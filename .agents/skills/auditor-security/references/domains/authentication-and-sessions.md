# Authentication and session security

Review:
- Password hashing algorithms and parameters; avoid plaintext or reversible password storage.
- Login throttling, credential stuffing controls, and account enumeration.
- MFA enrollment, recovery, reset, and bypass paths.
- Password reset token entropy, expiry, single use, and binding to the correct account.
- Session identifier rotation after authentication and privilege changes.
- Cookie `Secure`, `HttpOnly`, and appropriate `SameSite` settings.
- Token signature, algorithm allowlisting, issuer, audience, expiry, not-before, and key rotation.
- Refresh-token rotation, reuse detection, revocation, and logout behavior.
- OAuth/OIDC state, nonce, PKCE, redirect URI validation, and scope minimization.
- CSRF protections for cookie-authenticated state-changing requests.

Do not assume JWT is inherently safer than server-side sessions. Evaluate the actual lifecycle and threat model.
