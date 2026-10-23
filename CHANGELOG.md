# Changelog


## 1.0.1 - 2026-10-23

- Rename the Composer package to `stoneholl/magento2-related-customer-orders` and replace its corresponding legacy `stoneholl/module-related-customer-orders` identity at `self.version`.
- Require the canonical Core package from version 1.0.1 and update installation/migration documentation.
- Keep Magento module identifiers, configuration, storage and licensing unchanged.

## 1.0.0 — 2026-10-20

- Add a default-disabled native Admin table of related orders from the exact same store view.
- Match registered account snapshots; offer opt-in strict guest email matching with separate permission and an identity uncertainty notice.
- Show ten matches per page within a bounded 1,000-order search window, without whole-history COUNT or SQL OFFSET.
- Update only the table with AJAX, preserve unsaved order input and provide native keyed navigation as a fallback.
- Recheck permissions and matching rules on every request and disable response caching.
- Integrate with free offline Stoneholl Core; Connector and paid entitlements are not required.
- Validate native installation, upgrade, DI compilation, permissions and HTTP behavior on Magento Open Source 2.3.7-p4 / PHP 7.4.33 and 2.4.9 / PHP 8.5.9. Other pairs remain unverified.
