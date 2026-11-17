# HTTP security headers and browser policies

Inspect effective responses in the relevant environment where authorized. Source configuration alone may not reflect deployed behavior.

Evaluate:
- Content-Security-Policy: directives, nonce/hash strategy, `object-src`, `base-uri`, `frame-ancestors`, and unsafe allowances.
- Strict-Transport-Security: HTTPS deployment and subdomain implications.
- X-Content-Type-Options.
- Referrer-Policy.
- Permissions-Policy.
- CORS: explicit origins, credentials, methods, headers, and preflight behavior.
- Cross-origin isolation policies where applicable.
- Clickjacking protection using CSP `frame-ancestors`; consider legacy `X-Frame-Options` compatibility.

A missing header is not automatically a vulnerability. `unsafe-inline` has different implications for scripts and styles. A broad `connect-src` should be assessed against actual application needs. Avoid copy-paste policies that break functionality or create a false sense of security.
