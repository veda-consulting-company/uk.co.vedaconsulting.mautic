<?php

require_once 'mautic.civix.php';
use CRM_Mautic_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function mautic_civicrm_config(&$config) {
  _mautic_civix_civicrm_config($config);
   require_once  __DIR__ . '/vendor/autoload.php'; 
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function mautic_civicrm_xmlMenu(&$files) {
  _mautic_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function mautic_civicrm_install() {
  _mautic_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function mautic_civicrm_postInstall() {
  _mautic_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function mautic_civicrm_uninstall() {
  _mautic_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function mautic_civicrm_enable() {
  _mautic_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function mautic_civicrm_disable() {
  _mautic_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function mautic_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _mautic_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function mautic_civicrm_managed(&$entities) {
  _mautic_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function mautic_civicrm_caseTypes(&$caseTypes) {
  _mautic_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function mautic_civicrm_angularModules(&$angularModules) {
  _mautic_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function mautic_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _mautic_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function mautic_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implementation of hook_civicrm_buildForm.
 *
 * Add Mautic integration to group settings.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_buildForm
 */
function mautic_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Group_Form_Edit' AND ($form->getAction() == CRM_Core_Action::ADD OR $form->getAction() == CRM_Core_Action::UPDATE)) {
    //  Add form elements to associate group with Mautic Segment.
    $segments = CRM_Mautic_Utils::getMauticSegmentOptions();
    if($segments){
      $form->add('select', 'mautic_segment', ts('Mautic Segment'), array('' => '- select -') + $segments);
      
      $options = array(
        ts('No integration'),
        ts('Sync to a Mautic segment: Contacts in this group will be added or removed from a segment.'),
      );
      $form->addRadio('mautic_integration_option', '', $options, NULL, '<br/>');
      
      // Prepopulate details if 'edit' action
      $groupId = $form->getVar('_id');
      if ($form->getAction() == CRM_Core_Action::UPDATE AND !empty($groupId)) {
        $mauticDetails  = CRM_Mautic_Utils::getGroupsToSync(array($groupId));
        $groupDetails = CRM_Utils_Array::value($groupId, $mauticDetails, []);
        $defaults['mautic_fixup'] = 1;
        if (!empty($groupDetails)) {
          $defaults['mautic_segment'] = $groupDetails['segment_id'];
          $defaults['mautic_integration_option'] = !empty($groupDetails['segment_id']);
          $form->setDefaults($defaults);
        }
        else {
          // defaults for a new group
          $defaults['mautic_integration_option'] = 0;
          $defaults['is_mautic_update_grouping'] = 0;
          
          $form->setDefaults($defaults);
        }
        $form->assign('mautic_segment_id' ,  CRM_Utils_Array::value('segment_id', $groupDetails, 0));
      }
    }
  }
}

/**
 * Implements hook_civicrm_validateForm( $formName, &$fields, &$files, &$form, &$errors )
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_validateForm
 *
 */
function mautic_civicrm_validateForm( $formName, &$fields, &$files, &$form, &$errors ) {
  if ($formName != 'CRM_Group_Form_Edit') {
    return;
  }
  if ($fields['mautic_integration_option'] == 1) {
    if (empty($fields['mautic_segment'])) {
      $errors['mautic_segment'] = ts('Please specify a Segment');
    }
    else {
      // We need to make sure that this is the only group for this segment.
      $otherGroups = CRM_Mautic_Utils::getGroupsToSync(array(), $fields['mautic_segment'], TRUE);
      $thisGroup = $form->getVar('_group');
      if ($thisGroup) {
        unset($otherGroups[$thisGroup->id]);
      }
      if (!empty($otherGroups)) {
        $otherGroup = reset($otherGroups);
        $errors['mailchimp_list'] = ts('There is already a CiviCRM group associated with this Segment, called "'
            . $otherGroup['civigroup_title'].'"');
      }
    }
  }
}

/**
 * Implements hook_civicrm_pageRun().
 * 
 * @param unknown $page
 */
function mautic_civicrm_pageRun( &$page ) {
  if ($page->getVar('_name') == 'CRM_Group_Page_Group') {
    // Manage Groups page at /civicrm/group?reset=1
    
    $js_safe_object = [];
    foreach (CRM_Mautic_Utils::getGroupsToSync() as $group_id => $group) {
        if ($group['segment_name']) {
          $val = strtr(ts("Sync to segment: %segment_name"),
              [ '%segment_name'     => htmlspecialchars($group['segment_name']), ]);
        }
        else {
          $val = ts("Missing segment.");
        }
      
      $js_safe_object['id' . $group_id] = $val;
    }
    $page->assign('mautic_groups', json_encode($js_safe_object));
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function mautic_civicrm_navigationMenu(&$menu) {
  _mautic_civix_insert_navigation_menu($menu, 'Administer', [
    'label' => 'Mautic',
    'name' => 'Mautic',
    'url' => NULL,
    'permission' => 'administer CiviCRM',
    'operator' => NULL,
    'separator' => NULL,
  ]);
  _mautic_civix_insert_navigation_menu($menu, 'Administer/Mautic', [
    'label' => 'Mautic Settings',
    'name' => 'Mautic Settings',
    'url' => 'civicrm/admin/mautic/settings',
    'permission' => 'administer CiviCRM',
    'operator' => NULL,
    'separator' => 0,
  ]);
  _mautic_civix_insert_navigation_menu($menu, 'Administer/Mautic', [
    'label' => 'Connection',
    'name' => 'Connection',
    'url' => 'civicrm/admin/mautic/connection',
    'permission' => 'administer CiviCRM',
    'operator' => NULL,
    'separator' => 0,
  ]);
  _mautic_civix_insert_navigation_menu($menu, 'Administer/Mautic', [
    'label' => 'Push to Mautic',
    'name' => 'Push',
    'url' => 'civicrm/admin/mautic/pushsync?reset=1',
    'permission' => 'administer CiviCRM',
    'operator' => NULL,
    'separator' => 0,
  ]);
  _mautic_civix_navigationMenu($menu);
} // */

/**
 * Implements hook_civicrm_merge().
 * 
 * @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_merge/
 * 
 * @param string $type
 * @param mixed $data
 * @param int $mainId
 * @param int $otherId
 * @param array $tables
 */
function mautic_civicrm_merge($type, $data, $mainId, $otherId, $tables) {
  // @todo. 
  // If there is a contact with civicrm_contact_id of $otherId,
  // Update it to reference mainId.
 // if ($type == 'sqls') {    
  //}
}

/**
function mautic civicrm_post($op, $objectName, $objectId, &$objectRef) {
}
**/
function mautic_civicrm_entityTypes(&$entityTypes) {
  $entityTypes['CRM_Mautic_DAO_MauticWebHook'] = [
    'name' => 'MauticWebHook',
    'class' => 'CRM_Mautic_DAO_MauticWebHook',
    'table' => 'civicrm_mauticwebhook',
    
  ];
  if (function_exists('_mautic_civix_civicrm_entityTypes')) {
    _mautic_civix_civicrm_entityTypes($entityTypes);
  }
}