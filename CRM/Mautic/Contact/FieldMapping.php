<?php
class CRM_Mautic_Contact_FieldMapping {
  
  /**
   * Mapping of field names from CiviCRM to Mautic Contacts.
   * 
   * This is a basic name to alias map.
   * 
   * @var array
   *  Associative array where keys are the keys on a CiviCRM contact and Values are keys on a Mautic contact.
   */
  protected static $defaultFieldMapping = [
    'first_name' => 'firstname',
    'last_name' => 'lastname',
    'email' => 'email',
    // We treat the civi contact id separately.
    'id' => 'civicrm_contact_id',
    'civicrm_contact_id' => 'civicrm_contact_id',
  ];
  
  /**
   * Gets a set of civi to mautic field names.
   * 
   * @return string|array|string[]
   */
  public static function getMapping() {
    $mapping = static::$defaultFieldMapping;
    // Map the custom field referencing the Mautic contact id.
 $mautic_field_info = CRM_Mautic_Utils::getContactCustomFieldInfo('Mautic_Contact_ID');
    if (!empty($mautic_field_info['id'])) {
      $mapping['custom_' . $mautic_field_info['id']] = 'id';
    }
    return $mapping;
  }
    
  public static function getValue($data, $civiFieldName, $default = '') {
    $values = !empty($data['fields']['all']) ? $data['fields']['all'] : [];
    $mapping = self::$defaultFieldMapping;
    $key = !empty($mapping[$civiFieldName]) ? $mapping[$civiFieldName] : '';
    return $key && isset($values[$key]) ? $values[$key] : $default;
  }
  
  /**
   * Gets a value by key on mautic data.
   * 
   * Mautic api return associative arrays whereas webhook data are objects.
   * Most fields are nested within the fields property but some are not.
   * This helper function makes getting data more convenient.
   * Rather than flattening and converting the whole data structure we 
   * look in the first level of properties then in the fields.
   * 
   * @param string $key
   * @param string $data
   * @return mixed
   */
  protected static function lookupMauticValue($key, $data) {
    
    if (is_array($data)) {
      if (isset($data[$key])) {
        return $data[$key];
      }
      if (isset($data['fields']['core'][$key]['value'])) {
        return $data['fields']['core'][$key]['value'];
      }
    }
    elseif (is_object($data)) {
      if (isset($data->fields->core->{$key}->value)) {
        return $data->fields->core->{$key}->value;
      }
      if (isset($data->{$key})) {
        return $data->{$key};
      }
    }
  }
 
  /**
   * Converts between Mautic and CiviCRM values.
   * 
   * @param mixed $contactData
   * @param boolean $civiToMautic
   * @return mixed[]|array[]|string[]
   */
  protected static function convertContact($contactData, $civiToMautic = TRUE) {
    $mapping = static::getMapping();
    if (!$civiToMautic) {
      $mapping = array_flip($mapping);
    }
    $convertedContact = [];
    foreach ($mapping as $getKey => $setKey) {
      if (!$civiToMautic) {
        $convertedContact[$setKey] = static::lookupMauticValue($getKey, $contactData);
      }
      else {
        $convertedContact[$setKey] = CRM_Utils_Array::value($getKey, $contactData); 
      }
    }
    return $convertedContact;
  }
  
  public static function convertToMauticContact($contact) {
    return static::convertContact($contact, TRUE);
  }
  
  /**
   * Converts mautic contact data to values for a civicrm contact.
   * 
   * @param unknown $mauticContact
   * @return mixed[]|array|string[]
   */
  public static function convertToCiviContact($mauticContact) {
    $contact = static::convertContact($mauticContact, FALSE);
    unset($contact['civicrm_contact_id']);
    return $contact;
  }
}