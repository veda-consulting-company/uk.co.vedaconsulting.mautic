<?php

use CRM_Mautic_Connection as MC;

class CRM_Mautic_Utils {

  protected static $segmentData = [];

  /**
   * If we are updating a CiviCRM contact from Mautic, we do not want to trigger a sync going back to Mautic.
   * @var boolean
   */
  public static $skipUpdatesToMautic = FALSE;

  public static function getContactCustomFieldInfo($fieldName = NULL) {
    static $fieldInfo = [];
    $groupName = 'Mautic_Contact';
    if (!$fieldInfo) {
      $result = self::civiApi('CustomField', 'get', [
        'custom_group_id' => $groupName,
      ]);
      if ($result['values']) {
        // Key by name for easier lookup.
        foreach ($result['values'] as $field) {
          $fieldInfo[$field['name']] = $field;
        }
      }
    }
    return $fieldName ? CRM_Utils_Array::value($fieldName, $fieldInfo) : $fieldInfo;
  }

  /**
   * Save the Mautic Contact ID to CiviCRM custom field.
   * @param [] $contact
   *  Should have id element.
   * @param [] $mauticId
   */
  public static function saveMauticIDCustomField($contact, $mauticId) {

    $contactId = CRM_Utils_Array::value('id', $contact);
    $fid = CRM_Core_BAO_CustomField::getCustomFieldID('Mautic_Contact_ID', 'Mautic_Contact');
    $key = 'custom_' . $fid;
    $savedMauticId = CRM_Utils_Array::value($key, $contact);
    if ($contactId && $mauticId != $savedMauticId) {
      // Prefer to save customValue directly rather than update Contact.
      // This may be called from a contact update rule trigger.
      $update = self::civiApi('CustomValue', 'create', [
        'entity_id' => $contactId,
        'custom_' . $fid => $mauticId,
      ]);
      self::checkDebug(__FUNCTION__, ['updated' => $update, 'contact' => $contact]);
    }
  }


  /**
   * Gets Mautic Segments in [id] => label format.
   * @return string[]
   */
  public static function getMauticSegmentOptions() {
    $options = [];
    foreach (self::getMauticSegments() as $segment) {
      $options[$segment['id']] = $segment['name'];
    }
    return $options;
  }

  /**
   * Fetches segment data from the api.
   */
  public static function getMauticSegments() {
    if (!self::$segmentData) {
      $segmentsApi = MC::singleton()->newApi('segments');
      if ($segmentsApi) {
        $segments = $segmentsApi->getList();
        if (!empty($segments['lists'])) {
          self::$segmentData = $segments['lists'];
        }
      }
    }
    return self::$segmentData;
  }

  public static function getCiviGroupFromMauticSegment() {

  }

  public static function getMauticSegmentFromCiviGroup() {

  }

  /**
   * Sync Mautic Segment memberships based on CiviCRM Contact's group membership.
   */
  public static function syncContactSegmentsFromGroups($contactID, $mauticContactID = NULL) {
    if (!$mauticContactID) {
      $mauticContactID = CRM_Mautic_Contact_ContactMatch::getMauticFromCiviContact(['id' => $contactID]);
    }
    if (!$mauticContactID) {
      return;
    }
    // Contact's current groups.
    $contact = self::civiApi('Contact', 'get', [
      'return' => 'group',
      'id' => $contactID,
    ]);
    $groups = !empty($contact['values'][$contactID]['groups'])
      ? array_filter(explode(',', $contact['values'][$contactID]['groups']))
      : [];
    $segmentsToSync = array_map(function($val) {
       return $val['segment_id'];
     },
     self::getGroupsToSync($groups));
    $contactApi = MC::singleton()->newApi('contacts');
    // Contact's current segments.
    $segments = $contactApi->getContactSegments($mauticContactID);
    $segments = !empty($segments['lists']) ? $segments['lists'] : [];
    $currentSegments = $segments ? array_map(function($val) {
        return $val['id'];
      }, $segments)
      : [];

    // Contacts synched groups.
    $toRemove = array_diff($currentSegments, $segmentsToSync);
    $toAdd = array_diff($segmentsToSync, $currentSegments);
    if ($toRemove || $toAdd) {
      $segmentApi = MC::singleton()->newApi('segments');
      foreach ($toAdd as $sid) {
        $segmentApi->addContact($sid, $mauticContactID);
      }
      foreach ($toRemove as $sid) {
       $segmentApi->removeContact($sid, $mauticContactID);
      }
    }
  }


  /**
   * Convenience function to get details on a Mautic Segment.
   * @param int $segmentId
   * @param string $property
   *  Name of property to return. If not set, will return all properties in an associative array.
   *
   * @return mixed|array
   */
  public static function getMauticSegment($segmentId, $property = NULL) {
    $segment = CRM_Utils_Array::value($segmentId, self::getMauticSegments(), []);
    if ($property) {
      return CRM_Utils_Array::value($property, $segment, '');
    }
    return $segment;
  }


  /**
   * Look up an array of CiviCRM groups linked to Mautic segments.
   *
   * @param $groupIDs mixed array of CiviCRM group Ids to fetch data for; or empty to return ALL mapped groups.
   * @param $mauticSegmentId mixed Fetch details for a particular segment only, or null.
   * @return array keyed by CiviCRM group id whose values are arrays of details
   */
  public static function getGroupsToSync($groupIDs = [], $mauticSegmentId = NULL) {

    $params = $groups = $temp = [];
    $groupIDs = array_filter(array_map('intval',$groupIDs));

    if (!empty($groupIDs)) {
      $groupIDs = implode(',', $groupIDs);
      $whereClause = "entity_id IN ($groupIDs)";
    } else {
      $whereClause = "1 = 1";
    }
    $whereClause .= " AND mautic_segment_id IS NOT NULL AND mautic_segment_id <> ''";

    if ($mauticSegmentId) {
      // just want results for a particular MC list.
      $whereClause .= " AND mautic_segment_id = %1 ";
      $params[1] = array($mauticSegmentId, 'String');
    }

    $query  = "
      SELECT
        entity_id,
        mautic_segment_id,
        cg.title as civigroup_title,
        cg.saved_search_id,
        cg.children
      FROM   civicrm_value_mautic_settings m
      INNER JOIN civicrm_group cg ON m.entity_id = cg.id
      WHERE $whereClause";
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    while ($dao->fetch()) {
      $segment = self::getMauticSegment($dao->mautic_segment_id);
      $groups[$dao->entity_id] = [
          // Mautic Segment
          'segment_id' => $dao->mautic_segment_id,
          'segment_name' => CRM_Utils_Array::value('name', $segment),
          // Details from CiviCRM
          'civigroup_title' => $dao->civigroup_title,
          'civigroup_uses_cache' => (bool) (($dao->saved_search_id > 0) || (bool) $dao->children),
       ];
    }
    CRM_Mautic_Utils::checkDebug( __CLASS__ . __FUNCTION__ . '$groups', $groups);
    return $groups;
  }

  /**
   * Log a message and optionally a variable, if debugging is enabled.
   */
  public static function checkDebug($description, $variable='VARIABLE_NOT_PROVIDED') {
    $debugging = CRM_Mautic_Setting::get('mautic_enable_debugging');

    if ($debugging == 1) {
      if ($variable === 'VARIABLE_NOT_PROVIDED') {
        // Simple log message.
        CRM_Core_Error::debug_log_message($description, FALSE, 'mautic');
      }
      else {
        // Log a variable.
        CRM_Core_Error::debug_log_message(
            $description . "\n" . var_export($variable,1)
            , FALSE, 'mautic');
      }
    }
  }

  /**
   * Wraps civiCRM api.
   *
   * @param unknown $entity
   * @param unknown $method
   * @param unknown $params
   * @return array
   */
  public static function civiApi($entity, $method, $params) {
    try {
      $result = civicrm_api3($entity, $method, $params);
      return $result;
    }
    catch (Exception $e) {
      CRM_Core_Error::debug_var('API Error: ' . __CLASS__, [$e->getMessage(), $params]);
    }
  }

}
