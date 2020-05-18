<?php

use CRM_Mautic_Connection as MC;

class CRM_Mautic_Utils {
  
  protected static $segmentData = [];
 
  /**
   * Gets Mautic Segments as a set of options.
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
    // TODO: use civicrm cache.
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
 
  /**
   * Convenience function to get details on a Mautic Segment.
   * @param int $segmentId
   * @return array
   */
  public static function getMauticSegment($segmentId) {
    return CRM_Utils_Array::value($segmentId, self::getMauticSegments(), []);
  }
  
  /**
   * Returns the webhook URL.
   */
  public static function getWebhookUrl() {
    // FIXME: url is not registered.
    // FIXME: mautic webhooks are via events. What data is sent?
    $security_key =  Civi::settings()->get('mautic_webhook_security_key');
    if (empty($security_key)) {
      // @Todo what exception should this throw?
      throw new InvalidArgumentException("You have not set a security key for your Mailchimp integration. Please do this on the settings page at civicrm/mailchimp/settings");
    }
    $webhook_url = CRM_Utils_System::url('civicrm/mautic/webhook',
        $query = 'reset=1&key=' . urlencode($security_key),
        $absolute = TRUE,
        $fragment = NULL,
        $htmlize = FALSE,
        $fronteend = TRUE);
    
    return $webhook_url;
  }
  
  public static function 
  
  /**
   * Look up an array of CiviCRM groups linked to Maichimp groupings.
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
   // CRM_Mautic_Utils::checkDebug( __CLASS__ . __FUNCTION__ . '$groups', $groups);
    return $groups;
  }
  
  /**
   * Log a message and optionally a variable, if debugging is enabled.
   */
  public static function checkDebug($description, $variable='VARIABLE_NOT_PROVIDED') {
    $debugging = Civi::settings()->get('mautic_enable_debugging');
    
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
  
  
}