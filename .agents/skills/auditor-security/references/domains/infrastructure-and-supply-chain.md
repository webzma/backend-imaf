# Infrastructure and software supply chain

Review only technologies actually present.

- Docker: minimal base images, non-root users, pinned images, secrets, capabilities, filesystem permissions, exposed ports.
- Kubernetes: RBAC, service accounts, network policies, pod security, secrets, admission controls, image provenance.
- CI/CD: least-privilege tokens, workflow permissions, untrusted pull request execution, artifact integrity, secret exposure, dependency pinning.
- IaC/cloud: IAM least privilege, public storage, network exposure, encryption, logging, secret management.
- Reverse proxies/web servers: TLS, trusted proxy headers, request limits, path normalization, debug/admin exposure.
- Dependencies: lockfiles, known advisories, reachability/context, transitive dependencies, abandoned packages.
- Build provenance: consider SLSA-aligned controls where appropriate.
- TLS: protocol/cipher configuration and certificate lifecycle.

Do not claim an infrastructure setting is effective in production without inspecting the deployed configuration or authorized evidence.
