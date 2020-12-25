<?php
use CRM_Mautic_ExtensionUtil as E;
use CRM_Mautic_Utils as U;

/**
 * Matches Mautic Contacts with CiviCRM contacts.
 *
 */
class CRM_Mautic_Contact_ContactMatch {

  /**
   * The alias for the field created on Mautic.
   */
  public const MAUTIC_ID_FIELD_ALIAS = 'civicrm_contact_id';


  /**
   * Retrieve available dedupe rule options for settings form.
   *
   * @return array
   */
  public static function getDedupeRules() {
    $dao = CRM_Core_DAO::executeQuery("
     SELECT * FROM civicrm_dedupe_rule_group
    WHERE contact_type = 'Individual'
    AND used = 'Unsupervised'
    ");
    while ($dao->fetch()) {
      $title = $dao->name == 'IndividualUnsupervised' ? E::ts('Default Unsupervised') : $dao->title;
      $rules[$dao->id] = $title;
    }
    // Allow no dedupe rule in case high volume of webhooks
    // stresses the system.
    $rules[0] = E::ts('-- None --');

    return $rules;
  }

  public static function getMauticContactReferenceFieldId() {
    $mautic_field_info = CRM_Mautic_Utils::getContactCustomFieldInfo('Mautic_Contact_ID');
    return $mautic_field_info['id'] ?? NULL;
  }

  /**
   * Find a contact with a reference to a Mautic Contact.
   *
   * @param int $mauticContactId
   */
  public static function lookupMauticContactReference($mauticContactId) {
    if (!intval($mauticContactId)) {
      return;
    }
    U::checkDebug("looking up mautic contact id $mauticContactId");
    $query = "SELECT entity_id FROM civicrm_value_mautic_contact mc
      INNER JOIN civicrm_contact c
      ON  c.id = mc.entity_id
       AND mc.mautic_contact_id = %1
       AND c.is_deleted != 1
    ";
    $dao = CRM_Core_DAO::executeQuery($query, [1 => [$mauticContactId, 'Integer']]);
    return $dao->fetchValue();
  }

  /**
   * Gets a civicrm contact id set from mautic contact data.
   *
   * @param [] $mauticContact
   * @return NULL|int
   */
  public static function getContactReferenceFromMautic($mauticContact) {
    U::checkDebug('looking for civi reference field in mautic contact.');
    $fieldName = self::MAUTIC_ID_FIELD_ALIAS;
    return !empty($mauticContact['fields']['core'][$fieldName]) ? $mauticContact['fields']['core'][$fieldName]['value'] : NULL;
  }

  /**
   * Apply deduping rules to find a civicrm contact id from Mautic contact data.
   *
   * @param unknown $mauticContact
   * @return NULL
   */
  public static function dedupeFromMauticContact($mauticContact) {
    $ruleId = \Civi::settings()->get('mautic_webhook_dedupe_rule');
    if (!$ruleId) {
      U::checkDebug("No dedupe rule selected. Skipping.");
      return;
    }
    $contactData = CRM_Mautic_Contact_FieldMapping::convertToCiviContact($mauticContact);
    $ruleType = 'Unsupervised';
    $contactType = 'Individual';

    $params =  CRM_Dedupe_Finder::formatParams($contactData, $contactType);
    $params['check_permission'] = FALSE;
    $dupes = CRM_Dedupe_Finder::dupesByParams($params, $contactType, $ruleType, [], $ruleId);
    if ($dupes) {
      return !empty($dupes[0]) ? $dupes[0] : NULL;
    }
  }

  /**
   * Attempt to find a CiviCRM contact from Mautic contact data.
   *
   *  Contacts  are matched in the following way:
   *  1. Field referencing civicrm contact  on the mautic contact.
   *  2. Field referencing the mautic contact on CiviCRM Contacts.
   *  3. Application of dedupe rules from contact values.
   *
   * @param [] $mauticContact
   *
   * @return int|NULL
   *  Id of a CiviCRM contact.
   */
  public static function getCiviFromMauticContact($mauticContact) {
    U::checkDebug('get civi from mautic contact');
    // Look for contact reference in the Mautic Contact.
    $contactId = self::getContactReferenceFromMautic($mauticContact);
    if ($contactId) {
      U::checkDebug('found Civi contact reference in mautic data');
      return $contactId;
    }
    // Are there any contacts referencing this mautic contact via custom field?
    $contactId = self::lookupMauticContactReference($mauticContact['id']);
    if ($contactId) {
      U::checkDebug('found mautic contact id from civicontact');
      return $contactId;
    }
    U::checkDebug("Looking for matching contact via dedupe rule.");
    return self::dedupeFromMauticContact($mauticContact);

  }

  /**
   * Attempt to find a Mautic Contact Id for a CiviCRM Contact.
   *
   * @param array $contact
   * @return int|void
   */
  public static function getMauticFromCiviContact($contact) {
    if (empty($contact['id'])) {
      return;
    }
    $cid = $contact['id'];
    if (!isset(\Civi::$statics[__FUNCTION__]['cidMapCache'][$cid])) {
      \Civi::$statics[__FUNCTION__]['cidMapCache'][$cid] = 0;
      // Use custom field value.
      U::checkDebug("Looking for mautic contact reference in contact.");
      $key = 'custom_' . self::getMauticContactReferenceFieldId();
      $mauticContactId = CRM_Utils_Array::value($key, $contact);
      if ($mauticContactId) {
        \Civi::$statics[__FUNCTION__]['cidMapCache'][$cid] = $mauticContactId;
        return $mauticContactId;
      }
      /* @fixme: MJW Why would we ever have a contact in Mautic with a CiviCRM ID that is not already recorded in CiviCRM?
      $api = CRM_Mautic_Connection::singleton()->newApi('contacts');
      $result = $api->getList(static::MAUTIC_ID_FIELD_ALIAS . ':' . $contact['id'],
          $start = 0,
          $limit = 0,
          $orderBy = '',
          $orderByDir = 'ASC',
          $publishedOnly = TRUE,
          $minimal = TRUE);

      if (!empty($result['contacts'])) {
        U::checkDebug("Fetched mautic contact for civi contact.");
        $mcontact = reset($result['contacts']);
        \Civi::$statics[__FUNCTION__]['cidMapCache'][$cid] = $mcontact['id'];
      }*/
    }
    return \Civi::$statics[__FUNCTION__]['cidMapCache'][$cid];
  }

}
