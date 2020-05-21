<?php
class CRM_Mautic_Contact_FieldMapping {
  
  /**
   * Mapping of field names from CiviCRM to Mautic Contacts.
   * 
   * @var array
   */
  protected static $defaultFieldMapping = [
    'first_name' => 'firstname',
    'last_name' => 'lastname',
    'email' => 'email',
    // We treat the civi contact id separately.
    'id' => 'civicrm_contact_id',
    'civicrm_contact_id' => 'civicrm_contact_id',
  ];
  
  
  public static function getValue($data, $civiFieldName, $default = '') {
    $values = !empty($data['fields']['all']) ? $data['fields']['all'] : [];
    $mapping = self::$defaultFieldMapping;
    $key = !empty($mapping[$civiFieldName]) ? $mapping[$civiFieldName] : '';
    return $key && isset($values[$key]) ? $values[$key] : $default;
  }
  
}