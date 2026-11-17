# API security

Assess API endpoints as independent attack surfaces.

- Inventory routes, methods, authentication requirements, and sensitive operations.
- Test object-level authorization (BOLA), function-level authorization (BFLA), and property-level authorization (BOPLA).
- Validate input schemas, content types, pagination, filtering, sorting, and mass assignment.
- Review rate limits, quotas, resource consumption, and enumeration resistance.
- Review OAuth redirect URIs, state, PKCE, scopes, token audience/issuer/expiry, and token storage.
- Review webhook signature verification, timestamp/replay controls, and idempotency.
- GraphQL: resolver-level authorization, depth/complexity limits, batching, introspection exposure in context.
- WebSockets: handshake authentication, origin checks, per-message authorization, and resource limits.
- Ensure errors do not disclose secrets or internal implementation details.
- CORS is not authentication or authorization.

Use two test identities and controlled records only when explicitly authorized. Never access another user's real data to demonstrate a finding.
