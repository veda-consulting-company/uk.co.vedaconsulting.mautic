<?php

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\Address;
use Civi\Api4\Country;
use CRM_Mautic_ExtensionUtil as E;
use CRM_Mautic_Utils as U;

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
   * @var string[]
   *   The CiviCRM contact communication preferences fields
   */
  protected static $commsPrefFields = [
    'do_not_email',
    'do_not_phone',
    'do_not_mail',
    'do_not_sms',
    'do_not_trade',
    'is_opt_out'
  ];

  /**
   * Gets a set of civi to mautic field names.
   * @param bool $api4
   *   Whether to return the mapping for custom fields in API4 or API3 format
   *
   * @return string|array|string[]
   */
  public static function getMapping($api4 = TRUE) {
    $mapping = static::$defaultFieldMapping;
    // Map the custom field referencing the Mautic contact id.
    if ($api4) {
      $mapping['Mautic_Contact.Mautic_Contact_ID'] = 'id';
    }
    else {
      $mautic_field_info = CRM_Mautic_Utils::getContactCustomFieldInfo('Mautic_Contact_ID');
      if (!empty($mautic_field_info['id'])) {
        $mapping['custom_' . $mautic_field_info['id']] = 'id';
      }
    }
    return $mapping;
  }

  /**
   * @return string[]
   */
  public static function getCommsPrefsFields() {
    return self::$commsPrefFields;
  }

  /**
   * @param $data
   * @param string $civiFieldName
   * @param string $default
   *
   * @return mixed|string
   */
  public static function getValue($data, $civiFieldName, $default = '') {
    $values = $data['fields']['all'] ?? [];
    $mapping = self::$defaultFieldMapping;
    $key = !empty($mapping[$civiFieldName]) ? $mapping[$civiFieldName] : '';
    return $key && isset($values[$key]) ? $values[$key] : $default;
  }

  /**
   * Alter the converted contact communication prefs to include  subscription changes on Mautic.
   *
   * @param array $mauticContact
   * @param array $contact
   */
  public static function commsPrefsMauticToCivi($mauticContact, $contact) {
    // The doNotContact field appears to have an empty array when false and a nested empty array when true.
    // Not sure how to interpret this. So will use the channel and status property which is included
    // in the payload for webook mautic.lead_channel_subscription_changed.
    $channel = self::lookupMauticValue('channel', $mauticContact);
    if (!$channel) {
      return $contact;
    }
    $status = self::lookupMauticValue('new_status', $mauticContact);
    // Only make a change if channel is explicitly set.
    if (($channel === 'email') && $status) {
      // Can be contactable|manual.
      $contact['is_opt_out'] = $status != 'contactable';
      // We wont set do_not_email here.
    }
    return $contact;
  }

  /**
   * Sets doNotContact when Converting to a Mautic contact.
   *
   * @param array $civiContact
   * @param array $mauticContact
   */
  public static function commsPrefsCiviToMautic($civiContact, $mauticContact) {
    if (empty($civiContact['is_opt_out']) && empty($civiContact['do_not_email'])) {
      // Set email channel to contactable in Mautic
      $mauticContact['doNotContact'] = [];
    }
    elseif (!empty($civiContact['is_opt_out']) || !empty($civiContact['do_not_email'])) {
      // Set email channel to do not contact: email
       $mauticContact['doNotContact'][] = [
         'channel' => 'email',
         'reason' => Mautic\Api\Contacts::MANUAL,
       ];
    }
    return $mauticContact;
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
   * @param array $data
   *
   * @return mixed
   */
  public static function lookupMauticValue($key, $data) {
    if (isset($data[$key])) {
      return $data[$key];
    }
    if (isset($data['fields']['core'][$key]['value'])) {
      return $data['fields']['core'][$key]['value'];
    }
    return NULL;
  }

  /**
   * Converts between Mautic and CiviCRM values.
   *
   * @param array $contactData
   * @param boolean $civiToMautic
   *
   * @return array
   */
  protected static function convertContact($contactData, $civiToMautic = TRUE, $api4 = TRUE) {
    $mapping = static::getMapping($api4);
    CRM_Mautic_Utils::checkDebug(__FUNCTION__, ['contactData' => $contactData, 'fieldMapping' => $mapping]);
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
        $convertedContact[$setKey] = $contactData[$getKey] ?? NULL;
      }
    }
    return $convertedContact;
  }

  /**
   * @param array $contact
   *   CiviCRM contact
   * @param bool $includeTags
   *
   * @return array
   */
  public static function convertToMauticContact($contact, $includeTags = FALSE) {
    // Api4 Contact does not include email by default. We add it if it is missing.
    if (empty($contact['email']) && !empty($contact['id'])) {
      $email = \Civi\Api4\Email::get()
        ->addSelect('email')
        ->addWhere('contact_id', '=', $contact['id'])
        ->addWhere('is_primary', '=', TRUE)
        ->execute()
        ->first();
      $contact['email'] = $email['email'] ?? '';
    }
    $mauticContact = static::convertContact($contact, TRUE, TRUE);
    if ($includeTags) {
      $tagHelper = new CRM_Mautic_Tag();
      if ($tagHelper->isSync()) {
        $tagHelper->setData($contact, $mauticContact);
        $mauticContact['tags'] = $tagHelper->getCiviTagsForMautic($contact['id']);
      }
    }
    $mauticContact = self::commsPrefsCiviToMautic($contact, $mauticContact);
    return $mauticContact;
  }

  /**
   * Converts mautic contact data to values for a civicrm contact.
   *
   * @param array $mauticContact
   * @param bool $includeTags
   * @param bool $api4
   *   Whether to return custom fields in API4 format
   *
   * @return array
   */
  public static function convertToCiviContact($mauticContact, $includeTags = FALSE, $api4 = TRUE) {
    $contact = static::convertContact($mauticContact, FALSE, $api4);
    $contact = self::commsPrefsMauticToCivi($mauticContact, $contact);
    unset($contact['civicrm_contact_id']);
    return $contact;
  }

  /**
   * Save the Mautic contact address to the CiviCRM Contact primary address
   *
   * @param array $mauticContact
   * @param integer $contactID
   */
  public static function saveMauticAddressToCiviContact($mauticContact, $contactID) {
    $civiCRMToMauticMapping = [
      'street_address' => 'address1',
      'supplemental_address_1' => 'address2',
      'city' => 'city',
      'state_province_id:name' => 'state',
      'postal_code' => 'zipcode',
      'country_id' => 'country',
    ];

    foreach ($civiCRMToMauticMapping as $civiCRMKey => $mauticKey) {
      $addressValue = static::lookupMauticValue($mauticKey, $mauticContact);
      if (!empty($addressValue)) {
        switch ($mauticKey) {
          case 'country':
            $addressValue = Country::get(FALSE)
              ->addSelect('id')
              ->addWhere('name', '=', $addressValue)
              ->execute()
              ->first()['id'] ?? NULL;
            break;
        }
        if (!empty($addressValue)) {
          $address[$civiCRMKey] = $addressValue;
        }
      }
    }

    try {
      if (!empty($address)) {
        $existingAddress = Address::get(FALSE)
          ->addWhere('contact_id', '=', $contactID)
          ->addWhere('is_primary', '=', TRUE)
          ->execute()
          ->first();
        if (!$existingAddress) {
          Address::create(FALSE)
            ->setValues($address)
            ->addValue('contact_id', $contactID)
            ->addValue('is_primary', TRUE)
            ->execute()
            ->first();
        }
        else {
          Address::update(FALSE)
            ->addWhere('id', '=', $existingAddress['id'])
            ->setValues($address)
            ->execute();
        }
      }
    }
    catch (Exception $e) {
      \Civi::log()->error('Mautic error updating contact address. ' . $e->getMessage());
    }
  }

  /**
   * Saves tags from a Mautic to a CiviCRM contact.
   * @param array $mauticContact
   * @param int $contactId
   */
  public static function saveMauticTagsToCiviContact($mauticContact, $contactId) {
    // Get the tags in the mautic contact.
    $tags = self::lookupMauticValue('tags', $mauticContact);
    $tagNames = $tags ? array_map(function($t) { return $t['tag'];}, $tags) : [];
    $tagHelper = new CRM_Mautic_Tag();
    $tagHelper->saveContactTags($tagNames, $contactId);
  }

  /**
   * Have any of the core contact communication preferences changed?
   *
   * @param array $newContact
   * @param array $existingContact
   *
   * @return bool
   */
  public static function hasCiviContactCommunicationPreferencesChanged($newContact, $existingContact) {
    foreach (self::$commsPrefFields as $key) {
      $hasCommsPrefs = FALSE;
      if (array_key_exists($key, $newContact)) {
        $hasCommsPrefs = TRUE;
        break;
      }
    }

    if ($hasCommsPrefs && empty($newContact['id'])) {
      // We're creating a new contact which has comms preferences set.
      return TRUE;
    }

    // Check if any of the fields have changed from the existing contact
    foreach (self::$commsPrefFields as $key) {
      if (isset($newContact[$key]) && isset($existingContact[$key])
        && ((bool)$existingContact[$key] !== (bool)$newContact[$key])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Push the comms preferences ("Do not Contact" channels) from CiviCRM to Mautic
   * This is written to support additional channels in the future.
   *
   * @param \Mautic\Api\Contacts $api
   * @param int $mauticContactID
   * @param array $civicrmContact
   */
  public static function pushCommsPrefsToMautic($api, $mauticContactID, $civicrmContact) {
    if ((bool)$civicrmContact['do_not_email'] === TRUE) {
      $civicrmContact['is_opt_out'] = TRUE;
    }
    foreach (CRM_Mautic_Contact_FieldMapping::getCommsPrefsFields() as $field) {
      if (isset($civicrmContact[$field])) {
        switch ($field) {
          case 'is_opt_out':
            $channel = 'email';
            if ((bool) $civicrmContact[$field] === TRUE) {
              // We don't need to "add" because that happens automatically via contact push..
              // $api->addDnc($mauticContactID, $channel, \Mautic\Api\Contacts::MANUAL, NULL, 'via CiviCRM');
            }
            else {
              // ..but we can't remove a "do not contact" via standard API push
              // so have to call removeDnc explicitly
              $api->removeDnc($mauticContactID, $channel);
            }
            break;
        }
      }
    }
  }

  /**
   * Create a "Update Communication Preferences" activity when preferences are changed via Mautic and updated in
   * CiviCRM.
   *
   * @param array $civicrmContact
   *   The CiviCRM contact params that were updated
   * @param array $mauticContact
   *   The Mautic contact params that were sent by Mautic
   *
   * @return int|null
   * @throws \CiviCRM_API3_Exception
   */
  public static function createCommsPrefsActivity($civicrmContact, $mauticContact) {
    foreach (self::$commsPrefFields as $key) {
      if (isset($civicrmContact[$key])) {
        $commsPrefs[$key] = $civicrmContact[$key];
      }
    }
    if (empty($commsPrefs)) {
      return NULL;
    }

    try {
      $activity = Activity::create(FALSE)
        ->addValue('subject', 'Mautic')
        ->addValue('source_contact_id', $civicrmContact['id'])
        ->addValue('target_contact_id', $civicrmContact['id'])
        ->addValue('details', json_encode($commsPrefs, JSON_PRETTY_PRINT))
        ->addValue('activity_date_time', $mauticContact['dateModified'] ?? date('YmdHis'))
        ->addValue('status_id:name', 'Completed')
        ->addValue('activity_type_id:name', 'Update_Communication_Preferences')
        ->execute()
        ->first();
    }
    catch (Exception $e) {
      // Do nothing. If it fails we probably don't have GDPR extension installed so no "Update Communication
      // Preferences" activity.
      \Civi::log()->warning('Mautic unable to create "Update Communication Preferences" activity. ' . $e->getMessage());
    }

    U::checkDebug('Created "Update Communication Preferences" activity ' . $activity['id']);
    return $activity['id'] ?? NULL;
  }

}
