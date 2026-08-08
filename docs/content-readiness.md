# Gloskin Content Readiness

This checklist records what the staging runtime can safely populate from canonical repository facts and what remains client-owned input. It does not authorize placeholder medical, doctor, branch-contact, or marketing claims.

## Populated from approved/normalized facts

| Area | Runtime state | Populated facts |
| --- | --- | --- |
| Main Pages | implemented and provisioned | Home, About, Treatments, Skincare, Clinics, Contact, Insights, Shop, Doctors |
| Clinics | implemented and identity-populated | exactly 9 branch identities/routes: Kebayoran Baru, Tebet, Bekasi, Cibubur, Serpong, Surabaya, Banjarmasin, Balikpapan, Denpasar |
| Skincare | implemented and provisioned | 7 landing groups: Facial Wash; Day Cream / Sunscreen; Toner; Serum; Acne Care; Anti-Aging; Brightening & Pigmentation Care |
| Treatments | implemented, content pending | CPT, hub/detail templates, fields, relationships and an 8-record presentation target exist; no unapproved treatment names are seeded |
| Doctors | implemented, content pending | CPT, hub/detail templates, fields, relationships and a 13-record presentation target exist; no unapproved doctor identities are seeded |
| Insights | implemented | native WordPress Posts are queried; no duplicate Insights content type exists |
| Shop / Products | implemented integration | WooCommerce remains authoritative; Gloskin reads supported products/categories and optional BPOM/composition/usage data when present |
| Contact | implemented integration | clinic contacts render when entered; external form shortcode renders only when its provider is registered; otherwise a neutral fallback is shown |

## Pending client/site data

The canonical source still marks these as unresolved, so the runtime intentionally does not invent them:

- final approved eight treatment names, slugs, descriptions, benefits, contraindications and relationships;
- clinic addresses, phone numbers, WhatsApp numbers/messages, operating hours, map data and approved gallery media;
- thirteen doctor identities, portraits, degree/title, specialization, branches, SIP values, credentials, profiles, schedules, treatment relationships and booking targets;
- approved About overview, Vision, Mission and Values copy;
- actual WooCommerce products, product media and final mapping of the seven skincare pages to deployed Woo category slugs/term data;
- chosen external form provider/shortcode;
- final global/branch booking destinations where not already entered;
- final selected visual direction if the current Medical Professional default is not the launch choice;
- target-site commerce configuration and payment gateways.

## Population behavior

Activation or the narrow `0.2.0` admin upgrade provisions only facts already normalized in canonical docs: the main Pages, seven skincare child Pages/mapping defaults, and nine clinic identity records. Existing editor content is not overwritten. Treatment and doctor placeholder records are deliberately not created.
