<?php
use CRM_Mautic_ExtensionUtil as E;

/**
 * Mautic.Getchecksum API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_mautic_Getchecksum_spec(&$spec) {
  $spec['id']['api.required'] = 1;
}

/**
 * Mautic.Getchecksum API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_mautic_Getchecksum($params) {

  if (array_key_exists('id', $params)) {
    $returnValues = [];
    $cids = [];
    if (is_numeric($params['id'])) {
      $cids = [$params['id']];
    }
    elseif (!empty($params['id']['IN'])) {
      $cids = $params['id']['IN'];
    }
    elseif (is_array($params['id'])) {
      $cids = $params['id'];
    }
    foreach ($cids as $cid) {
      if (!is_numeric($cid)) {
        continue;
      }
      $returnValues[$cid] = CRM_Contact_BAO_Contact_Utils::generateChecksum($cid);
    }
    return civicrm_api3_create_success($returnValues, $params, 'Mautic', 'Getchecksum');
  }
  else {
    throw new API_Exception('The id parameter is required');
  }
}
