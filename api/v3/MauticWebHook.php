<?php
use CRM_Mautic_ExtensionUtil as E;

/**
 * MauticWebHook.create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_mautic_web_hook_create_spec(&$spec) {
   $spec['webhook_trigger_type']['api.required'] = 1;
}

/**
 * MauticWebHook.create API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_mautic_web_hook_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * MauticWebHook.delete API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_mautic_web_hook_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * MauticWebHook.get API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_mautic_web_hook_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

function _civicrm_api3_mautic_web_hook_process_spec($params) {
  $params['id'] = [
    'api.required' => 0,
    'name' => 'ID of webhook in database',
    'description' => 'Optionally specify the ID to process a single webhook',
  ];
}

/**
 * @param array $params
 */
function civicrm_api3_mautic_web_hook_process($params) {
  if (!isset($params['processed_date'])) {
    $params['processed_date'] = ['IS NULL' => 1];
  }
  $webhooks = civicrm_api3('MauticWebHook', 'get', $params)['values'];
  $mauticWebhookHandler = new CRM_Mautic_WebHook_Handler();
  foreach ($webhooks as $webhook) {
    $mauticWebhookHandler->processEvent($webhook);
  }
  return civicrm_api3_create_success(count($webhooks), $params, 'MauticWebHook', 'process');
}
