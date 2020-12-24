<?php
use CRM_Mautic_ExtensionUtil as E;

class CRM_Mautic_BAO_MauticWebHook extends CRM_Mautic_DAO_MauticWebHook {

  /**
   * Create a new MauticWebHook based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Mautic_DAO_MauticWebHook|NULL
   */
  public static function create($params) {
    $className = 'CRM_Mautic_DAO_MauticWebHook';
    $entityName = 'MauticWebHook';
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
   * @param string $webhookData
   *  API MauticWebHook data.
   *
   * @return []
   */
  public static function getProvidedData($mauticEntityType, $webhookData) {
    $data = json_decode($webhookData, TRUE);;
    if (!empty($data[$mauticEntityType])) {
      return $data[$mauticEntityType];
    }
  }

}
