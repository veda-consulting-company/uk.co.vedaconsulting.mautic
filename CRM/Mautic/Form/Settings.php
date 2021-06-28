<?php

use CRM_Mautic_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Mautic_Form_Settings extends CRM_Admin_Form_Setting {

 /**
  * {@inherit}
  */
  public function buildQuickForm() {
    $this->setTitle(E::ts('Mautic Settings'));

    // Assign settings before calling parent.
    $this->_settings = $this->getExtensionSettings();
    parent::buildQuickForm();
    $this->applyFilter('mautic_connection_url', 'CRM_Mautic_Form_Settings::filterTrailingSlash');
    CRM_Core_Session::singleton()->pushUserContext(CRM_Utils_System::url('civicrm/admin/mautic/connection', 'reset=1'));

    $sections = $this->getFormSections();
    $this->assign('sections', $sections);
    $this->assign('elementNames', array_keys($this->_settings));
    $this->addRule('mautic_connection_url', E::ts('Please enter a valid URL'), 'url');
  }

  /**
   * {@inheritdoc}
   */
  public function postProcess() {
    parent::postProcess();
    $values = $this->exportValues();

    if ($values['mautic_sync_tag_method'] == 'sync_tag_children' && empty($values['mautic_sync_tag_parent'])) {
      $tid = CRM_Mautic_Tag::createParentTag();
      if ($tid) {
        Civi::settings()->set('mautic_sync_tag_parent', $tid);
        CRM_Core_Session::setStatus("Created new tag: 'Mautic'. $tid");
      }
    }
  }

  /**
   * Strip trailing slash from URL.
   * 
   * @param string $value
   * 
   * @return string
   */
  public static function filterTrailingSlash($value) {
    $end_pos = strlen($value) - 1;
    return strrpos($value, '/') === $end_pos ? substr($value, 0, $end_pos) : $value;
  }

  protected function getExtensionSettings() {
    $result = civicrm_api3('Setting', 'getfields',[
      'filters' => ['group' => 'mautic'],
    ]);
    return array_filter(CRM_Utils_Array::value('values', $result, []), function($item) {
      return !empty($item['html_type']);
    });
  }

  private function getSectionHelp() {
    $callback = CRM_Mautic_Connection::singleton()->getCallbackUrl();
    $txt = [];
    $txt[] = ts('You will need to enable the API in your Mautic installation.');
    $txt[] = ts('For OAuth use the following callback URL: <br /> <emphasis>%1</emphasis>', [1 => $callback]);
    if (Civi::settings()->get('mautic_connection_authentication_method')) {
      $txt[] = ts('<a href="%1">Test connection</a>', [1 => $callback]);
    }
    return '<p>' . implode('</p><p>', $txt) . '</p>';
  }

  /**
   * Returns section structure for the form.
   *
   * @return array[]
   */
  protected function getFormSections() {
    // Groups elements into sections.
    // This structure is interpreted by the template, not in Quickform.
    // Setting names should be prefixed by the section name.
    $sections = [
      'mautic_connection' => [
        'title' => E::ts('Connection'),
        'help' => $this->getSectionHelp(),
        ],
       'mautic_basic' => [
         'title' => E::ts('Basic Authentication'),
         'help' => E::ts('It is best to create a dedicated admin user to connect as CiviCRM.'),
       ],
      'mautic_oauth1' => [
        'title' => E::ts('OAuth 1a'),
        'help' => '',
        ],
      'mautic_oauth2' => [
        'title' => E::ts('OAuth 2'),
        'help' => '',
      ],
      'mautic_webhook' => [
        'title' => E::ts('Webhook'),
        'help' => '',
      ],
      'mautic_sync_tag' => [
        'title' => E::ts('Tag Synchronization'),
        'help' => '',
      ],
      'mautic_enable_debugging' => [
        'title' => 'Logging',
        'help' => '',
      ],
    ];
    $this->assign('sectionTitles', $sections);
    $elementNames = array_keys($this->_settings);
    $sectionElements = [];
    foreach ($sections as $section => $title) {
      $sectionElements[$section] = array_filter($elementNames, function($name) use ($section) {
        return 0 === strpos($name, $section);
      });
    }

    return $sectionElements;
  }

  /**
   * {@inheritdoc}
   */
  protected function getQuickFormType($spec) {
    // SettingTrait doesn't map password element type so let's do it here.
    if (strtolower($spec['html_type']) == 'password') {
      return 'Element';
    }
    return  parent::getQuickFormType($spec);
  }
}
