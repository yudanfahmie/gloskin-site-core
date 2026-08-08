# Plugin Workspace

This directory is intentionally empty of production implementation during the setup phase.

The future implementation developer should place the WordPress plugin under `plugin/gloskin-site-core/`, unless the final build/release tooling clearly benefits from moving the plugin directory to repository root.

Before importing any Morgen code:

1. read `../docs/implementation-plan.md`;
2. read `../docs/prune-matrix.csv`;
3. confirm `main` is current;
4. pin the Morgen source commit documented in the plan;
5. inspect V6 dependencies before copying files.

Do not copy the complete Morgen plugin wholesale and treat that as finished Gloskin code. Import the V6-capable dependency set into a controlled working area, replace retired route/data registries, remove legacy domains safely, rename public namespaces, and only then promote the production files into the Gloskin plugin directory.
