<?php
use CRM_Mautic_ExtensionUtil as E;
use CRM_Mautic_Connection as MC;
/**
 * A page for general set-up for integration with Mautic.
 * 
 * The page is divided into sections which 
 * report on status and may also provide a button to complete an action.
 */
class CRM_Mautic_Page_Connection extends CRM_Core_Page {
  
  protected $isConnectedToMautic = NULL;
  
  protected $action = '';
  
  protected $actionKey = 'doAction';
  
  protected $mauticVersion = '';
  
  public function run() {
    
    CRM_Utils_System::setTitle(E::ts('Mautic Integration'));
    $this->action = CRM_Utils_Request::retrieve($this->actionKey, 'String',  CRM_Core_DAO::$_nullObject, FALSE, NULL, 'GET');
    
    $sections = [];
    
    $sections['connection_content'] = $this->connectionContent();
    if ($this->isConnectedToMautic) {
      $sections['webhook_content'] = $this->webhookContent();
      $sections['fields_content'] = $this->fieldsContent();
      $civirules = $this->civirulesContent();
      if ($civirules) {
        $sections['civirules_content'] = $civirules;
      }
    }
    
    $this->assign('sections', $sections); 
    parent::run();
  }
 
  /**
   * Return HTML for a self-linking button.
   * 
   * @param string $label
   * @param array $params
   * @return string
   */
  protected function buttonLink($label, $params, $path = '') {
    $path = $path ? $path : CRM_Utils_System::currentPath();
    $url = CRM_Utils_System::url(
      $path,
      $params,
      TRUE,
      NULL,
      FALSE,
      FALSE,
      TRUE
    );
    return <<< EOT
    <div class="crm-inline-button">
      <span class="crm-button">
         <a class="crm-button button" href="$url">$label</a>
      </span>
     </div>
EOT;
  }
  
  protected function connectionContent() {
    $section = $this->blankSection(E::ts('Connection'));
     
    $authMethod = MC::singleton()->getAuthMethod(); 
    $authMethodLabel = $authMethod 
     ? CRM_Mautic_Setting::getLabel('mautic_connection_authentication_method', $authMethod)
     : E::ts('None set');

    $mauticUrl = MC::singleton()->getBaseUrl(); 
    
    // Check connection and redirect to the Authorization workflow if needed.
    $authAction = 'mautic_authorization';
    $doAuthorizationRedirect = $this->action == $authAction;
    $this->isConnectedToMautic = $this->checkConnection($doAuthorizationRedirect);
    
    if ($this->isConnectedToMautic) {
      $this->isConnectedToMautic = TRUE;
      $section['content'] .= '<p><strong>' . E::ts('Connection to Mautic Successful.') . '</strong></p>';
      $section['content'] .= $this->labelValue(E::ts('Mautic URL'), $mauticUrl); 
      $section['content'] .= $this->labelValue(E::ts('Mautic Version'), $this->mauticVersion); 
      $section['content'] .= $this->labelValue(E::ts('Connection Method'), $authMethodLabel);
    }
    elseif (0 === strpos(strtolower($authMethod), 'oauth') && empty($_GET['oauth_token'])) {
      // Add button for the user to authorize.
      $section['content'] .= '<p>' . E::ts('You need to authorize with Mautic to establish a connection.') . '</p>';
      $section['action'] .= $this->buttonLink(E::ts('Authorize with Mautic'), [$this->actionKey => 'mautic_authorization']);
    }
    else {
      // Settings. 
       $section['help'] .= '<p>' . E::ts(
           'Could not connect to Mautic. Please <a href="%1">check your settings</a>.', [
             1 => CRM_Utils_System::url(
                 'civicrm/admin/mautic/settings'),
           ]) . '</p>'; 
       $section['help'] .= '<p>' . E::ts('Check your <a target="blank" href="%1">Mautic installation</a>:', 
           [1 => $mauticUrl]
           ) . '</p>';
       // Some general troublehooting tips, in no way complete.
       $items = [];
       $items[] =  E::ts('Ensure the API is enabled.');
       $items[] = E::ts('The API mode matches the authentication method in your CiviCRM settings.');
       $items[] =  E::ts('Your credentials in CiviCRM match those set in the Mautic API');
       $items[] =  E::ts('The Mautic cache was cleared after setting the API.');
       $section['help'] .= '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';
    }
    return $section;
  }
  
  
 /**
  * Get a structure to start a page section.
  * 
  * @param string $title
  * @return string[]
  */ 
  protected function blankSection($title = '') {
    return [
      'title' => $title,
      'help' => '',
      'content' => '',
      'action' => '',
    ];
  }
 
  /**
   * Return HTML to format informational content with a label and value.
   * 
   * @param string $label
   * @param string $value
   * @return string
   */
  protected function labelValue($label, $value) {
    return <<< EOF
<div class="container">
  <div class="label"> $label </div>
  <div class="content"> $value </div>
  <div class="break"></div>
</div>
EOF;
  }
  
  
  protected function fieldsContent() {
    $section = $this->blankSection(E::ts('Field'));
    $section['help'] .= E::ts('CiviCRM adds a Contact Field on Mautic to identify contacts.');
    $fieldApi = MC::singleton()->newApi('contactFields');
    $createdField = NULL;
    $civiField = [
      'alias' => CRM_Mautic_Contact_ContactMatch::mauticIdFieldAlias,
      'label' => 'CiviCRM Contact ID',
      'type' => 'text',
      'description' => 'CiviCRM Contact ID on ' . CRM_Utils_System::baseCMSURL(),
      'isPubliclyUpdatable' => FALSE,
     // 'group' => 'civicrm',
    ];
    $alias = [];
    if ($fieldApi) {
      $fields = $fieldApi->getList();

      foreach ($fields['fields'] as $field) {
        $alias[$field['id']] = $field['alias'];
        if ($field['alias'] == $civiField['alias']) {
          $createdField = $field;
          break;
        }
      }
      if (!$createdField) {
        $createdField = $fieldApi->create($civiField);  
      }
    }
    if (!empty($createdField['errors'])) {
        $section['content'] = E::ts("There was a problem creating field on Mautic.");
    }
    elseif ($createdField) {
      $section['content'] =  $this->labelValue(E::ts('Mautic Field:'), E::ts('%1 created on Mautic on %2', [ 
        $createdField['label'],
        date_format(date_create($createdField['dateAdded']), 'd-m-Y'),
      ]));
    }
    return $section;
  }
 
  /**
   * Get status content for CiviRules triggers, conditions and actions.
   * 
   * If CiviRules was installed after this extension, then insert the items so CiviRules knows about them.
   */
  public function civirulesContent() {
    $section = [];
    // Check CiviRules is installed and its upgrader is available.
    if (class_exists('CRM_Civirules_Utils_Upgrader')) {
      $section = $this->blankSection(E::ts('CiviRules'));
      $action = 'register_civirules_components';
      $ruleComponents = [
        'trigger' => 'insertTriggersFromJson',
        'condition' => 'insertConditionsFromJson',
        'action' => 'insertActionsFromJson',
      ];
      $missing = FALSE;
      $content = '';
      // Are components already inserted?
      foreach ($ruleComponents as $component => $insertMethod) {
        $filePath = E::path('sql/civirules/' . $component . 's.json');
        $items = json_decode(file_get_contents($filePath));
        if (!$items) {
          continue;
        }
        $names = array_filter(array_map(function($val) {
          return $val->name ? $val->name : '';
        }, $items));
        // Get names we want to register
        $table = 'civirule_' . $component;
        $sql = "SELECT name FROM $table WHERE name IN ('" . implode('\', \'', $names) . "')";
        $dao = CRM_Core_DAO::executeQuery($sql);
        $insertedNames = $dao->fetchAll();
        if (count($insertedNames) != count($names)) {
          if ($this->action == $action) {
            call_user_func(['CRM_Civirules_Utils_Upgrader', $insertMethod], $filePath);
          }
          else {
            $missing = TRUE;
          }
        }
        $content .= $this->labelValue(
            ucwords($component) . '(s):',
            implode(', ', array_map(function($val){ return '<em>&quot;' . $val->label . '&quot;</em>';}, $items)));
      }
      if ($missing) {
        $section['content'] = E::ts('Click button below to make available CiviRules Triggers, Conditions and Actions provided by this extension.');
        $section['action'] = $this->buttonLink(
           E::ts('Enable CiviRules Items'),
           [$this->actionKey => $action]
        );
      }
      else {
        $section['content'] = $content;
      }
    }
    return $section;
  }
  
  /**
   * Gets status and action for webhooks.
   * 
   * @return string[]
   */
  public function webhookContent() {
    $section = $this->blankSection(E::ts('Webhook'));
    $action = 'register_webhooks';
    $key = CRM_Mautic_WebHook::getKey();
    if (!$key) {
      $key = CRM_Mautic_WebHook::generateKey();
      CRM_Mautic_Setting::set('mautic_webhook_security_key', $key);
    }
    // Key info.
    $section['content'] .= '<div class="crm-section webhook-key">
       <div class="label"> Webhook Security Key: </div>
       <div class="content"> ' . $key . '</div>
       <div class="clear"></div>
       </div>';
     
    if ($this->action == $action) {
      CRM_Mautic_Webhook::fixMauticWebHooks();
    }
    $hookData = CRM_Mautic_Webhook::validateWebhook();
    if (!empty($hookData['valid'])) {
      $webhook = reset($hookData['valid']);
      $section['content'] .= E::ts('Webhook: %1 created on Mautic on %2',[
        1 => $webhook['name'],
        2 => date_format(date_create($webhook['dateAdded']), 'd-m-Y'),
      ]);
    }
    elseif ($this->isConnectedToMautic) {
      $section['content'] .= E::ts('A valid Webhook has not been found on the Mautic installation.');
      $section['action'] = $this->buttonLink(
          E::ts('Create Webhook'),
          [$this->actionKey => $action]
       );
    }
    return $section;
  }
  
  
  /**
   * Checks the Mautic connection.
   * 
   * @param boolean $doAuthorizationRedirect
   *  If set, then the user will be redirected to authorize.
   * 
   * @return Bool
   */
  public function checkConnection($doAuthorizationRedirect = FALSE) {
    // If we are authorizing, clear out existing token data.
    if ($doAuthorizationRedirect) {
      MC::singleton()->clearAccessToken();
      unset($_SESSION['oauth']);
    }
    $auth = MC::singleton()->getAuth($doAuthorizationRedirect);
    // $auth->enableDebugMode();
    
    // Make an api request to test the connection.
    // The choice of context is quite arbitrary.
    $testApi = MC::singleton()->newApi('segments');
    if ($testApi) {
      // Just retrieve one entity for this check.
      $res = $testApi->getList('', 0, 1);
      $responseInfo = $auth->getResponseInfo();
      $this->mauticVersion = $testApi->getMauticVersion();
      return 200 == $responseInfo['http_code'];
    }
    return FALSE;
  }
}
