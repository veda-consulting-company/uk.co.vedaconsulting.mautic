

-- drop custom set and their fields
DELETE FROM `civicrm_custom_group` WHERE table_name in (
'civicrm_value_mautic_webhook', 'civicrm_value_mautic_settings', 'civicrm_value_mautic_contact');

-- drop custom value table
DROP TABLE IF EXISTS civicrm_value_mautic_settings;
DROP TABLE IF EXISTS civicrm_value_mautic_contact;
DROP TABLE IF EXISTS civicrm_value_mautic_webhook;

-- drop sync table
DROP TABLE IF EXISTS `civicrm_mautic_sync`;

-- delete job entry
DELETE FROM `civicrm_job` WHERE name = 'Mautic Sync';

-- drop webhook entity table
DROP TABLE IF EXISTS `civicrm_mauticwebhook`;

