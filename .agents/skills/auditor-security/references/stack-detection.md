# Stack and version detection

## Evidence sources

Use multiple sources where possible:
- Package manifests and lockfiles.
- Framework configuration files.
- Runtime/version files and CI definitions.
- Source imports, route definitions, and application bootstrap.
- Container files and deployment manifests.
- Database drivers, ORM configuration, and migration files.
- Build scripts and infrastructure-as-code.

Do not infer exact versions from imports alone. Lockfiles and runtime manifests are stronger evidence, but may not represent deployed versions.

## Coverage tiers

- Tier 1 — Verified: dedicated rules/procedures tested against representative fixtures or projects.
- Tier 2 — Supported: framework-specific guidance exists; automation or testing is partial.
- Tier 3 — Best effort: generic analysis, no specialized coverage promise.

Record version confidence and coverage tier per major technology. Unknown or unsupported stacks remain Tier 3.

## Ecosystem discovery

Potential ecosystems include:
- JavaScript/TypeScript: Node.js, React, Next.js, Vue, Nuxt, Angular, Express, NestJS.
- PHP: Laravel, Symfony, Composer applications.
- Python: Django, Flask, FastAPI.
- Java/Kotlin: Spring and JVM services.
- .NET: ASP.NET Core and related services.
- Ruby: Rails and Rack applications.
- Go: standard library and common HTTP frameworks.
- Data: PostgreSQL, MySQL/MariaDB, SQL Server, MongoDB, Redis.
- Infrastructure: Docker, Kubernetes, Nginx, Apache, Terraform, cloud services, CI/CD.

This list is not a promise of Tier 1 coverage. Load framework-specific guidance only when it exists and matches the detected version.
