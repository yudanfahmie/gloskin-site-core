# Contribution Rules

These rules are mandatory for AI agents and human developers working in this repository.

## Branch policy

- Work directly on `main`.
- Do not create feature branches, work branches, temporary branches, or pull requests unless the repository owner explicitly changes this policy.
- Never use a branch as a scratch area.

## Before editing

1. Confirm the repository is `yudanfahmie/gloskin-site-core`.
2. Checkout `main`.
3. Pull the latest `origin/main`.
4. Record and report the current HEAD SHA.
5. Inspect the current implementation before changing it.
6. Read `docs/implementation-plan.md`, `docs/page-matrix.csv`, and `docs/prune-matrix.csv` when the task touches architecture or cloning.

## Commit policy

Commits must be intentional and economical.

- Group files that implement one coherent outcome into one commit.
- Do not create one commit per file.
- Do not create temporary, probe, checkpoint, or cleanup-only commits that can be avoided.
- Keep commit messages short, lowercase, and action-oriented.
- Prefer messages such as `define gloskin workspace`, `adapt v6 shell`, `build clinic templates`, or `remove morgen legacy modules`.
- Avoid noisy prefixes, ticket prose, sentence-length messages, and title case unless explicitly required.
- If a task naturally contains independent production outcomes, use the smallest reasonable number of commits rather than forcing unrelated work into one commit.

## Change discipline

- Make only changes required by the current task.
- Keep existing working functionality unless the plan explicitly retires it.
- Do not add dependencies or frameworks without a demonstrated need.
- Do not add or change GitHub Actions merely as a workaround or probe.
- Do not carry raw source documents from `project-9901` into this repository. That repository is reference-only.
- Do not modify `project-9901` while implementing Gloskin.

## Verification before push

1. Review the complete diff.
2. Confirm production files—not only documentation or temporary files—changed when the task is an implementation task.
3. Run existing checks available in the environment.
4. Check for accidental secrets, raw client files, generated archives, and debug artifacts.
5. Commit the coherent change set.
6. Push to `origin/main`.
7. Verify remote `main` points to the pushed commit.
8. Inspect the final commit with `git show --stat HEAD` or an equivalent repository view.

Do not claim completion when changes exist only locally or when the push failed.
