# File upload, filesystem, and SSRF

## File handling
- Enforce size and count limits.
- Validate file type using content inspection where appropriate, not only extension or client MIME.
- Generate server-side filenames; prevent path traversal and overwrite.
- Store uploads outside executable web roots or use isolated object storage.
- Apply safe processing, scanning, and access controls appropriate to the file type.
- Consider archive bombs, parser vulnerabilities, polyglots, and metadata exposure.
- Serve downloads with safe content disposition and content type.

## SSRF
- Identify user-controlled URLs and server-side fetches.
- Restrict schemes, hosts, ports, and resolved IP ranges with robust allowlists.
- Revalidate redirects and DNS resolution; consider rebinding.
- Block loopback, link-local, private, and cloud metadata ranges where not required.
- Apply egress network controls and response size/time limits.
- Do not probe internal networks or metadata services without explicit authorization.
