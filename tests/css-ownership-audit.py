#!/usr/bin/env python3
"""Read-only public CSS cascade collision inventory.

The parser is deliberately dependency-free so the contract can run in the
repository's minimal release environment. It understands nested at-rules,
selector lists, declaration order, media context, and common shorthand groups.
"""
from __future__ import annotations

import argparse
import json
import re
from collections import defaultdict
from pathlib import Path
from typing import Iterator

ROOT = Path(__file__).resolve().parents[1]
CSS_ROOT = ROOT / "plugin/gloskin-site-core/assets/css"

PUBLIC_STYLES = [
    ("gloskin-ui1-fonts", "gloskin-ui1-fonts.css", "global", []),
    ("gloskin-ui1-core-base", "gloskin-ui1-core-base.css", "global", ["gloskin-ui1-fonts"]),
    ("gloskin-ui1-core", "gloskin-ui1-core.css", "global", ["gloskin-ui1-core-base"]),
    ("gloskin-ui1-single-product-geometry", "gloskin-ui1-single-product-geometry.css", "global", ["gloskin-ui1-core"]),
    ("gloskin-ui1-readiness", "gloskin-ui1-readiness.css", "global", ["gloskin-ui1-single-product-geometry"]),
    ("gloskin-ui1-production", "gloskin-ui1-production.css", "global", ["gloskin-ui1-readiness", "registered Woo styles"]),
    ("gloskin-ui1-quickadd-polish", "gloskin-ui1-quickadd-polish.css", "global", ["gloskin-ui1-production"]),
    ("gloskin-ui1-commerce-polish", "gloskin-ui1-commerce-polish.css", "global", ["gloskin-ui1-quickadd-polish"]),
    ("gloskin-ui1-loader-system", "gloskin-ui1-loader-system.css", "global", ["gloskin-ui1-commerce-polish"]),
    ("gloskin-ui1-brand-purchase-polish", "gloskin-ui1-brand-purchase-polish.css", "global", ["gloskin-ui1-loader-system"]),
    ("gloskin-ui1-editorial", "gloskin-ui1-editorial.css", "global", ["gloskin-ui1-brand-purchase-polish"]),
    ("gloskin-ui1-product-grid", "gloskin-ui1-product-grid.css", "global", ["gloskin-ui1-editorial"]),
    ("gloskin-ui1-prototype-refresh", "gloskin-ui1-prototype-refresh.css", "global", ["gloskin-ui1-product-grid"]),
    ("gloskin-ui1-consultation", "gloskin-ui1-consultation.css", "Treatments Hub only", ["gloskin-ui1-prototype-refresh"]),
    ("gloskin-ui1-shop-discovery", "gloskin-ui1-shop-discovery.css", "Shop only", ["gloskin-ui1-prototype-refresh"]),
]

BASELINE_SUMMARY = {
    "declarations": 7794,
    "exact_conflicts": 232,
    "practical_conflicts": 258,
    "identical_cross_file_duplicates": 110,
    "important_declarations": 42,
}

OWNERSHIP_MAP = {
    "tokens_and_foundations": "gloskin-ui1-core-base.css",
    "structural_interactions": "gloskin-ui1-core.css",
    "pdp_geometry": "gloskin-ui1-single-product-geometry.css",
    "navigation_typography_and_footer_geometry": "gloskin-ui1-production.css",
    "quick_add": "gloskin-ui1-quickadd-polish.css",
    "shared_loader": "gloskin-ui1-loader-system.css",
    "purchase_brand_skin": "gloskin-ui1-brand-purchase-polish.css",
    "product_grid": "gloskin-ui1-product-grid.css",
    "shared_cards_sections_and_body_copy": "gloskin-ui1-prototype-refresh.css",
    "treatments_consultation": "gloskin-ui1-consultation.css",
    "shop_discovery": "gloskin-ui1-shop-discovery.css",
}

SHORTHAND_GROUPS = {
    "margin": {"margin", "margin-top", "margin-right", "margin-bottom", "margin-left", "margin-inline", "margin-block"},
    "padding": {"padding", "padding-top", "padding-right", "padding-bottom", "padding-left", "padding-inline", "padding-block"},
    "border": {"border", "border-top", "border-right", "border-bottom", "border-left", "border-width", "border-style", "border-color", "border-block", "border-inline"},
    "background": {"background", "background-color", "background-image", "background-position", "background-size"},
    "font": {"font", "font-family", "font-size", "font-style", "font-weight", "line-height"},
    "grid": {"grid", "grid-template", "grid-template-columns", "grid-template-rows", "grid-column", "grid-row"},
    "flex": {"flex", "flex-basis", "flex-grow", "flex-shrink"},
    "size": {"width", "min-width", "max-width", "height", "min-height", "max-height"},
    "overflow": {"overflow", "overflow-x", "overflow-y"},
}

BOX_SIDES = {"top", "right", "bottom", "left"}


def property_coverage(prop: str) -> set[str]:
    """Return the computed sub-properties a declaration can reset.

    This keeps the practical audit useful: ``margin`` really collides with
    ``margin-top``, while unrelated longhands such as ``font-size`` and
    ``font-weight`` do not collide merely because both belong to typography.
    """
    if prop in {"margin", "padding"}:
        return {f"{prop}-{side}" for side in BOX_SIDES}
    for root in ("margin", "padding"):
        if prop == f"{root}-inline":
            return {f"{root}-left", f"{root}-right"}
        if prop == f"{root}-block":
            return {f"{root}-top", f"{root}-bottom"}
    if prop == "background":
        return {f"background-{part}" for part in ("color", "image", "position", "size", "repeat", "attachment", "origin", "clip")}
    if prop == "font":
        return {f"font-{part}" for part in ("family", "size", "style", "weight", "stretch")} | {"line-height"}
    if prop == "border":
        return {f"border-{side}-{part}" for side in BOX_SIDES for part in ("width", "style", "color")}
    if prop in {"border-width", "border-style", "border-color"}:
        part = prop.removeprefix("border-")
        return {f"border-{side}-{part}" for side in BOX_SIDES}
    match = re.fullmatch(r"border-(top|right|bottom|left)", prop)
    if match:
        return {f"border-{match.group(1)}-{part}" for part in ("width", "style", "color")}
    if prop == "grid":
        return {"grid-template-columns", "grid-template-rows", "grid-auto-flow", "grid-auto-columns", "grid-auto-rows", "grid-column", "grid-row"}
    if prop == "grid-template":
        return {"grid-template-columns", "grid-template-rows"}
    if prop == "flex":
        return {"flex-basis", "flex-grow", "flex-shrink"}
    if prop == "overflow":
        return {"overflow-x", "overflow-y"}
    return {prop}


def strip_comments(source: str) -> str:
    return re.sub(r"/\*.*?\*/", lambda match: "\n" * match.group(0).count("\n"), source, flags=re.S)


def matching_brace(source: str, start: int) -> int:
    depth = 0
    quote = ""
    escape = False
    for index in range(start, len(source)):
        char = source[index]
        if quote:
            if escape:
                escape = False
            elif char == "\\":
                escape = True
            elif char == quote:
                quote = ""
            continue
        if char in "\"'":
            quote = char
        elif char == "{":
            depth += 1
        elif char == "}":
            depth -= 1
            if depth == 0:
                return index
    raise ValueError("unbalanced CSS braces")


def split_top_level(source: str, delimiter: str) -> list[str]:
    parts: list[str] = []
    start = 0
    round_depth = square_depth = 0
    quote = ""
    escape = False
    for index, char in enumerate(source):
        if quote:
            if escape:
                escape = False
            elif char == "\\":
                escape = True
            elif char == quote:
                quote = ""
            continue
        if char in "\"'": quote = char
        elif char == "(": round_depth += 1
        elif char == ")": round_depth = max(0, round_depth - 1)
        elif char == "[": square_depth += 1
        elif char == "]": square_depth = max(0, square_depth - 1)
        elif char == delimiter and not round_depth and not square_depth:
            parts.append(source[start:index])
            start = index + 1
    parts.append(source[start:])
    return parts


def normalize_space(value: str) -> str:
    value = re.sub(r"\s+", " ", value.strip())
    return re.sub(r"\s*([>+~,:])\s*", r"\1", value)


def specificity(selector: str) -> tuple[int, int, int]:
    cleaned = re.sub(r":where\([^)]*\)", "", selector)
    ids = len(re.findall(r"#[\w-]+", cleaned))
    classes = len(re.findall(r"\.[\w-]+|\[[^]]+\]|:(?!:)[\w-]+", cleaned))
    elements = len(re.findall(r"(?:^|[\s>+~,(])(?:[a-zA-Z][\w-]*|::[\w-]+)", cleaned))
    return ids, classes, elements


def declarations(body: str) -> Iterator[tuple[str, str]]:
    for raw in split_top_level(body, ";"):
        if ":" not in raw:
            continue
        prop, value = raw.split(":", 1)
        prop = prop.strip().lower()
        value = normalize_space(value)
        if re.match(r"^(?:--[\w-]+|[a-z-]+)$", prop) and value:
            yield prop, value


def rules(source: str, context: tuple[str, ...] = (), offset: int = 0) -> Iterator[dict]:
    cursor = 0
    while cursor < len(source):
        brace = source.find("{", cursor)
        if brace < 0:
            return
        prelude = source[cursor:brace].strip()
        close = matching_brace(source, brace)
        body = source[brace + 1:close]
        absolute = offset + brace
        if prelude.startswith("@"):
            name = prelude.split(None, 1)[0].lower()
            if name in {"@media", "@supports", "@container", "@layer"}:
                nested_context = context + (normalize_space(prelude),)
                yield from rules(body, nested_context, offset + brace + 1)
        else:
            for selector in split_top_level(prelude, ","):
                selector = normalize_space(selector)
                if not selector:
                    continue
                for prop, value in declarations(body):
                    yield {
                        "selector": selector,
                        "property": prop,
                        "value": value,
                        "context": " | ".join(context) or "global",
                        "specificity": specificity(selector),
                        "offset": absolute,
                    }
        cursor = close + 1


def shorthand_group(prop: str) -> str:
    for group, properties in SHORTHAND_GROUPS.items():
        if prop in properties:
            return group
    return prop


def build_report() -> dict:
    records = []
    stylesheets = []
    for order, (handle, filename, condition, deps) in enumerate(PUBLIC_STYLES):
        path = CSS_ROOT / filename
        source = path.read_text(encoding="utf-8")
        stylesheets.append({"order": order, "handle": handle, "source": str(path.relative_to(ROOT)), "deps": deps, "condition": condition})
        for item in rules(strip_comments(source)):
            item.update({"order": order, "handle": handle, "source": filename, "line": source.count("\n", 0, item.pop("offset")) + 1})
            records.append(item)

    exact = defaultdict(list)
    practical = defaultdict(list)
    for item in records:
        exact[(item["selector"], item["property"], item["context"])].append(item)
        practical[(item["selector"], shorthand_group(item["property"]), item["context"])].append(item)

    def conflicting(groups: dict, require_overlap: bool = False) -> list[dict]:
        result = []
        for key, owners in groups.items():
            files = {owner["source"] for owner in owners}
            values = {(owner["property"], owner["value"]) for owner in owners}
            if len(files) < 2 or len(values) < 2:
                continue
            if require_overlap and not any(
                left["source"] != right["source"]
                and left["value"] != right["value"]
                and property_coverage(left["property"]) & property_coverage(right["property"])
                for index, left in enumerate(owners)
                for right in owners[index + 1:]
            ):
                continue
            result.append({"key": list(key), "owners": owners})
        return sorted(result, key=lambda row: tuple(row["key"]))

    exact_conflicts = conflicting(exact)
    practical_conflicts = conflicting(practical, require_overlap=True)
    duplicate_rules = [
        {"key": list(key), "owners": owners}
        for key, owners in exact.items()
        if len({owner["source"] for owner in owners}) > 1 and len({owner["value"] for owner in owners}) == 1
    ]
    important = [item for item in records if "!important" in item["value"]]
    for item in important:
        item["allowlist_reason"] = allowed_important_reason(item)
    summary = {
        "stylesheets": len(stylesheets),
        "declarations": len(records),
        "exact_conflicts": len(exact_conflicts),
        "practical_conflicts": len(practical_conflicts),
        "identical_cross_file_duplicates": len(duplicate_rules),
        "important_declarations": len(important),
    }
    return {
        "stylesheets": stylesheets,
        "ownership_map": OWNERSHIP_MAP,
        "baseline_summary": BASELINE_SUMMARY,
        "summary": summary,
        "reduction": {key: BASELINE_SUMMARY[key] - summary[key] for key in BASELINE_SUMMARY},
        "exact_conflicts": exact_conflicts,
        "practical_conflicts": practical_conflicts,
        "identical_cross_file_duplicates": duplicate_rules,
        "important_declarations": important,
    }


def allowed_important_reason(item: dict) -> str | None:
    if (
        item["source"] == "gloskin-ui1-core-base.css"
        and item["selector"] == ".gloskin-ui1 .screen-reader-text"
        and item["property"] == "position"
    ):
        return "WordPress accessibility utility must beat theme/plugin display helpers."
    if (
        item["source"] == "gloskin-ui1-core.css"
        and item["selector"] == ".gloskin-ui1 .select2-container"
        and item["property"] == "width"
    ):
        return "Select2 writes an inline width; the scoped form adapter must retain fluid width."
    if (
        item["source"] == "gloskin-ui1-core-base.css"
        and item["context"] == "@media (prefers-reduced-motion:reduce)"
        and item["selector"] in {".gloskin-ui1 *", ".gloskin-ui1 *::before", ".gloskin-ui1 *::after"}
        and item["property"] in {"scroll-behavior", "animation-duration", "animation-iteration-count", "transition-duration"}
    ):
        return "Global accessibility override must suppress motion regardless of component specificity."
    return None


def run_contract(report: dict) -> list[str]:
    errors: list[str] = []
    summary = report["summary"]
    for key in ("exact_conflicts", "practical_conflicts", "identical_cross_file_duplicates"):
        if summary[key] != 0:
            errors.append(f"{key} must stay at zero (found {summary[key]})")

    handles = [style["handle"] for style in report["stylesheets"]]
    sources = [style["source"] for style in report["stylesheets"]]
    if len(handles) != len(set(handles)):
        errors.append("public CSS handle registry contains duplicate handles")
    if len(sources) != len(set(sources)):
        errors.append("public CSS handle registry contains duplicate sources")

    unapproved = [item for item in report["important_declarations"] if allowed_important_reason(item) is None]
    if unapproved:
        errors.append(f"unapproved !important declarations found: {len(unapproved)}")
    if len(report["important_declarations"]) != 14:
        errors.append("documented !important allowlist changed; review the accessibility/integration boundary")

    # Reparse because a clean report intentionally contains no collision owners.
    records = []
    for order, (_, filename, _, _) in enumerate(PUBLIC_STYLES):
        source = (CSS_ROOT / filename).read_text(encoding="utf-8")
        for item in rules(strip_comments(source)):
            item.update({"order": order, "source": filename})
            records.append(item)

    section_owners = {item["source"] for item in records if "gloskin-ui1-section-heading" in item["selector"]}
    if section_owners != {"gloskin-ui1-prototype-refresh.css"}:
        errors.append(f"section-heading cascade owner drifted: {sorted(section_owners)}")
    nav_weights = [item for item in records if item["selector"] == ".gloskin-ui1-nav__link" and item["property"] == "font-weight"]
    if len(nav_weights) != 1 or nav_weights[0]["source"] != "gloskin-ui1-production.css" or nav_weights[0]["value"] != "400":
        errors.append("navigation link weight must have one production owner at 400")

    prototype = (CSS_ROOT / "gloskin-ui1-prototype-refresh.css").read_text(encoding="utf-8")
    required_fragments = (
        ".gloskin-ui1-section-heading{\n\tdisplay:grid;\n\tgrid-template-columns:repeat(2,minmax(0,1fr));",
        ".gloskin-ui1-section-heading p{max-width:40ch;margin:0 0 0 auto;justify-self:end;text-align:right;",
        "@media (max-width:1040px)",
        ".gloskin-ui1-section-heading{grid-template-columns:1fr;gap:14px}",
        ".gloskin-ui1-section-heading p{max-width:42ch;justify-self:start;text-align:left;margin:0}",
    )
    for fragment in required_fragments:
        if fragment not in prototype:
            errors.append(f"section-heading responsive contract missing: {fragment[:55]}")

    trait = (ROOT / "plugin/gloskin-site-core/includes/gloskin-site-core-shop-discovery-route-trait.php").read_text(encoding="utf-8")
    if "array( 'gloskin-ui1-prototype-refresh' )" not in trait:
        errors.append("Shop discovery stylesheet must depend on the final shared prototype layer")

    assets = (ROOT / "plugin/gloskin-site-core/config/assets.php").read_text(encoding="utf-8")
    registered = PUBLIC_STYLES[:-1]
    for handle, filename, _, deps in registered:
        marker = f"'{handle}' => array("
        if marker not in assets:
            errors.append(f"public CSS handle missing from registry: {handle}")
            continue
        block = assets.split(marker, 1)[1].split("),", 1)[0]
        if f"'assets/css/{filename}'" not in block:
            errors.append(f"public CSS source drifted for {handle}")
        for dependency in (dep for dep in deps if dep.startswith("gloskin-")):
            if f"'{dependency}'" not in block:
                errors.append(f"dependency {dependency} missing from {handle}")
    asset_service = (ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-asset-service.php").read_text(encoding="utf-8")
    if "wp_register_style( $handle, $src, $deps, $this->version" not in asset_service:
        errors.append("registered public CSS must derive cache version from Kernel")
    if "const CONDITIONAL_HANDLES = array( 'gloskin-ui1-consultation'" not in asset_service:
        errors.append("consultation style must remain conditional")
    if "plugins_url( 'assets/css/gloskin-ui1-shop-discovery.css'" not in trait or ", $version );" not in trait:
        errors.append("Shop CSS must use the route's synchronized Kernel version")
    return errors


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--report", type=Path)
    parser.add_argument("--summary", action="store_true")
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()
    report = build_report()
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    if args.summary or not args.report:
        print(json.dumps(report["summary"], sort_keys=True))
    if args.check:
        errors = run_contract(report)
        if errors:
            raise SystemExit("css-ownership-audit: FAIL\n- " + "\n- ".join(errors))
        print("css-ownership-audit: OK")


if __name__ == "__main__":
    main()
