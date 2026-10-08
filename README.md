# Belkajo_ProductBadge

Magento 2 module that adds a translatable **Badge** product attribute and a custom
`productDetails` GraphQL query.

## Features

- `badge` dropdown attribute (`New`, `Best Seller`, `Sale`), assignable in the admin.
- The label is translatable per store view (EN `New` / FR `Nouveau` / DE `Neu`) and can be
  edited in the admin.
- When a badge is assigned, the product name includes it in square brackets wherever
  `getName()` is used: `Joust Duffle Bag [Best Seller]`.
- `productDetails(sku: "...")` GraphQL query returning the product data and the badge of the
  current store view.

## Requirements

Magento 2.4.9 (CE), PHP 8.3 - 8.5, and store views with the codes `en`, `fr` and `de`.

## Installation

The `AddBadgeStoreLabels` data patch resolves the store ids by store view **code**, so the
`en`, `fr` and `de` store views have to exist before the module is installed. If a code is
missing, the patch stops with `Create the "fr", "de" store view(s) before running this patch.`
and nothing is written.

Set the locales as well, otherwise the `i18n/*.csv` translations (for example `stock_status`)
stay English:

```bash
bin/magento config:set --scope=stores --scope-code=fr general/locale/code fr_FR
bin/magento config:set --scope=stores --scope-code=de general/locale/code de_DE
```

Install the module - pick one of the three options below:

**Install with composer:**

```bash
cd <magento root>
composer config repositories.belkajo-product-badge vcs https://github.com/belkajo/ProductBadge.git
composer require belkajo/product-badge:^1.0
```

**Install with git:**

```bash
cd <magento root>
git clone https://github.com/belkajo/ProductBadge.git app/code/Belkajo/ProductBadge
```

**Install from a zip:** unpack the archive so the files end up in
`app/code/Belkajo/ProductBadge`:

```bash
cd <magento root>
unzip /path/to/ProductBadge.zip -d app/code/Belkajo/
```

Then enable and install the module:

```bash
bin/magento module:enable Belkajo_ProductBadge
bin/magento setup:upgrade
bin/magento cache:flush
```

In production mode also run:

```bash
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
```

Finally assign a badge to a product: **Catalog → Products → edit product → Badge**.

## Usage

The store view is selected with the `Store` HTTP header, so the badge, the name and the
`stock_status` come back in the language of that store view.

```graphql
query {
  productDetails(sku: "24-MB01") {
    sku
    name
    price
    currency
    stock_status
    badge
  }
}
```

Response for `Store: en`:

```json
{
  "data": {
    "productDetails": {
      "sku": "24-MB01",
      "name": "Joust Duffle Bag [Best Seller]",
      "price": 34,
      "currency": "GBP",
      "stock_status": "In stock",
      "badge": "Best Seller"
    }
  }
}
```

For `Store: fr` the same query returns `name` `Joust Duffle Bag [Meilleure vente]`,
`badge` `Meilleure vente`, `stock_status` `En stock` and `currency` `EUR`.

An unknown SKU is reported as a regular GraphQL error (`graphql-input`), not as a 500 response.
Ready-made requests for all store views are in `Tests/graphql.http` (PhpStorm HTTP client).

## Assumptions

- The store views use the codes `en`, `fr` and `de`; the patch fails fast when one is missing
  instead of silently leaving the labels untranslated.
- The options are exactly `New`, `Best Seller` and `Sale`. The task only defined the wording of
  `New`, so the other two were translated as `Meilleure vente` / `Promo` (FR) and
  `Bestseller` / `Angebot` (DE).
- Badge labels are EAV option values per store view, not translation CSV entries: attribute
  option labels do not go through `__()`, and this keeps them editable in the admin. The CSVs
  are only used for the strings the module itself produces.
- `price` is the stored price value: no catalog price rule (`final_price`) and no currency
  conversion is applied. `currency` is the store view's current currency from the
  configuration, not a hardcoded GBP/EUR.
- `stock_status` returns the localised strings `In stock` / `Out of stock`.
- The `[Badge]` suffix is only added to the `getName()` output; the stored product `name` and
  the URL keys stay untouched.
- No frontend or theme changes, the standard Magento admin and frontend are used.
