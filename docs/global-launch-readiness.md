# Gloskin Global Launch & Exhibition Readiness

This document records the final pre-launch hardening state of Gloskin Site Core. It distinguishes production-safe presentation work from factual content that still belongs to the client/site owner. It does not authorize invented medical, professional, clinic, commerce, or legal information.

## Release

- Runtime release: `0.3.0`.
- Architecture: existing Kernel and eight-service/adapter ownership model retained; no new framework, bootstrap, cache, diagnostics, recovery, or migration subsystem was introduced.
- Public language: Gloskin-owned visitor UI is Bahasa Indonesia. Common product/category terminology such as `Skincare`, `Facial Wash`, `Serum`, and `Anti-Aging` remains where it is normal category language.
- Core presentation assets: first-party custom CSS/JS only; no Tailwind, React, Vue, animation library, remote font, remote stock image, or other critical first-screen network dependency.

## Content classification

Every production content block follows one of these states:

### A. Approved factual

Rendered normally when supplied by canonical Gloskin content or the site editor. Current normalized facts include the nine clinic identities/routes and seven skincare landing groups. Real WordPress/WooCommerce content also renders when present.

### B. Safe editorial

Neutral Indonesian editorial framing may explain how to explore the site, choose a clinic, review published information, or continue toward consultation. It does not assert treatment efficacy, indications, contraindications, doctor credentials, branch contact facts, product registration, or other unresolved factual claims.

### C. Navigational

Buttons and discovery tiles point only to real site destinations or validated stored external/contact destinations. Empty/dummy `#` and JavaScript CTAs are rejected by the presentation check.

### D. Decorative

When approved factual media is unavailable, Gloskin-owned abstract presentation media supplies visual rhythm without implying a real doctor, patient, clinic interior, before/after result, or product. Decorative presentation media is CSS-driven, deterministic, network-independent, and `aria-hidden`.

### E. Unresolved factual

Not invented or seeded. This includes the final eight treatment identities/content, thirteen doctor identities/professional data, branch addresses/phones/WhatsApp/hours/maps/photos, formal About/Vision/Mission/Values, actual Woo products/media, form-provider configuration, booking destinations, and target-site payment configuration where not already entered on the site.

## Indonesian public experience

The launch pass localizes the Gloskin-owned public shell and page-family copy, including:

- desktop and mobile navigation;
- header, footer, accessibility labels, drawer controls, and CTAs;
- default page/hero headings and supporting copy;
- Home discovery and section headings;
- About, Treatments, Clinics, Doctors, Skincare, Contact, Insights, and Shop wrappers;
- treatment, clinic, and doctor detail labels;
- WooCommerce presentation labels owned by Gloskin;
- form-wrapper behavior and fallback presentation.

`tests/check-language.py` statically reviews the owned public string surface and rejects the launch brief's obvious English UI phrases and staging/dummy vocabulary.

## Presentation media

The production presentation-media helper is owned by the existing template layer and rendered with the existing stylesheet. Real WordPress attachment media always wins when available. Otherwise the runtime can render abstract visual compositions for:

- hero/editorial media;
- clinics;
- treatments;
- doctors, using non-human imagery only;
- skincare categories;
- product cards, without fabricated packaging.

Ratios follow the launch presentation intent: editorial/hero landscape, clinic/treatment 4:3, doctor 4:5, and skincare/product 1:1. No remote runtime image source is required.

## Page readiness

| Page family | Launch behavior |
| --- | --- |
| Home | Indonesian hero, three-path discovery, nine clinic identities, seven skincare categories, optional real treatments/doctors/products/posts, closing contact CTA, exhibition-aware layout. |
| About | Approved editor content/Visi/Misi/Nilai only when present; otherwise safe brand/navigation framing, clinic network, optional real doctors. |
| Treatments | Published approved treatments when present; otherwise safe consultation/discovery composition with abstract media and clinic path. |
| Clinics | Nine canonical identities remain present; abstract branch media is used when factual photos are unavailable; factual contact/map/gallery rows render only when stored. |
| Doctors | Published real profiles when present; otherwise non-human abstract presentation and clinic discovery. No fake professional records. |
| Skincare | Seven normalized category labels with Indonesian surrounding UI and abstract category presentation when product media is unavailable. |
| Contact | Nine clinic discovery cards plus only real stored contact actions; external form renders only when a configured provider exists. |
| Insights | Native WordPress posts when present; otherwise safe site-discovery composition. |
| Shop | WooCommerce remains authoritative; real products render when available; empty catalog uses an Indonesian skincare discovery composition without fake products/prices/SKUs/BPOM. |
| Woo product/cart/checkout/account | Native Woo routing/commerce remains authoritative; Gloskin contributes presentation styles and optional approved Woo-managed facts only. |

## Automated launch verification

Run from repository root:

```bash
./tests/check-architecture.sh
./tests/check-language.py
./tests/check-presentation.sh
./tests/check-runtime.sh
```

The runtime/browser suite covers:

- activation and idempotent deactivation/reactivation;
- admin upgrade path;
- Woo absent and Woo-present simulation;
- configured and absent form provider behavior;
- missing media/relationship/contact/map behavior;
- conditional frontend asset loading;
- 13 representative public page views;
- 390x844 mobile;
- 820x1180 tablet;
- 1440x900 desktop;
- 1920x1080 exhibition;
- 2560x1440 large exhibition;
- horizontal overflow, H1 structure, CTA contrast, keyboard focus, drawer focus management, Escape/focus return, disclosure ARIA state, reduced motion, image intrinsic dimensions, public language/dummy leakage, and JavaScript console/page errors.

Chromium is the available automated browser in the current verification environment. Firefox and WebKit are attempted and explicitly reported unavailable when their Playwright engine binaries are not installed; they must not be claimed as tested until run in an environment that provides them.

## Manual presentation review

The sparse factual state was visually reviewed on Home, Treatments, Doctors, Clinics/clinic detail, Skincare, Contact, and Shop. Home was additionally reviewed at 1920x1080 and 2560x1440 for first-viewport composition, hero proportion, max-width, line length, card scale, header/footer balance, and excess-margin risk.

## Remaining launch-environment checks

Repository verification cannot prove target-host configuration. Before public deployment, the deployment owner should still verify on the actual WordPress host:

- real client-owned content entered in WordPress/WooCommerce;
- final menu assignment and front-page/shop configuration;
- target-domain HTTPS/permalinks;
- live form provider, WhatsApp, telephone and Maps destinations when supplied;
- Woo cart/checkout/payment gateways if commerce is enabled;
- cache/CDN behavior if the host adds one;
- Firefox and Safari/WebKit on representative real devices/browsers.

These are deployment/content-owner checks, not permission to replace missing facts with fabricated content.
