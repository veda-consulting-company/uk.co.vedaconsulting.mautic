<?php
use CRM_Mautic_ExtensionUtil as E;
/**
 * @class
 *
 * Provides utility functions for getting extension settings.
 *
 */
class CRM_Mautic_Setting {

  /**
   * Get metaData for the settings defined by this extension.
   *
   * @return array
   */
  public static function getSettingMetaData() {
    static $settingMeta = [];
    if (!$settingMeta) {
      $result = civicrm_api3('Setting', 'getfields',[
        'filters' => ['group' => E::SHORT_NAME],
      ]);
      $settingMeta = array_filter(CRM_Utils_Array::value('values', $result, []), function($item) {
        return !empty($item['html_type']);
      });
    }
    return $settingMeta;
  }

  /**
   * Get all extension settings.
   *
   * @return array
   */
  public static function getAll() {
    static $settings = [];
    if (!$settings) {
      foreach (self::getSettingMetaData() as $name => $data) {
        $settings[$name] = Civi::settings()->get($name);
      }
    }
    return $settings;
  }

  /**
   * Get the human-readable label for a setting or setting option.
   *
   * @param string $name
   * The setting name.
   *
   * @param string $optionValue
   * If passed, the label of the option will be
   * returned instead of the setting title.
   *
   */
  public static function getLabel($name, $optionValue = NULL) {
    $meta = CRM_Utils_Array::value($name, self::getSettingMetaData(), []);
    if ($optionValue) {
      return CRM_Utils_Array::value($optionValue, $meta['options']);
    }
    return CRM_Utils_Array::value('title', $meta);
  }

  /**
   * Checks for missing Setting values.
   *
   * @param [] $settingValues
   */
  public static function validate($settingValues, &$invalid, $required = []) {
    foreach (self::getSettingMetaData() as $name => $data) {
      if (!empty($data['is_required']) || in_array($name, $required)) {
        if (!isset($settingValues[$name]) || ($data['type'] != 'Boolean' && empty($settingValues[$name]))) {
          $invalid = $name;
        }
      }
    }
    return empty($invalid);
  }
}
