<?php 
use CRM_Mautic_Connection as MC;
use CRM_Mautic_ExtensionUtil as E;
use CRM_Mautic_Utils as U;

/**
 * @class
 * 
 * Contains utility functionality for Mautic Webhook.
 * 
 * Includes creating a hook on Mautic and processing the incoming hooks.
 * 
 */
class CRM_Mautic_WebHook {
  
 /**
  * @var string
  */ 
  private const webhookBaseUrl = 'civicrm/mautic/webhook';
  
  /**
   * @var string
   */
  private const webhookName = 'CiviCRM_Mautic';
  
  /**
   * 
   */
  private const activityType = 'Mautic_Webhook_Triggered'; 
  
  /**
   * Get the Mautic events for which our webhooks will listen.
   * 
   * @return array
   */
  public static function getEnabledTriggers() {
    $triggers = CRM_Mautic_Setting::get('mautic_webhook_trigger_events');
    sort($triggers);
    return $triggers;
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
  
  public static function identifyContact($contact) {
   // @todo: Add setting to select dedupe rule to identify
   // incoming contact data.
      
    $cid = NULL;
    // $contact is a Mautic contact in a std object.
    if (!$contact) {
      return;
    }
  }
  
  public static function processEvent($trigger, $data) {
    
    $civicrmContactId = NULL;
    $eventTrigger = str_replace('mautic.', '', $trigger);
    // Data may include lead and contact properties - they appear to be the same.
    $contact = !empty($data->contact) ? $data->contact : NULL;
    CRM_Core_Error::debug_var("webhook trigger", $eventTrigger);
    if (!$eventTrigger || !$contact ) {
     CRM_Core_Error::debug_log_message("Processing Mautic webhook: no contact data, exiting.");
      return;
    } 
    
    if (!empty($contact->fields->core->civicrm_contact_id->value)) {
      $civicrmContactId = $contact->fields->core->civicrm_contact_id->value;
    }
    // It would be good here to use a wrapper to normalize access to contact fields.
    
    if ($civicrmContactId && $eventTrigger == 'lead_post_save_new') {
      // If this is a new contact then it was created by Civi.
      // Ignore, it may be from a bulk sync.
      return;
    }
    CRM_Core_Error::debug_var("Processing Mautic webhook item:", ['trigger' => $eventTrigger, 
      'contact_id' => $civicrmContactId, 
      'mauticcontactid' => $contact->id]
        );
    
    
    if (!$civicrmContactId) {
      $civicrmContactId = self::identifyContact($contact);
    }
    // We have extracted enough information for an action. 
    if ($civicrmContactId) {
      // Create an activity for this contact.
      self::createActivity($eventTrigger, $contact, $civicrmContactId);
    }
    else {
      // @todo: setting to determine what to do here.
      // Could create a new contact?
      // Possibly expose in CiviRules conditions.
      U::checkDebug("Webhook: no matching contact found for trigger: " . $trigger);
      return;
    }
  }
  
  public static function processWebHookPayload($data) {
    CRM_Core_Error::debug_log_message("Processing Mautic webhook.");
    CRM_Core_Error::debug_var('triggerdatakeys', array_keys((array)$data));
    $triggers = self::getEnabledTriggers();
    foreach ($triggers as $trigger) {
      CRM_Core_Error::debug_log_message("trying trigger" . $trigger);
      if (!empty($data->{$trigger})) {
        CRM_Core_Error::debug_log_message("found data at " . $trigger);
        // We may be processing a batch.
        if (is_array($data->{$trigger})) {
          foreach ($data->{$trigger} as $idx => $item) {
            self::processEvent($trigger, $item);
          }
        }
      }
    }
    if (!$trigger) {
      CRM_Core_Error::debug_log_message("Mautic Webhook: Trigger not found.");
    }
  }
  
  public static function createActivity($trigger, $mauticContact, $cid, $data=NULL) {
    CRM_Core_Error::debug_log_message("Creating Mautic Webhook Activity");
    // We expect this to be called only om WebHook url.
    // So won't bother catching exceptions.
    $fieldInfo = [];
    $fieldResult = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'custom_group_id' => "Mautic_Webhook_Data",
    ]);
    foreach ($fieldResult['values'] as $field) {
      $fieldInfo[$field['name']] = $field['id'];
    }
    CRM_Core_Error::debug_var('fieldInfo', [$fieldInfo]);
    $params = [
      'activity_type_id' => self::activityType,
      // We could have a more descriptive subject.
      'subject' => E::ts('Mautic Webhook Triggered'),
      'source_contact_id' => $cid,
      'custom_' . $fieldInfo['Trigger_Event'] => $trigger,
      'custom_' . $fieldInfo['Data'] => json_encode($mauticContact),
    ];
    CRM_Core_Error::debug_var('creating activity', $params);
    civicrm_api3('Activity', 'create', $params); 
  }
  
  /**
   * Gets the webhooks from the Mautic installation with callbacks at current host.
   * 
   * @return array
   */
  public static function getMauticWebhooks() {
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
    $hooks = CRM_Utils_Array::value('hooks', $list);
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
  public static function validateWebhook() {
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
  public static function fixMauticWebhooks() {
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
    return CRM_Mautic_Setting::get('mautic_webhook_security_key');
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
