## Information

Releases use the following numbering system:
**{major}.{minor}.{incremental}**

* major: Major refactoring or rewrite - make sure you read and test very carefully!
* minor: Breaking change in some circumstances, or a new feature. Read carefully and make sure you understand the impact of the change.
* incremental: A "safe" change / improvement. Should *always* be safe to upgrade.

* **[BC]**: Items marked with [BC] indicate a breaking change that will require updates to your code if you are using that code in your extension.

## Release 1.4

* Sync contact address from Mautic to CiviCRM.
* Set Contact Source to "Mautic" when created via Mautic.
* Create webhook activity in Completed status.
* Fixes to email/contact update/create on Mautic -> CiviCRM sync.
* Allow changing an existing email when syncing mautic -> CiviCRM.
* Fix error on processing mautic.lead_post_delete (which we don't do anything with).
* Add support for pushing communication preferences to Mautic (removing 'Do Not Contact' from Mautic contact).
* Create 'Update Communication Preferences' activity when updated via Mautic.
* If a contact is deleted in CiviCRM and "Anonymous" in Mautic don't try to sync.

## Release 1.3

* Add Upgrader to change the operation for existing MauticWebHook Trigger from create to edit.
* Fix case when accessing CiviRules trigger data.
* Fix various PHP warnings.

## Release 1.2

* Display missing CiviCRM contacts on manual sync summary.
* CiviRules (2.23) now enforces case sensitive for entities.

## Release 1.1

* Catch errors and continue processing webhooks if one fails.
* Add created_date to mauticwebhook table.
* Remove limit of 25 on MauticWebHook.process.
* When doing a pushSync to Mautic check and log mautic contacts with CiviCRM contact IDs that do not exist (probably they have been deleted in CiviCRM but not in Mautic).
* Remove foreign key constraints on civicrm_mauticwebhook table so we don't have issues with deleted contacts.
* Fixes for webhook processing job and pushSync.

## Release 1.0

* Initial release
