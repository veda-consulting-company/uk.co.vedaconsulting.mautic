<?php 

class CRM_Mautic_Contact_ContactMatch {
  
  /**
   * The alias for the field created on Mautic.
   */
  public const mauticIdFieldAlias = 'civicrm_contact_id';
  
  /**
   * Get the custom field ID 
   */
  public static function getMauticContactID($contact) {
    
  }
  
  /**
   * Attempt to find a CiviCRM contact from Mautic contact data.
   * @param [] $mauticContact
   */
  public static function getCiviFromMauticContact($mauticContact) {
    // Use custom mautic field.
    
    // Else use unsupervised dedupe.
    
    
  }
  
  public static function getMauticFromCiviContact($contact) {
    // Use custom field.
    // Use email match.
  }
    
}