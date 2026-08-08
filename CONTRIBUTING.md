# Contribution Rules

These rules are mandatory for AI agents and human developers working in this repository.

## Branch policy

- Work directly on `main`.
- Do not create feature branches, work branches, temporary branches or pull requests unless the repository owner explicitly changes this policy.
- Never use a branch as a scratch area.

## Requirements authority

For normal Gloskin development, the canonical source is this repository.

Read in this order when relevant:

1. `docs/developer-source-of-truth.md`
2. `docs/content-data-contracts.md`
3. `docs/morgen-v6-reverse-engineering.md`
4. `docs/implementation-plan.md`
5. `docs/page-matrix.csv`
6. `docs/prune-matrix.csv`

`yudanfahmie/project-9901` is provenance/raw reference only. Do not modify it, copy its raw files here, or make routine implementation dependent on re-reading it. If a value is not captured in the canonical Gloskin docs, treat it as pending/new input rather than silently rediscovering raw project assumptions.

The pinned Morgen repository may be inspected when implementing an explicitly documented reuse/adaptation decision.

## Before editing

1. Confirm the repository is `yudanfahmie/gloskin-site-core`.
2. Checkout `main`.
3. Pull the latest `origin/main`.
4. Record and report the current HEAD SHA.
5. Inspect the current implementation before changing it.
6. Read the relevant canonical docs above.
7. Define the coherent outcome the commit is intended to deliver.

## Commit policy

Commits must be intentional and economical.

- Group files that implement one coherent outcome into one commit.
- Do not create one commit per file.
- Do not create temporary, probe, checkpoint or avoidable cleanup-only commits.
- Keep commit messages short, lowercase and action-oriented.
- Prefer messages such as `initialize gloskin core`, `build ui1 asset core`, `adapt v6 shell`, `build clinic pages`, `integrate woocommerce views`, or `remove morgen legacy`.
- Avoid sentence-length messages, title case and noisy ticket prose unless explicitly required.
- If a task naturally contains independent production outcomes, use the smallest reasonable number of commits; do not force unrelated changes together merely to reduce commit count.

## Change discipline

- Make only changes required by the current task and canonical architecture.
- Keep existing working Gloskin functionality unless the requirements explicitly change it.
- Do not add dependencies/frameworks without demonstrated need and owner approval when material.
- Do not add/change GitHub Actions merely as a workaround or probe.
- Do not wholesale-copy the Morgen plugin.
- Do not introduce Morgen historical migrations, repair state or compatibility aliases into a fresh Gloskin runtime.
- Do not duplicate WooCommerce product/cart/checkout/order/payment ownership.
- Do not introduce developer work that belongs to the explicitly excluded SEO/marketing/infrastructure scope.

## Documentation discipline

When implementation changes an architecture decision, content field, relationship, route or retained/pruned Morgen dependency, update the matching canonical documentation in the **same coherent commit**.

Do not let implementation knowledge live only in chat, commit messages or a developer's memory.

## Verification before push

1. Review the complete diff.
2. Confirm production files—not only documentation or temporary files—changed when the task is an implementation task.
3. Run existing checks available in the environment.
4. Check for accidental secrets, raw client files, generated archives and debug artifacts.
5. Static-check for accidental excluded Morgen dependencies when the task touches adaptation/removal.
6. Commit the coherent change set.
7. Push directly to `origin/main`.
8. Verify remote `main` points to the pushed commit.
9. Inspect the final commit with `git show --stat HEAD` or an equivalent repository view.

Do not claim completion when changes exist only locally or when the push failed.