# Stoneholl Related Customer Orders

A free, offline Magento Admin order-view helper. Shows ten other orders per page from the exact same store view, ordered by descending database record ID. Requires free Stoneholl Core ^1.0; Connector, pairing and paid entitlements are not required. MIT license.

## Features

- Compact native Admin table: linked order number, creation date/time using Magento display settings, native status label and explicit match basis.
- Registered orders match the same positive customer account ID and non-guest flag. The current customer profile and profile email are never read.
- Optional guest matching requires both a separate setting and a separate permission. Guest and registered orders never mix.
- Native Magento grid, striped rows and AJAX Previous/Next pager with a page input (Enter to navigate); only this block refreshes. Match/page counts cover only the bounded searched window.
- Visible candidate coverage, clear unavailable/empty states and an explicit warning that matching guest email does not prove identity.
- Default-disabled; one local RequireJS pager and a read-only authenticated Admin HTML endpoint. No extra stylesheets, order mutations, storefront, public API, cron or database schema changes.

## Matching and search limits

Only orders from the current order's exact `store_id` are displayed. Admin store 0 and deleted/missing stores are unavailable. The current order is excluded. Orders with missing/inconsistent identity flags, including former registered orders detached after customer deletion, cannot become guest matches.

Registered mode inspects the newest 1,000 other account orders. Other-store orders consume this account window but never appear. Guest mode inspects the newest 1,000 other orders in the current store. A 1,001st row is only a truncation sentinel; the block reports that older orders were not searched. The displayed count is the actual candidate window size, not a total history count. Pagination covers only eligible matches in this fixed window, ten per page (at most 100 pages). For 100 matching other orders, there are ten pages. Counts are derived in PHP from the bounded identity projection; there is no whole-history COUNT or SQL OFFSET query. Every page rechecks permissions and identity/store/email rules. Native keyed page URLs carry only the current order ID and a numeric `related_orders_page`, without email or filter serialization. Malformed page parameters return the first page; stale/out-of-range numeric pages clamp to the current last page. New/changed/deleted orders can move results between pages. A concurrently empty page reports the change rather than claiming no matches, with no unbounded backfill.

Guest matching uses the order email snapshot. It accepts at most 128 ASCII bytes with one `@`, an unquoted dot-atom local part of at most 64 bytes, and a domain of at least two valid 1–63 byte labels. The local part is preserved byte for byte; only the domain is lowercased. Matching is performed in PHP independently of database collation. No trimming, local-part case folding, plus-tag/dot removal, Unicode conversion, DNS calls, aliases, phone, name, address or fuzzy matching. Unsupported addresses are unavailable. Shared or recycled mailboxes may belong to different people.

The candidate scan uses existing native customer/store indexes and descending entity IDs. Registered mode uses at most two feature queries, guest mode at most three. The status label is joined in the final query, with no per-order or per-status model load. Store metadata/configuration/ACL work is additional to these budgets. Query plans and total request performance must be verified on each supported runtime before release; a `LIMIT` alone is not a performance guarantee.

## Installation and configuration

Install with Composer from your Magento root:

```sh
composer require stoneholl/module-related-customer-orders:^1.0
bin/magento module:enable Stoneholl_Core Stoneholl_RelatedCustomerOrders
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean config layout block_html
```

Follow your normal deployment and backup procedures. Composer installs the required free Core dependency. In production mode, deploy Admin static content for your configured locales before opening the order page.

At **Stores → Configuration → Stoneholl → Related Customer Orders**, Default scope only:

| Setting | Default | Effect |
| --- | --- | --- |
| Enable Related Customer Orders | No | Show account matches to authorized roles. |
| Enable Possible Guest Email Matches | No | Permit strict guest matching only for roles with the additional guest permission. |

Roles need both `Magento_Sales::actions_view` and `Stoneholl_RelatedCustomerOrders::view`. Guest matching additionally requires `Stoneholl_RelatedCustomerOrders::guest_matches`. Configuration is governed separately by `Stoneholl_RelatedCustomerOrders::config`; configuring the module does not grant viewing permission. Refresh role sessions after permission changes. Unauthorized or disabled blocks render no payload and execute no related-order queries; guest denial also avoids reading the current email snapshot.

Magento Open Source's native action ACL does not establish per-order/store ownership restrictions. **Before granting the view permission to roles with third-party order restrictions, verify an integration that applies those restrictions to source queries and native target access. Otherwise keep this feature denied for those roles.** A same-store filter is a feature boundary, not row-level authorization. Native order links contain only Magento's order ID and backend key; missing/denied targets follow native behavior. Third-party Admin layouts need placement verification.

## Privacy and removal

No order/customer mutations, automatic actions, new feature storage, new sessions, local/session storage, telemetry or external network calls. The read-only AJAX endpoint uses the existing same-origin Admin session. Email stays server-side during the request and is stripped from presentation state. Names, email, addresses, amounts, items and payment data are not shown. Exceptions log fixed diagnostic codes only, with no identifiers, SQL or exception context. The block explicitly disables HTML caching, even if a layout supplies a cache lifetime, and is marked non-cacheable for FPC. No additional order-data cache is introduced. Every page/request resolves current configuration and authorization again; native configuration/ACL caches follow Magento invalidation and session rules.

Disable the feature first. Remove `Stoneholl_RelatedCustomerOrders` using Magento's normal module/package removal procedure and remove only its `stoneholl_related_customer_orders/general/enabled` and `stoneholl_related_customer_orders/general/guest_email_matching` configuration paths through your normal configuration management. No schema/data cleanup is needed for orders or customers. Keep Core installed while other Stoneholl modules depend on it.

## Compatibility and validation

Declared source target: Magento Open Source 2.3.7-p4 through 2.4.9; PHP 7.4–8.5, including PHP 8.0 syntax. This is not a claim that every Magento/PHP combination installs, or that Adobe Commerce is supported.

Local validation as of 2026-10-20 used existing Docker services on Magento Open Source 2.3.7-p4 / PHP 7.4.33 and Magento Open Source 2.4.9 / PHP 8.5.9: native install, upgrade, Core-only DI compilation, Admin static deployment, persisted-role/configuration checks and authenticated HTTP AJAX/cache checks. Connector 1.0.0 enabled and disabled compilation was also tested. Core plus all four free order helpers passed upgrade/DI compilation together with Connector absent from module discovery. Interactive browser pagination and preservation of unsaved order input were checked on 2.4.9 / PHP 8.5.9. Focused PHPUnit checks on PHP 8.5.9 pass 32 tests / 360 assertions; PHP 7.4/8.5 lint, 7.4/8.0/8.5 parser checks, XML schemas, PHPCS and Composer validation pass. PHP 8.0 has parser coverage only, with no runtime or unit-test lane. Other Magento/PHP pairs and Adobe Commerce are not runtime certified. Private CI checks PHP 7.4/8.0/8.5 syntax and Composer/XML/JSON metadata. Public distributions contain runtime files and documentation only; development tests and tooling stay in the private source repository.

## Extension boundary

Initial documented extension surfaces are layout block `stoneholl.related_customer_orders` in `order_additional_info` (order 10, before Order Navigation) and the two settings above. The `related_orders_page` order-view parameter selects a bounded page. Internal matcher/reader classes are not public APIs. Complete history, cross-store matching, phone matching, exports, merge/account linking and paid-board integration require separate design approval.
