<?php
use CRM_Mautic_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Mautic_Upgrader extends CRM_Mautic_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   */
  public function install() {
    $activityTypeGroup = civicrm_api3('OptionGroup', 'getsingle', [
      'name' => "activity_type",
    ]);
    $groupId = $activityTypeGroup['id'];
    // Create activity type.
    $params = [
      'option_group_id' =>  $groupId,
      'label' => 'Mautic Webhook Triggered',
      'name' => 'Mautic_Webhook_Triggered',
      'filter' =>  '0',
      'is_active' => '1'
    ];
    $this->createIfNotExists('OptionValue', $params, ['name', 'option_group_id']);
    $this->createPushSyncJob();
    $this->enableCiviRules();
  }

  protected function createIfNotExists($entity, $params, $lookupKeys = ['name']) {
    try {
      $lookupParams = array_intersect_key($params, array_flip($lookupKeys));
      $lookupParams['sequential'] = 1;
      $existingResult = civicrm_api3($entity, 'get', $lookupParams);
      if (!empty($existingResult['values'])) {
        return $existingResult['values'][0];
      }
      // Doesn't exist, create.
      $createResult = civicrm_api3($entity, 'create', $params);
      return $createResult['values'];
    }
    catch (Exception $e) {
      // Let Civi Handle it.
      throw($e);
    }
  }

  protected function createPushSyncJob() {
    // Create a cron job to do sync data between CiviCRM and Mautic.
    $params =[
      'name'          => 'Mautic Push Sync',
      'description'   => 'Sync contacts between CiviCRM and Mautic, assuming CiviCRM to be correct. Please understand the implications before using this.',
      'run_frequency' => 'Daily',
      'api_entity'    => 'Mautic',
      'api_action'    => 'pushsync',
      'is_active'     => 0,
    ];
    $this->createIfNotExists('Job', $params);
  }

  protected function enableCiviRules() {
    if (class_exists('CRM_Civirules_Utils_Upgrader')) {
      CRM_Civirules_Utils_Upgrader::insertTriggersFromJson($this->extensionDir . DIRECTORY_SEPARATOR . 'sql/civirules/triggers.json');
      CRM_Civirules_Utils_Upgrader::insertConditionsFromJson($this->extensionDir . DIRECTORY_SEPARATOR . 'sql/civirules/conditions.json');
      CRM_Civirules_Utils_Upgrader::insertActionsFromJson($this->extensionDir . DIRECTORY_SEPARATOR . 'sql/civirules/actions.json');
    }
  }
  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   */
  public function postInstall() {
    // Add Custom Data for Mautic_Webhook_Triggered activity type.
    $file = $this->extensionDir . '/xml/activity_data.xml';
    $this->executeCustomDataFileByAbsPath($file);
  }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_4200() {
    $this->createPushSyncJob();

    // Create Pull Sync job.
    /**
     * Not implemented yet, so don't expose it as a job.
    $params = array(
      'sequential' => 1,
      'name'          => 'Mautic Pull Sync',
      'description'   => 'Sync contacts between CiviCRM and Mautic, assuming Mautic to be correct. Please understand the implications before using this.',
      'run_frequency' => 'Daily',
      'api_entity'    => 'Mautic',
      'api_action'    => 'pullsync',
      'is_active'     => 0,
    );
    $result = civicrm_api3('job', 'create', $params);
    **/
    return TRUE;
  }


  /**
   * Insert Civirules trigger for WebHook.
   *
   * @return TRUE on success
   * @throws Exception
   **/
  public function upgrade_4201() {
    $this->ctx->log->info('Enabling CiviRules triggers/conditions/actions');
    $this->enableCiviRules();
    return TRUE;
  }

  public function upgrade_4202() {
    $this->ctx->log->info('Updating civicrm_mauticwebhook table');
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civicrm_mauticwebhook', 'processed', FALSE)) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_mauticwebhook` ADD COLUMN `processed_date` timestamp NULL DEFAULT NULL COMMENT 'Date this webhook was processed in CiviCRM'");
    }
    CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_mauticwebhook` MODIFY `webhook_trigger_type` VARCHAR(255)");
    return TRUE;
  }

}
