from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
refresh = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css").read_text(encoding="utf-8")
loader = (ROOT / "plugin/gloskin-site-core/assets/css/gloskin-ui1-loader-system.css").read_text(encoding="utf-8")
shell = (ROOT / "plugin/gloskin-site-core/templates/shell.php").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise SystemExit(f"FAIL: {message}")


media = refresh.split(".gloskin-ui1-hero .gloskin-ui1-hero__media{", 1)[1].split("}", 1)[0]
image = refresh.split(".gloskin-ui1-hero .gloskin-ui1-hero__image{", 1)[1].split("}", 1)[0]
require("position:relative" in media, "hero media must establish the containing block")
require(".gloskin-ui1-hero .gloskin-ui1-hero__media-fallback{position:absolute;inset:0}" in refresh,
        "hero fallback must fill the complete media frame")
require("display:block" in image and "width:100%" in image and "height:100%" in image,
        "hero image must fill both frame axes")
require("object-fit:cover" in image and "object-fit:contain" not in image,
        "hero image must crop with cover and never create contain ghost space")
require("--gl-transition-bg:transparent" in loader,
        "page-transition backdrop must be transparent")
require("background:var(--gl-transition-bg)" in loader,
        "page-transition root must consume the transparent token")
require(".gloskin-ui1-page-transition__g path{fill:#fff}" in loader and 'fill="#fff"' in shell,
        "transition G must remain pure white")

print("diagnostic-presentation-contract.py: OK")
