# Backend security

Review server-side trust boundaries and enforcement.

- Authentication middleware and identity validation.
- Authorization at route, controller, service, resolver, and data-access boundaries.
- Object ownership, tenant isolation, role/permission checks, and alternate access paths.
- Parameterized database access and safe dynamic query construction.
- Input validation, output encoding, deserialization, and mass assignment.
- Session/token lifecycle, CSRF protections, cookie attributes, and logout invalidation.
- File uploads, filesystem paths, temporary files, and generated filenames.
- SSRF and outbound request controls.
- Rate limiting, resource consumption, pagination, and abuse controls.
- Error handling, debug endpoints, stack traces, and sensitive logging.
- Background jobs, queues, scheduled tasks, and message authorization.
- Security-relevant business logic, replay, race conditions, and idempotency.

Framework middleware or policies must be traced to the actual route and execution path. Their presence elsewhere in the project is not proof that a sensitive endpoint is protected.
