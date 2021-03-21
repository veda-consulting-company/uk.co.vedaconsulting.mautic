## Information

Releases use the following numbering system:
**{major}.{minor}.{incremental}**

* major: Major refactoring or rewrite - make sure you read and test very carefully!
* minor: Breaking change in some circumstances, or a new feature. Read carefully and make sure you understand the impact of the change.
* incremental: A "safe" change / improvement. Should *always* be safe to upgrade.

* **[BC]**: Items marked with [BC] indicate a breaking change that will require updates to your code if you are using that code in your extension.

## Release 1.2

* Display missing CiviCRM contacts on manual sync summary.
* CiviRules (2.23) now enforces case sensitive for entities.

## Release 1.1

* Catch errors and continue processing webhooks if one fails.
* Add created_date to mauticwebhook table.
* Remove limit of 25 on MauticWebhook.process.
* When doing a pushSync to Mautic check and log mautic contacts with CiviCRM contact IDs that do not exist (probably they have been deleted in CiviCRM but not in Mautic).
* Remove foreign key constraints on civicrm_mauticwebhook table so we don't have issues with deleted contacts.
* Fixes for webhook processing job and pushSync.

## Release 1.0

* Initial release
