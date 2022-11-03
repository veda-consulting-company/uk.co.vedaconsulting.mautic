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
 * Implements hook_civicrm_install().
 */
function mautic_civicrm_install() {
  _mautic_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 */
function mautic_civicrm_postInstall() {
  _mautic_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 */
function mautic_civicrm_uninstall() {
  _mautic_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 */
function mautic_civicrm_enable() {
  _mautic_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 */
function mautic_civicrm_disable() {
  _mautic_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 */
function mautic_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _mautic_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_buildForm.
 *
 * Add Mautic integration to group settings.
 *
 * @param string $formName
 * @param CRM_Core_Form $form
 */
function mautic_civicrm_buildForm($formName, &$form) {
  if ($formName != 'CRM_Group_Form_Edit') {
    return;
  }

  if ($form->isSubmitted()) {
    return;
  }
  // Add Settings Template.
  // For Editing a group, then adding to regions other than html-header
  // results in duplicate elements.
  // For Adding a group, the html-header region inserts the template before
  // CRM.$ is loaded.
  $region = $form->getAction() == CRM_Core_Action::ADD ? 'page-footer' : 'html-header';

  CRM_Core_Region::instance($region)->add(
    ['template' => 'CRM/Group/MauticSettings.tpl']);

  if (($form->getAction() == CRM_Core_Action::ADD) || ($form->getAction() == CRM_Core_Action::UPDATE)) {
    //  Add form elements to associate group with Mautic Segment.
    $segments = CRM_Mautic_Utils::getMauticSegmentOptions();
    if ($segments) {
      $form->add('select', 'mautic_segment', ts('Mautic Segment'), ['' => '- select -'] + $segments);

      $options = [
        ts('No integration'),
        ts('Sync to a Mautic segment: Contacts in this group will be added or removed from a segment.'),
      ];
      $form->addRadio('mautic_integration_option', '', $options, NULL, '<br/>');

      // Prepopulate details if 'edit' action
      $groupId = $form->getVar('_id');
      if ($form->getAction() == CRM_Core_Action::UPDATE AND !empty($groupId)) {
        $mauticDetails  = CRM_Mautic_Utils::getGroupsToSync([$groupId]);
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
        $form->assign('mautic_segment_id' , $groupDetails['segment_id'] ?? 0);
      }
    }
  }
}

/**
 * Implements hook_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors)
 */
function mautic_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName != 'CRM_Group_Form_Edit') {
    return;
  }
  if ($fields['mautic_integration_option'] == 1) {
    if (empty($fields['mautic_segment'])) {
      $errors['mautic_segment'] = ts('Please specify a Segment');
    }
    else {
      // We need to make sure that this is the only group for this segment.
      $otherGroups = CRM_Mautic_Utils::getGroupsToSync([], $fields['mautic_segment'], TRUE);
      $thisGroup = $form->getVar('_group');
      if ($thisGroup) {
        unset($otherGroups[$thisGroup->id]);
      }
      if (!empty($otherGroups)) {
        $otherGroup = reset($otherGroups);
        $errors['mautic_segment'] = ts('There is already a CiviCRM group associated with this Segment, called "'
          . $otherGroup['civigroup_title'].'"');
      }
    }
  }
}
/**
 * Implements hook_civicrm_customPre()
 */
function mautic_civicrm_customPre(string $op, int $groupID, int $entityID, array &$params): void {
  // Create a Mautic Segment for an Event if it is so configured.
  static $mauticEventGid = NULL;
  if (!$mauticEventGid) {
    $mauticEventGid = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_CustomGroup',
    'Mautic_Event',
    'id',
    'name'
    );
  }
  if (in_array($op, ['create', 'edit']) &&  $groupID == $mauticEventGid) {
    $createSegmentField = NULL;
    $segmentIdField = NULL;
    foreach ($params as $idx => &$field) {
      if ($field['column_name'] == 'create_segment') {
        $createSegmentField = $field;
      }
      elseif ($field['column_name'] == 'mautic_segment_id') {
        $segmentIdField =& $field;
      }
    }

    if (empty($segmentIdField['value']) && !empty($createSegmentField['value'])) {
      $title = CRM_Core_DAO::getFieldValue('CRM_Event_DAO_Event',
        $entityID,
        'title',
        'id'
      );
      if ($title) {
        try {
          $suffix = Civi::settings()->get('mautic_event_segment_text');
          $position = Civi::settings()->get('mautic_event_segment_text_position');
          $segmentName = $position == 'before' ? "$suffix $title" : "$title $suffix";
          $segmentName = trim($segmentName);
          // Possibly segment with the name already exists.
          $segmentParams = [
            'name' => $segmentName,
          ];
          $segmentId = CRM_Mautic_Utils::createSegment($segmentParams);
          // Add Segment.
          $segmentIdField['value'] = $segmentId;
        }
        catch (Exception $e) {
          CRM_Core_Error::debug_var(__FUNCTION__, [
            'Error Creating Mautic segment.',
            $e->getMessage(),
            $segmentParams,
          ]);
        }
      }
    }
  }
}

 /**
 * Implements hook_civicrm_pageRun().
 *
 * @param CRM_Core_Page $page
 */
function mautic_civicrm_pageRun(&$page) {
  if ($page->getVar('_name') == 'CRM_Group_Page_Group') {
    // Manage Groups page at /civicrm/group?reset=1

    $js_safe_object = [];
    foreach (CRM_Mautic_Utils::getGroupsToSync() as $group_id => $group) {
      if ($group['segment_name']) {
        $val = strtr(
          ts("Sync to segment: %segment_name"),
          ['%segment_name' => htmlspecialchars($group['segment_name'])]
        );
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
}

function mautic_civicrm_entityTypes(&$entityTypes) {
  _mautic_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_alterLogTables().
 *
 * Exclude tables from logging tables since they hold mostly temp data.
 */
function mautic_civicrm_alterLogTables(&$logTableSpec) {
  unset($logTableSpec['civicrm_mauticwebhook']);
}

/**
 *
 * Implements hook_civicrm_fieldOptions().
 */
function mautic_civicrm_fieldOptions($entity, $field, &$options, $params) {
  // Add options for field linking Event to Mautic Segment.
  if ($entity == 'Event' && 0 === strpos($field, 'custom_')) {
    $fid = CRM_Core_BAO_CustomField::getCustomFieldID('Mautic_Segment', 'Mautic_Event');
    if ('custom_' . $fid == $field) {
      $segments = CRM_Mautic_Utils::getMauticSegmentOptions();
      $options = $segments ? $segments : $options;
    }
  }
}

/*
 * Implementation of hook_civicrm_alterCustomFieldDisplayValue
 *
 */
function mautic_civicrm_alterCustomFieldDisplayValue(&$displayValue, $value, $entityId, $fieldInfo) {
  if ($fieldInfo['name'] == 'Mautic_Contact_ID') {
    $mauticURL = \Civi::settings()->get('mautic_connection_url');
    $displayValue = "<a href='{$mauticURL}/s/contacts/view/{$value}' target='_blank'>$value</a>";
  }
}
