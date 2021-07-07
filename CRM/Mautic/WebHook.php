<?php
use CRM_Mautic_Connection as MC;
use CRM_Mautic_ExtensionUtil as E;
use CRM_Mautic_Utils as U;

/**
 * @class
 *
 * Contains utility functionality for Mautic Webhook.
 */
class CRM_Mautic_WebHook {

 /**
  * @var string
  */
  protected const webhookBaseUrl = 'civicrm/mautic/webhook';

  /**
   * @var string
   */
  public const webhookName = 'CiviCRM_Mautic';

  /**
   *
   */
  public const activityType = 'Mautic_Webhook_Triggered';

  /**
   * Get the Mautic events for which our webhooks will listen.
   *
   * @return array
   */
  public static function getEnabledTriggers() {
    return \Civi::settings()->get('mautic_webhook_trigger_events');
  }

  public static function getTriggerLabel($trigger) {
    $prefix = 'mautic.';
    if (0 !==  strpos($trigger, $prefix)) {
      $trigger = $prefix . $trigger;
    }
    return CRM_Utils_Array::value($trigger, static::getAllTriggerOptions());
  }

  public static function getAllTriggerOptions() {
    return [
     // Contact Channel Subscription Change Event.
     'mautic.lead_channel_subscription_changed' => E::ts('Contact Channel Subscription Change Event'),
     // Contact Deleted Event.
     'mautic.lead_post_delete' => E::ts('Contact Deleted Event'),
     // Contact Identified Event.
     'mautic.lead_post_save_new' => E::ts('Contact Identified Event'),
     // Contact Points Changed Event
     'mautic.lead_points_change' => E::ts('Contact Points Changed Event'),
      // Contact Updated Event.
     'mautic.lead_post_save_update' => E::ts('Contact Updated Event'),
      // Email Open Event.
      'mautic.email_open' => E::ts('Email Open Event'),
      // Email Send Event.
      'mautic.email_send' => E::ts('Email Send Event'),
      // Form Submit Event.
      'mautic.form_submit' => E::ts('Form Submit Event'),
      // Page Hit Event.
      'mautic.page_on_hit' => E::ts('Page Hit Event'),
      // Text Send Event,
      'mautic.text_send' => E::ts('Text Send Event'),
    ];
  }

  /**
   * Gets the webhooks from the Mautic installation with callbacks at current host.
   *
   * @return array
   */
  public static function getMauticWebHooks() {
    $webhooksApi = MC::singleton()->newApi('webhooks');
    // Get webhooks registered from this URL.
    // Match on host and path, without schema or key.
    $list = $webhooksApi->getList(
        $searchFilter = 'is:mine',
        $start = 0,
        $limit = 0,
        $orderBy = 'id',
        $orderByDir = 'ASC',
        $publishedOnly = TRUE,
        $minimal = FALSE
     );
    $hooks = CRM_Utils_Array::value('hooks', $list, []);
    $urlParts = parse_url(self::getWebhookUrl(FALSE));
    $port = !empty($urlParts['port']) ? ':' . $urlParts['port'] : '';
    $urlPattern = $urlParts['host'] . $port . $urlParts['path'];
    return array_filter($hooks, function($hook) use($urlPattern) {
      return !empty($hook['isPublished']) && preg_match('#^http(s)?://' . $urlPattern . '#', $hook['webhookUrl']);
    });
  }


  /**
   * Determines whether webhooks require changing or creating on Mautic.
   * @return boolean
   */
  public static function hooksAreOK() {
    $hooks = self::validateWebhook();
    return count($hooks['valid']) == 1 && empty($hooks['invalid']);
  }

  /**
   * Validates the webhooks on the Mautic installation.
   *
   * @return array[]
   *  Associative array with keys:
   *   - invalid: Array of invalid hooks, including excess hooks.
   *   - valid: Array of valid hooks. Should not contain more than one element.
   **/
  public static function validateWebHook() {
    $hooks = self::getMauticWebhooks();
    // We are only interested in particular properties.
    $compareKeys = array_flip(['isPublished', 'webhookUrl']);
    $template = self::templateWebHook();
    $compare1 = array_intersect_key($template, $compareKeys);
    $triggers = $template['triggers'];
    $return = ['valid' => [], 'invalid' => []];
    foreach ($hooks as $key => $hook) {
      $compare2 = array_intersect_key($hook, $compareKeys);
      // Consider this Valid if:
      // - There isn't already another valid webhook.
      // - The properties we are interested in are correct.
      // - Triggers are correct.
      if (empty($return['valid']) && $compare2 == $compare1 && $triggers == $hook['triggers']) {
        $return['valid'][$key] = $hook;
      }
      else {
        $return['invalid'][$key] = $hook;
      }
    }
    return $return;
  }

  /**
   * Creates new webhooks and removes invalid hooks from the Mautic installation.
   *
   */
  public static function fixMauticWebHooks() {
    $hooks = self::validateWebhook();
    $api = MC::singleton()->newApi('webhooks');
    if (empty($hooks['valid']) && !empty(self::getEnabledTriggers())) {
      // No valid webhooks. Need to create them.
      $newHook = self::templateWebHook();
      $created = $api->create($newHook);
    }
    if (!empty($hooks['invalid'])) {
      // Delete invalid hooks.
      foreach($hooks['invalid'] as $hook) {
        $api->delete($hook['id']);
      }
    }
  }

  /**
   * Returns hook parameters for this extension.
   *
   * Used when validitating of existing
   * hooks or creating a new hook.
   *
   * @return string[]|boolean[]|array[]|string[][]
   */
  public static function templateWebHook() {
    return [
      'name' => self::webhookName,
      'webhookUrl' => self::getWebhookUrl(),
      'description' => E::ts('Created via API by %1.', [1 => E::LONG_NAME]),
      'eventsOrderbyDir' => 'ASC',
      'isPublished' => TRUE,
      'triggers' => self::getEnabledTriggers(),
    ];
  }

  /**
   * Returns the webhook URL.
   */
  public static function getWebhookUrl($includeQueryString = TRUE) {
    $security_key =  self::getKey();
    if (empty($security_key)) {
      throw new InvalidArgumentException("You have not set a security key for your Mautic integration. Please do this on the settings page at civicrm/mailchimp/settings");
    }
    $query = $includeQueryString ? 'reset=1&key=' . urlencode($security_key) : NULL;
    $webhook_url = CRM_Utils_System::url(
        self::webhookBaseUrl,
        $query,
        $absolute = TRUE,
        $fragment = NULL,
        $htmlize = FALSE,
        $fronteend = TRUE);

    return $webhook_url;
  }


  /**
   * Get webhook key.
   * @return mixed|NULL
   */
  public static function getKey() {
    return \Civi::settings()->get('mautic_webhook_security_key');
  }

  /**
   * Generate a new security key.
   */
  public static function generateKey() {
    $key = md5(uniqid(rand(), true));
    if (!empty($_POST['ajaxurl'])) {
      echo $key;
      CRM_Utils_System::civiExit();
    }
    return $key;
  }
}
