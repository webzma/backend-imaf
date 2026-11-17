# Business logic and data protection

Review application-specific workflows rather than relying only on generic vulnerability patterns.

- Price, quantity, discount, role, ownership, and status values must be validated server-side.
- Check workflow transitions and whether steps can be skipped or repeated.
- Check replay, race conditions, duplicate submissions, and idempotency.
- Verify authorization for exports, bulk actions, administrative functions, and background jobs.
- Assess data minimization, retention, access, encryption, and cache behavior.
- Prevent sensitive data from entering URLs, analytics, logs, or public error messages.
- Review tenant isolation and cross-account data access.
- Evaluate rate limits and abuse controls around costly or sensitive operations.

Do not infer legal compliance from code inspection alone. State the specific technical observation and the limits of the assessment.
