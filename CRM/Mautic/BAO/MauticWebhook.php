<?php
use CRM_Mautic_ExtensionUtil as E;

class CRM_Mautic_BAO_MauticWebhook extends CRM_Mautic_DAO_MauticWebhook {

  /**
   * Create a new MauticWebhook based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Mautic_DAO_MauticWebhook|NULL
   */
  public static function create($params) {
    $className = 'CRM_Mautic_DAO_MauticWebhook';
    $entityName = 'MauticWebhook';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    // Serialize data. API calls may need to serialize beforehand.
    if (!empty($params['data']) && !is_string($params['data'])) {
      $params['data'] = json_encode($params['data']);
    }
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);
    return $instance;
  }

  /**
   * Gets an entity from the webhook.
   *
   * @param string $mauticEntityType
   * @param array[] $webhookData
   *  API MauticWebhook data.
   *
   * @return []
   */
  public static function getProvidedData($mauticEntityType, $webhookData) {
    if (!empty($webhookData[$mauticEntityType])) {
      return $webhookData[$mauticEntityType];
    }
  }

}
