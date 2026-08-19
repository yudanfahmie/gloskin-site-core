#!/usr/bin/env python3
"""Focused semi-full-width + all-published doctor directory regression contract."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def require(condition, message):
    if not condition:
        raise AssertionError(message)


base = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-core-base.css")
refresh = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-prototype-refresh.css")
core = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-core.css")
product_grid = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-product-grid.css")
production = read("plugin/gloskin-site-core/assets/css/gloskin-ui1-production.css")
template_service = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php")
doctors_template = read("plugin/gloskin-site-core/templates/pages/doctors.php")
about_template = read("plugin/gloskin-site-core/templates/pages/about.php")
content_service = read("plugin/gloskin-site-core/includes/class-gloskin-site-core-content-service.php")

# One canonical width token. The normal desktop owner is 1320px; existing
# wider breakpoints remain 1480/1680 and the editorial reading measure stays
# independently bounded.
require("--gloskin-container:1320px" in base, "canonical desktop container must be 1320px")
require("--gloskin-container:1180px" not in base, "old 1180px canonical container must be retired")
require("--gloskin-reading:760px" in base, "base editorial reading measure must remain 760px")
require(".gloskin-ui1-container--narrow{max-width:var(--gloskin-reading)}" in base, "narrow reading container must remain token-bound")
require("@media (min-width:1800px){:root{--gloskin-container:1480px" in base, "1800px wide-screen container behavior must remain")
require("@media (min-width:2300px){:root{--gloskin-container:1680px" in base, "2300px large-screen container behavior must remain")
require("--gloskin-ui1-content-max" not in refresh, "prototype layer must not keep a second container-width owner")
require("1180px" not in refresh, "prototype refresh/header must not hardcode competing 1180px width")
require(".gloskin-ui1 .gloskin-ui1-container{max-width:var(--gloskin-container)}" in refresh, "prototype container must consume canonical token")
require(".gloskin-ui1-header__inner{max-width:var(--gloskin-container);" in refresh, "Header V2 must consume canonical token")
require(".gloskin-ui1 .woocommerce{width:min(calc(100% - (2 * var(--gloskin-gutter))),var(--gloskin-container))" in base,
        "Woo wrapper must continue consuming shared container token")
require("max-width:980px" in production or "max-width:900px" in production,
        "My Account/commerce inner constraint must remain bounded after global widening")

# Header V2 geometric centering from the approved typography/header pass must
# remain byte-for-byte in the structural owner.
require('[data-gloskin-header="header-2"] .gloskin-ui1-header__inner{grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);' in core,
        "Header V2 geometric centering must remain approved 1fr/auto/1fr")
require('[data-gloskin-header="header-2"] .gloskin-ui1-header__zone--end{grid-column:3;justify-self:end}' in core,
        "Header V2 utility alignment must remain column 3/end")

# Doctor hub/About are factual directories, not preview slices. Both must use
# one dedicated all-published collection with no 4/13 display ceiling.
require("private function all_published_doctor_cards()" in template_service, "dedicated all-published doctor collection missing")
doctor_collection = template_service.split("private function all_published_doctor_cards()", 1)[1].split("private function post_cards_except", 1)[0]
require("'post_status'    => 'publish'" in doctor_collection, "all-doctor collection must query published records")
require("'posts_per_page' => -1" in doctor_collection, "all-doctor collection must have no display ceiling")
require("Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE" in doctor_collection, "all-doctor collection must query Gloskin doctors only")
about_context = template_service.split("private function about_context()", 1)[1].split("private function treatments_context()", 1)[0]
doctors_context = template_service.split("private function doctors_context()", 1)[1].split("private function doctor_context()", 1)[0]
require("$this->all_published_doctor_cards()" in about_context, "About must render all published doctors")
require("'doctors' => $this->all_published_doctor_cards()" in doctors_context, "Doctor hub must render all published doctors")
require("DOCTOR_POST_TYPE, 4" not in about_context, "About must not cap doctor team at four")
require("DOCTOR_POST_TYPE, 13" not in doctors_context, "Doctor hub must not use readiness target as display ceiling")
require("DOCTOR_TARGET_COUNT" in content_service and "DOCTOR_TARGET_COUNT" in doctors_context,
        "doctor target may remain readiness metadata but not the collection limit")
require('data-gloskin-section="doctors-grid"' in doctors_template, "Doctor hub section marker missing")
require('data-gloskin-section="about-doctors"' in about_template, "About doctor/team section marker missing")

# Doctor-specific 4/2/1 density. Do not change generic/product grid owners.
for marker in ('[data-gloskin-section="doctors-grid"] .gloskin-ui1-grid--cards',
               '[data-gloskin-section="about-doctors"] .gloskin-ui1-grid--cards'):
    require(marker in refresh, f"doctor-specific grid selector missing: {marker}")
require("grid-template-columns:repeat(4,minmax(0,1fr));" in refresh, "doctor desktop grid must be four columns")
require("@media (max-width:1099px)" in refresh and "repeat(2,minmax(0,1fr))" in refresh,
        "doctor tablet grid must be two columns")
require("@media (max-width:759px)" in refresh and "grid-template-columns:1fr" in refresh,
        "doctor mobile grid must be one column")
require(".gloskin-ui1-card--doctor .gloskin-ui1-card__image{aspect-ratio:4/5}" in base,
        "doctor factual image aspect ratio must remain 4:5")
require(".gloskin-ui1-grid--cards{grid-template-columns:repeat(3,minmax(0,1fr))}" in base,
        "generic card grid must remain three columns at base desktop")
require(".gloskin-ui1-product-grid,\n.gloskin-ui1-shop-skeleton__grid {" in product_grid,
        "canonical product-grid selector owner must remain unchanged")
require("grid-template-columns: repeat(4, minmax(0, 1fr));" in product_grid and "@media (max-width: 1100px)" in product_grid,
        "canonical product-grid 4-column desktop owner/breakpoint must remain unchanged")
require('[data-gloskin-section="doctors-grid"]' not in product_grid and '[data-gloskin-section="about-doctors"]' not in product_grid,
        "doctor-specific layout must not leak into the product-grid owner")

print("width-doctor-grid-contract: OK")
