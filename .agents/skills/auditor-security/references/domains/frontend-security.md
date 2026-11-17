# Frontend security

Review browser-executed code and the boundary between client and server.

- XSS: React/Vue/Angular escaping defaults, dangerous HTML APIs, DOM sinks, URL contexts, template compilation, third-party scripts.
- Client-side secrets: distinguish public build-time variables from server-only secrets; anything shipped to the browser is public.
- Authentication: token storage, cookie attributes, refresh flows, logout, cross-tab behavior, and XSS impact.
- Authorization: never rely on hidden controls or client-side route guards as the sole enforcement.
- CSP and browser policies: evaluate effective headers and nonce/hash strategy in context.
- Open redirects and unsafe URL handling.
- postMessage: validate origin, source, and message schema.
- Dependency and supply-chain risks: scripts, packages, build plugins, lockfiles.
- Source maps and debug artifacts: assess actual exposure and sensitivity.
- CORS is enforced by browsers but is not a substitute for server authorization.

Do not report a public frontend configuration value as a secret merely because its name contains `KEY`; determine whether it is intended to be public and whether it grants privileged access.
