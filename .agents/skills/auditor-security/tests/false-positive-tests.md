# False-positive resistance tests

- Public frontend environment variable intended for browser use is not automatically a secret.
- A security header absent from a source config but added by an upstream proxy is not a confirmed missing-header finding without effective-response evidence.
- A scanner alert on an unreachable or test-only code path is not automatically exploitable.
- A dependency advisory for an unused optional feature is not automatically a confirmed application vulnerability.
- A route without local middleware may still be protected by a verified global middleware or service policy; trace the complete path.
- An input validation function is not automatically safe for every output context; evaluate the sink.
- A CSP `unsafe-inline` style allowance is not equivalent to allowing inline scripts.
