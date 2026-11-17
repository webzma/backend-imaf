# Injection and output encoding

Trace untrusted input to interpreters and sensitive sinks.

- SQL/NoSQL: parameterized queries; allowlist identifiers when dynamic identifiers are unavoidable.
- Shell/command: avoid shell interpolation; use safe APIs and argument arrays.
- HTML/DOM: context-aware output encoding; review dangerous DOM APIs and sanitization.
- Templates: distinguish data from template source; review SSTI paths.
- LDAP, XPath, XML, and parser-specific injection.
- Deserialization: restrict types and trust boundaries; validate signed data.
- Prototype pollution and unsafe object merges.
- URL contexts: validate scheme and destination; HTML escaping alone does not make `javascript:` safe.
- CSS and style contexts: assess injection and data exfiltration implications.

Do not recommend one universal escaping function. Correct encoding depends on the output context and framework.
