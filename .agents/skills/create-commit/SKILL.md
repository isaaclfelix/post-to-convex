---
name: create-commit
description: >-
    Create git commits for this repository using Conventional Commits and
    Commitlint. Use when the user asks to commit, save work to git, write a commit
    message, or stage and commit changes.
---

# Create Commit

Create commits **only when the user explicitly asks** (e.g. "commit this", "make a commit"). If unclear, ask first.

## Message format

Every commit has a **subject** (first line). Most commits also include a **body** (blank line, then bullet list). **Very small changes** may use a **subject-only** message when the subject fully describes the diff.

**Default (multi-change or non-trivial):**

```
type(optional-scope): imperative subject under 100 chars

- First change summarized in plain language
- Second change
- Third change
```

**Subject-only (very small changes):**

```
fix(ui): correct nav overflow on mobile
```

### Subject line

-   Pattern: `type(optional-scope): subject` (see [CONTRIBUTING.md](../../../CONTRIBUTING.md))
-   **Types** (lowercase only): `build`, `chore`, `ci`, `docs`, `feat`, `fix`, `perf`, `refactor`, `revert`, `style`, `test`
-   **Scope** optional in parentheses: `feat(blog): add category archives`
-   **Subject**: imperative mood, lowercase start, no trailing period, max **100** characters (Commitlint `header-max-length`)
-   **Branch vs commit**: branches use `feat/add-login`; commits use `feat: add login`

Pick the type that matches intent: `feat` = user-facing behavior, `fix` = bug, `refactor` = no intended behavior change, `chore` = tooling/deps, etc.

### Body

-   **Default**: blank line after subject, then a **bullet list** (`- `) summarizing each logical change in the commit
-   **Skip the body** when the change is very small and the subject already says everything (e.g. one typo, a single obvious fix, a one-line config tweak, formatting one file). Do not add bullets that only restate the subject.
-   One bullet per distinct change area (file group, feature slice, or behavior change)
-   Imperative or past-tense phrases are fine; be specific (what and why), not file dumps
-   Wrap lines at **100** characters (`body-max-line-length`)
-   Do not repeat the subject verbatim in the first bullet

### Footer (optional)

-   `BREAKING CHANGE: description` when the commit breaks consumers
-   `Refs #123` only if the user or team process uses ticket IDs

## Workflow

### 1. Inspect changes (parallel)

```bash
git status
git diff
git diff --staged
git log -10 --format="%s%n%n%b---"
```

Read staged and unstaged diffs. If nothing is staged, stage only files that belong to this commit (never secrets: `.env`, credentials, keys).

### 2. Draft the message

-   Subject: one clear outcome of the commit
-   **Subject-only** if the diff is very small and self-explanatory; otherwise add a body
-   Body bullets: 2–8 items typical; merge tiny edits into one bullet; split unrelated work into separate commits if the user agrees

**Subject-only example:**

```
docs: link contributing guide
```

**Body example** (from this repo):

```
refactor(convex): adopt ConvexError and null query results for post/taxonomy sync

- Add mutationErrorResponse helper for HTTP endpoints to map ConvexError to JSON
- Replace returning Error from queries with null; update pages to use !post checks
- Use throw ConvexError in post mutations for duplicate originalId and permalink failures
- Standardize category/tag HTTP endpoints on mutationErrorResponse
```

### 3. Validate (optional but recommended)

Pipe the message through Commitlint. For subject-only commits, pipe just the subject line.

```bash
# Bash / Git Bash — subject only
printf '%s\n' "fix(ui): correct nav overflow" | pnpm dlx commitlint

# Bash / Git Bash — subject + body
printf '%s\n\n%s\n' "feat(scope): subject" "- bullet one" "- bullet two" | pnpm dlx commitlint

# PowerShell — subject only
"fix(ui): correct nav overflow" | pnpm dlx commitlint

# PowerShell — subject + body
@(
  "feat(scope): subject",
  "",
  "- bullet one",
  "- bullet two"
) -join "`n" | pnpm dlx commitlint
```

Fix any errors before committing.

### 4. Commit

**Git safety** (never violate):

-   Never update git config
-   Never `--no-verify` / skip hooks unless the user explicitly requests it
-   Never destructive commands (`push --force`, `reset --hard`) unless explicitly requested
-   Never force-push `main`/`master`; warn if asked
-   Avoid `git commit --amend` unless: user asked for amend, HEAD commit is yours in this session, and branch is not pushed
-   If a hook fails, fix the issue and make a **new** commit (do not amend a failed commit)
-   Do not push unless the user asks

**Stage and commit**

Subject-only:

```bash
git add <paths>
git commit -m "type(scope): subject line"
```

With body (Bash / Git Bash — preferred HEREDOC):

```bash
git add <paths>
git commit -m "$(cat <<'EOF'
type(scope): subject line

- Bullet summarizing change one
- Bullet summarizing change two

EOF
)"
```

With body (PowerShell — multiple `-m` flags; first is subject, rest are body paragraphs):

```powershell
git add <paths>
git commit -m "type(scope): subject line" -m "- Bullet one" -m "- Bullet two"
```

Or write the message to a temp file and use `git commit -F path`.

### 5. Verify

```bash
git status
git log -1 --format=full
```

If **pre-commit** (lint-staged) reformats files, stage those changes and commit again (new commit or amend only per amend rules above).

## Type selection guide

| Type       | Use when                                          |
| ---------- | ------------------------------------------------- |
| `feat`     | New behavior users or API consumers notice        |
| `fix`      | Bug fix                                           |
| `docs`     | Documentation only                                |
| `refactor` | Restructure without intended behavior change      |
| `test`     | Tests only                                        |
| `chore`    | Deps, tooling, config, housekeeping               |
| `style`    | Formatting only (Prettier/ESLint style, no logic) |
| `perf`     | Performance improvement                           |
| `build`    | Build system or bundler                           |
| `ci`       | CI/CD config                                      |
| `revert`   | Reverting a prior commit                          |

**Scope hints** for this repo: `convex`, `blog`, `ui`, `api`, `auth` — use when it clarifies the area; omit if the change is broad.

## Hooks

-   **commit-msg**: Commitlint validates message format
-   **pre-commit**: lint-staged runs ESLint + Prettier on staged JS/TS and Prettier on JSON/MD/CSS

Emergency bypass (only if user explicitly requests): `$env:HUSKY=0; git commit` (PowerShell) or `HUSKY=0 git commit` (Bash). Prefer fixing the message instead.

## Checklist before committing

-   [ ] User explicitly requested a commit
-   [ ] No secrets or local-only env files staged
-   [ ] Subject passes Conventional Commits + Commitlint
-   [ ] Body included when the change is not very small; otherwise subject-only is enough
-   [ ] Type and scope match the diff
-   [ ] Unrelated changes split or called out to the user
