<?php 
use CRM_Mautic_Utils as U;

/**
 * @todo: implement.
 *
 */
class CRM_Mautic_Contact_ContactMatch {
  
  /**
   * The alias for the field created on Mautic.
   */
  public const MAUTIC_ID_FIELD_ALIAS = 'civicrm_contact_id';
  
  /**
   * Find a contact with a reference to a Mautic Contact.
   * 
   * @param int $mauticContactId
   */
  public static function lookupMauticContactReference($mauticContactId) {
    U::checkDebug("looking up mautic contact id $mauticContactId");
    $query = "SELECT entity_id FROM civicrm_value_mautic_contact 
     WHERE mautic_contact_id = %1
    ";
    $dao = CRM_Core_DAO::executeQuery($query, [1 => [$mauticContactId, 'Integer']]);
    return $dao->fetchValue();
  }
  
  /**
   * Gets a civicrm contact id set from mautic contact data.
   * 
   * @param unknown $mauticContact
   * @return NULL|int
   */
  public static function getContactReferenceFromMautic($mauticContact) {
    $fieldName = self::MAUTIC_ID_FIELD_ALIAS;
    if (is_array($mauticContact)) {
      return !empty($mauticContact['fields']['core'][$fieldName]) ? $mauticContact['fields']['core'][$fieldName] : NULL;
    }
    elseif (is_object($mauticContact)) {
      return !empty($mauticContact->fields->core->{$fieldName}->value) ? $mauticContact->fields->core->{$fieldName}->value : NULL;
    }
  }
 
  /**
   * Apply deduping rules to find a civicrm contact id from Mautic contact data.
   * 
   * @param unknown $mauticContact
   * @return NULL
   */
  public static function dedupeFromMauticContact($mauticContact) {
    $contactData = CRM_Mautic_Contact_FieldMapping::convertToCiviContact($mauticContact);
    U::checkDebug(__FUNCTION__ . ' using contact data', $contactData);
    
    // We use default unsupervised.
    // @todo make dedupe rule configurable.
    
    $ruleType = 'Unsupervised';
    $contactType = 'Individual';
    $params =  CRM_Dedupe_Finder::formatParams($contactData, $contactType);
    $params['check_permission'] = FALSE;
    U::checkDebug("deduping params are", $params);
    
    $dupes = CRM_Dedupe_Finder::dupesByParams($params, $contactType, $ruleType, []);
    U::checkDebug("deduping result is", $dupes);
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
   * @return int
   *  Id of a CiviCRM contact.
   */
  public static function getCiviFromMauticContact($mauticContact) {
    U::checkDebug('get civi from mautic contact', $mauticContact); 
    // Look for contact reference in the Mautic Contact.
    $contactId = self::getContactReferenceFromMautic($mauticContact);
    if ($contactId) {
      U::checkDebug('foundCivi contact reference in mautic data');
      return $contactId;
    }
    // Are there any contacts referencing this mautic contact via custom field?
    $contactId = self::lookupMauticContactReference($mauticContact->id);
    if ($contactId) {
      U::checkDebug('found mautic contact id from civicontact');
      return $contactId;
    }
    U::checkDebug("Looking for matching contact via dedupe rule.");
    return self::dedupeFromMauticContact($mauticContact);
    
  }
  
  public static function getMauticFromCiviContact($contact) {
    // Use custom field.
    // Use email match.
  }
    
}