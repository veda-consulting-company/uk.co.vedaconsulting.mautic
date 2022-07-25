<?php

use Civi\Api4\Contact;
use CRM_Mautic_Connection as MC;
use CRM_Mautic_Contact_FieldMapping as FieldMapping;
use CRM_Mautic_Contact_ContactMatch as ContactMatch;
use CRM_Mautic_Utils as U;

/**
 * @class
 * Functionality for pushing a single contact to Mautic.
 *
 *
 */
class CRM_Mautic_Contact_Contact {
  
  private $civicmContactID;

  private $civicrmContact = [];

  private $mauticAPI;
  
  /**
   * Create an instance.
   *
   * @param int $civicrmContactID
   */
  public function __construct($civicrmContactID) {
    $this->civicrmContactID = $civicrmContactID;
    $this->mauticAPI = MC::singleton()->newApi('contacts');
  }


  /**
   * Loads CiviCRM Contact data for a  Mautic push.
   *
   * @return array
   */
  public function getCiviCRMContact($reset = FALSE) {
    if (!$this->civicrmContact || $reset) {
      $fields = FieldMapping::getMapping();
      unset($fields['civicrm_contact_id']);
      $fields = array_merge(array_keys($fields), FieldMapping::getCommsPrefsFields());
      $this->civicrmContact = Contact::get(FALSE)
        ->addSelect(...$fields)
        ->addWhere('id', '=', $this->civicrmContactID)
        ->execute()
        ->first();
    }
    return $this->civicrmContact;
  }


  /**
   * Get the Mautic Contact ID.
   * 
   * @return int
   */
  public function getMauticContactID() {
    return ContactMatch::getMauticContactIDFromCiviContact($this->getCiviCRMContact());
  }


  /**
   * Push the contact to Mautic.
   *
   * @param bool $skipIfExists
   *  Whether to skip if the contact already exists.
   *
   * @param array $params
   *  Additonal property values for the Mautic contact.
   *  
   *  
   * @return int
   *  Mautic Contact ID
   */
  public function pushToMautic($skipIfExists = TRUE, $params = []) {
    $mauticID = $this->getMauticContactID();
    $contact = $this->getCiviCRMContact();
    $mauticContact = array_merge(FieldMapping::convertToMauticContact($contact), $params);

    U::$skipUpdatesToMautic = TRUE;
    if (!$mauticID) {
      $response = $this->mauticAPI->create($mauticContact);
      $mauticID = $response['contact']['id'];
      U::saveMauticIDCustomField($contact, $mauticID);
      $this->resetData();
    }
    elseif (!$skipIfExists) {
      $response = $this->mauticAPI->edit($mauticID, $mauticContact);
    }
    U::$skipUpdatesToMautic = FALSE;
    return $mauticID;
  }

  /**
   * Reset Contact Data.
   */
  private function resetData() {
    $this->civicrmContact = [];
  }

}
