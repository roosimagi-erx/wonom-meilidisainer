# Wonom Email Designer

A visual designer for WooCommerce order emails. Set the brand once and it applies
to every email — you add blocks only where you actually need them.

**Status:** test version 0.24.0 · **Needs:** WordPress 6.0+, PHP 7.4+, WooCommerce 6.0+
· **Languages:** English, Estonian ([eestikeelne README](README.et.md))

---

## Why

Most email builders make you assemble every transactional email separately —
fourteen emails, fourteen times the same work. Here there are two levels:

**1. Brand.** Logo, colour palette, font, button style, email width, header and
footer. Set once, applied to every email automatically. For most shops the work
ends here.

**2. Emails.** Only when one particular email needs something extra: its own
subject line, its own large heading, blocks before or after the order table.

The order table, the addresses and the totals are still rendered by WooCommerce,
so nothing breaks when WooCommerce updates its templates. If you want full
control, "Build the whole email" takes the WooCommerce content apart into blocks
you can edit.

## What is in the box

| | |
|---|---|
| **Brand** | logo, colours, font and size, button style, email width, corner radius, inner padding, extra CSS |
| **Header and footer** | shared by every email, built from blocks |
| **Per-email content** | subject line, large heading, blocks before or after the order table |
| **Blocks** | heading, paragraph, button, image, divider, spacer, two columns, images side by side, social media, custom HTML |
| **WooCommerce blocks** | order table, addresses, totals, customer note, order field, payment instructions |
| **Variables** | 58 tags such as `{{customer_first_name}}`, `{{order_number}}`, plus `{{meta:any_order_field}}` |
| **Live preview** | desktop and mobile, filled with a real order you pick |
| **Test email** | sends the real WooCommerce email to a test address |
| **Export / import** | the whole design in one file, to move it to another shop |
| **Updates** | straight from your GitHub releases |

## Emails covered

Order processing · completed · on hold · refunded · invoice / pending payment ·
note to customer · password reset · new account · new order to the shop ·
cancelled order · failed order.

## Installation

1. Upload the folder `wonom-meilidisainer` to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open **WooCommerce → Email Designer**.

## Translating

The source language is English. Estonian is bundled in `languages/`.

To add a language, take `languages/wonom-meilidisainer.pot`, translate it with
Poedit or any `.po` editor, and save it as `wonom-meilidisainer-<locale>.po`
plus the compiled `.mo`. For the designer's JavaScript you also need the JSON
files that `wp i18n make-json` produces.

In this repository both are built by `tests/i18n-build.php`, which takes the
translations from `tests/i18n-map.php` (PHP strings) and `tests/i18n-js-et.php`
(JavaScript strings):

```
php tests/i18n-build.php wonom-meilidisainer
```

## Development

There is no PHP or Node on the development machine, so the checks bring their
own portable PHP:

```
.\tests\run.ps1
```

That runs `php -l` over every file and then `tests/smoke.php`, which imitates
enough of WordPress to execute the plugin's own logic under `E_ALL` — a warning
counts as a failure.

`demo/wonom-meilidisainer-demo.html` loads the real `admin.js`, `renderer.js`
and `admin.css` with stubbed AJAX, so the designer can be driven without a
WordPress install.

## Releases

```
.\build-release.ps1 -Version X.Y.Z -Tag
```

This bumps the version in three places, builds the ZIP, commits, tags and
pushes. Publishing the GitHub release and attaching `wonom-meilidisainer.zip`
is a manual step — without the ZIP the updater cannot see the release.

## Licence

GPL-2.0-or-later.
