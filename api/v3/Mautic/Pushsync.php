<?php
use CRM_Mautic_ExtensionUtil as E;

/**
 * Mautic.Pushsync API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_mautic_Pushsync_spec(&$spec) {
}

/**
 * Mautic.Pushsync API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_mautic_Pushsync($params) {
  // Do push from CiviCRM
  $dry_run = !empty($params['dry_run']);
  $runner = CRM_Mautic_Form_PushSync::getRunner($skipEndUrl = TRUE, $dry_run);
  if ($runner) {
    $result = $runner->runAll();
  }
  if (empty($result['is_error'])) {
    $log = '';
    $stats = \Civi::settings()->get('mautic_push_stats');
    foreach ($stats as $sid => $info) {
      $log .= "\n\n Segment: $sid; \n";
      if (!$info) {
        continue;
      }
      foreach ($info as $k => $v) {
        if (!isset($v) || !is_string($v)) {
          continue;
        }
        $log .= "$k: $v;\n";
      }
    }
    return civicrm_api3_create_success($log);
  }
  else {
    if (isset($result['exception']) && $result['exception'] instanceof Exception) {
      return civicrm_api3_create_error($result['exception']->getMessage());
    }
    return civicrm_api3_create_error('Unknown error');
  }
}
