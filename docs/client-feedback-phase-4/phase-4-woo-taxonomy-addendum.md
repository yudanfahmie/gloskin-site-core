# Phase 4 Addendum — Woo Native Product Category Alignment

## Why this addendum exists

Phase 3 production can be fully valid while WooCommerce's native **Categories** column still shows `Uncategorized`.

That is because the Phase-3 contract owns and verifies the private Gloskin taxonomies:

- `gloskin_product_family` (`skincare` / `treatment`)
- `gloskin_concern`
- `gloskin_consultation_path`

The Phase-3 Woo reconciler intentionally assigns `gloskin_product_family` and Treatment concern terms, but it does **not** assign Woo's native `product_cat` taxonomy. Therefore `Uncategorized` in Woo admin is not evidence that Phase 3 failed.

Phase 4 should align Woo's native merchandising categories in one bounded, idempotent pass so the final catalog is editor-friendly and Shop filtering can use canonical Woo categories.

---

## Non-negotiable boundary

Do **not** replace or repurpose Phase-3 private taxonomies.

Preserve exactly:

- `gloskin_product_family=skincare` for the canonical 25 Skincare products
- `gloskin_product_family=treatment` for the canonical 48 Treatment products
- all authoritative `gloskin_concern` assignments
- all Phase-3 canonical product slugs, prices, images, provenance, stock policy, and migration state

Woo `product_cat` is an additional merchandising/editor taxonomy only.

---

## One-shot owner

Implement this inside the small Phase-4 privileged/idempotent finalizer already planned.

Do not create another migration framework.

Required order:

1. verify the canonical Phase-3 product sets are present;
2. ensure the required native Woo `product_cat` terms exist;
3. assign categories only to the canonical 25 + 48 products;
4. verify all assignments;
5. remove `Uncategorized` from a canonical product only after another valid native category is attached;
6. save completion state;
7. second run must make zero category mutations.

Never mutate unrelated Woo products.

---

## Canonical Skincare category mapping

Use `skincare-products.json` as the identity/category source, but do not modify that authoritative file.

Map its `category` field to Woo `product_cat`:

- `facial-wash` -> `facial-wash` / **Facial Wash**
- `day-cream-sunscreen` -> `day-cream-sunscreen` / **Day Cream / Sunscreen**
- `toner` -> `toner` / **Toner**
- `serum` -> `serum` / **Serum**
- `acne-care` -> `acne-care` / **Acne Care**
- `anti-aging` -> `anti-aging` / **Anti-Aging**
- `brightening-pigmentation-care` -> `brightening-pigmentation-care` / **Brightening & Pigmentation Care**
- `support` -> `produk-penunjang` / **Produk Penunjang**

Reuse existing terms by slug. Create only missing terms required by this map.

Do not infer extra Skincare categories beyond the manifest value.

---

## Canonical Treatment category mapping

All 48 canonical Woo Treatment products must receive the native Woo category:

- `perawatan` / **Perawatan**

Do not create one Woo category per Treatment `group` in this pass. The manifest `group` and `gloskin_concern` data already carry Treatment classification and consultation semantics; duplicating those into a second category tree is unnecessary for this repair.

The Phase-4 pass may keep any legitimate pre-existing additional `product_cat` assignment on a canonical product, but must ensure `perawatan` exists on every canonical Treatment product.

---

## Uncategorized cleanup

For each canonical Phase-3 product only:

- if at least one valid canonical `product_cat` is now assigned, remove the Woo default `uncategorized` term from that product;
- never globally delete the `uncategorized` term;
- never remove categories from unrelated products.

Final requirement:

- canonical Skincare with only `Uncategorized`: **0**
- canonical Treatment with only `Uncategorized`: **0**

---

## Shop integration

Phase 4 already fixes the Shop AJAX selector and category behavior.

Ensure the existing Shop catalog path can filter the new native `perawatan` category without adding a second filter state owner or a second taxonomy model.

Do not break the existing Skincare category pages/mappings.

If Shop currently renders only the Skincare mapping list as category choices, extend the existing Shop category projection minimally so `Perawatan` is reachable while the canonical category filtering remains Woo `product_cat`-based.

---

## Focused contract additions

Phase-4 tests must prove:

1. exact 25 canonical Skincare products retain `gloskin_product_family=skincare`;
2. exact 48 canonical Treatment products retain `gloskin_product_family=treatment`;
3. all authoritative Treatment concern assignments remain untouched;
4. every canonical Skincare product receives its manifest-derived native `product_cat` mapping;
5. every canonical Treatment product receives native `product_cat=perawatan`;
6. `support` Skincare resolves deterministically to `produk-penunjang`;
7. no canonical Phase-3 product remains only `Uncategorized`;
8. unrelated Woo products are unchanged;
9. second taxonomy-finalize run performs zero mutations;
10. Shop can filter `perawatan` through the existing catalog/filter owner;
11. Phase-3 manifests/state/media remain byte-unchanged.

---

## Final report additions

```text
WOO NATIVE CATEGORY ALIGNMENT: PASS/FAIL
SKINCARE PRODUCT_CAT ALIGNED: 25/25
TREATMENT PRODUCT_CAT=PERAWATAN: 48/48
CANONICAL PRODUCTS ONLY UNCATEGORIZED: 0
UNRELATED WOO PRODUCTS MUTATED: 0
SHOP PERAWATAN FILTER: PASS/FAIL
TAXONOMY SECOND RUN NO-OP: PASS/FAIL
PHASE3 PRIVATE TAXONOMIES PRESERVED: PASS/FAIL
```
