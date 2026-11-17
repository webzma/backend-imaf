# Agent compatibility tests

Test the same skill directory without duplicating its content.

For each agent (Codex, Claude Code, OpenCode):
1. Confirm discovery from its documented project-level skill path.
2. Confirm frontmatter parsing and activation behavior.
3. Confirm the agent can read referenced Markdown files.
4. Confirm relative paths resolve from the skill directory.
5. Confirm it follows read-only audit and individual remediation approval.
6. Record agent name, version, date, test project, and result.

Do not label an agent compatible or verified until these tests have been run. Agent-specific installation/discovery rules may change; check official documentation.
