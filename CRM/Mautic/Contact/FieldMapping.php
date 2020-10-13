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
   * Alter the converted contact communication prefs to include  subscription changes on Mautic.
   * @param stdClass $mauticContact
   * @param array $contact
   */
  public static function commsPrefsMauticToCivi($mauticContact, &$contact) {
    // The doNotContact field appears to have an empty array when false and a nested empty array when true.
    // Not sure how to interpret this. So will use the channel and status property which is included
    // in the payload for webook mautic.lead_channel_subscription_changed.
    $channel = self::lookupMauticValue('channel', $mauticContact);
    if (!$channel) {
      return;
    }
    $status = self::lookupMauticValue('new_status', $mauticContact);
    // Only make a change if channel is explicitly set.
    if ($channel == 'email' && $status) {
      // Can be contactable|manual.
      $contact['is_opt_out'] = $status != 'contactable';
      // We wont set do_not_email here.
    }
  }

  /**
   * Sets doNotContact when Converting to a Mautic contact.
   *
   * @param [] $civiContact
   * @param [] $mauticContact
   */
  public static function commsPrefsCiviToMautic($civiContact, &$mauticContact) {
    if (!empty($civiContact['is_opt_out']) || !empty($civiContact['do_not_email'])) {
       $mauticContact['doNotContact'][] = [
         'channel' => 'email',
         'reason' => Mautic\Api\Contacts::MANUAL,
       ];
    }
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
    CRM_Mautic_Utils::checkDebug(__FUNCTION__, [$contactData, $mapping]);
    if (!$civiToMautic) {
      $mapping = array_flip($mapping);
    }
    $convertedContact = [];
    foreach ($mapping as $getKey => $setKey) {
      if (!$civiToMautic) {
        $convertedContact[$setKey] = static::lookupMauticValue($getKey, $contactData);
      }
      else {
        if ($getKey == 'civicrm_contact_id') {
          $getKey = 'id';
        }
        $convertedContact[$setKey] = CRM_Utils_Array::value($getKey, $contactData);
      }
    }
    return $convertedContact;
  }

  public static function convertToMauticContact($contact, $includeTags = FALSE) {
    $mauticContact = static::convertContact($contact, TRUE);
    if ($includeTags) {
      $tagHelper = new CRM_Mautic_Tag();
      if ($tagHelper->isSync()) {
        $tagHelper->setData($contact, $mauticContact);
        $mauticContact['tags'] = $tagHelper->getCiviTagsForMautic($contact['id']);
      }
    }
    self::commsPrefsCiviToMautic($contact, $mauticContact);
    return $mauticContact;
  }

  /**
   * Converts mautic contact data to values for a civicrm contact.
   *
   * @param mixed $mauticContact
   * @return mixed[]|array|string[]
   */
  public static function convertToCiviContact($mauticContact, $includeTags = FALSE) {
    $contact = static::convertContact($mauticContact, FALSE);
    self::commsPrefsMauticToCivi($mauticContact, $contact);
    unset($contact['civicrm_contact_id']);
    return $contact;
  }

  /**
   * Saves tags from a Mautic to a CiviCRM contact.
   * @param mixed $mauticContact
   * @param int $contactId
   */
  public static function saveMauticTagsToCiviContact($mauticContact, $contactId) {
    // Get the tags in the mautic contact.
    $tags = self::lookupMauticValue('tags', $mauticContact);
    $tagNames = $tags ? array_map(function($t) { return $t->tag;}, $tags) : [];
    $tagHelper = new CRM_Mautic_Tag();
    $tagHelper->saveContactTags($tagNames, $contactId);
  }
}