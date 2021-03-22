<?php
/**
 * @file
 * Provides the Form for Push from CiviCRM to Mautic form.
 */

class CRM_Mautic_Form_PushSync extends CRM_Core_Form {

  const QUEUE_NAME = 'mautic-sync';
  const END_URL    = 'civicrm/admin/mautic/pushsync';
  const END_PARAMS = 'state=done';

  /**
   *
   * {@inheritDoc}
   * @see CRM_Core_Form::preProcess()
   */
  function preProcess() {
    $state = CRM_Utils_Request::retrieveValue('state', 'String', 'tmp', FALSE, 'GET');
    if ($state == 'done') {
      $stats = \Civi::settings()->get('mautic_push_stats');
      $groups = CRM_Mautic_Utils::getGroupsToSync();
      if (!$groups) {
        return;
      }
      $output_stats = [];
      $this->assign('dry_run', $stats['dry_run'] ?? NULL);
      $this->assign('mauticDeletedInCivi', $stats['deletedInCivi'] ?? NULL);
      foreach ($groups as $group_id => $details) {
        if (empty($details['segment_name'])) {
          continue;
        }
        $segment_stats = $stats[$details['segment_id']];
        $output_stats[] = [
          'name' => $details['civigroup_title'],
          'stats' => $segment_stats,
        ];
      }
      $this->assign('stats', $output_stats);

      // Load contents of mautic_log table.
      $dao = CRM_Core_DAO::executeQuery("SELECT * FROM mautic_log ORDER BY id");
      $logs = [];
      while ($dao->fetch()) {
        $logs []= [
          'group' => $dao->group_id,
          'email' => $dao->email,
          'name' => $dao->name,
          'message' => $dao->message,
          ];
      }
      $this->assign('error_messages', $logs);
    }
  }

  /**
   *
   * {@inheritDoc}
   * @see CRM_Core_Form::buildQuickForm()
   */
  public function buildQuickForm() {

    $groups = CRM_Mautic_Utils::getGroupsToSync();
    $will = '';
    $wont = '';
    if (!empty($_GET['reset'])) {
      foreach ($groups as $group_id => $details) {
        $description = "<a href='/civicrm/group?reset=1&action=update&id=$group_id' >"
          . "CiviCRM group $group_id: "
          . htmlspecialchars($details['civigroup_title']) . "</a>";

        if (empty($details['segment_name'])) {
          $wont .= "<li>$description</li>";
        }
        else {
          $will .= "<li>$description &rarr; Mautic Segment: " . htmlspecialchars($details['segment_name']) . "</li>";
        }
      }
    }
    $msg = '';
    if ($will) {
      $msg .= "<h2>" . ts('The following will be synchronised') . "</h2><ul>$will</ul>";

      $this->addElement('checkbox', 'mautic_dry_run',
        ts('Dry Run? (if ticked no changes will be made to CiviCRM or Mautic.)'));

      // Create the Submit Button.
      $buttons = [
        [
          'type' => 'submit',
          'name' => ts('Sync Contacts'),
        ],
      ];
      $this->addButtons($buttons);
    }
    if ($wont) {
      $msg .= "<h2>" . ts('The following segments will be NOT synchronised') . "</h2><p>The following segment(s) no longer exist at Mautic.</p><ul>$wont</ul>";
    }
    $this->assign('summary', $msg);
  }

  /**
   * {@inheritdoc}
   */
  public function postProcess() {
    $vals = $this->_submitValues;
    $runner = self::getRunner(FALSE, !empty($vals['mautic_dry_run']));
    // Clear out log table.
    // Do we need to check if another process has this table?
    CRM_Mautic_Sync::dropLogTable();
    if ($runner) {
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    } else {
      CRM_Core_Session::setStatus(ts('Nothing to sync. Make sure mailchimp settings are configured for the groups with enough members.'));
    }
  }

  /**
   * Set up the queue.
   */
  public static function getRunner($skipEndUrl = FALSE, $dry_run = FALSE) {
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create([
      'name'  => self::QUEUE_NAME,
      'type'  => 'Sql',
      'reset' => TRUE,
    ]);

    // reset push stats
    $stats = ['dry_run' => $dry_run];
    \Civi::settings()->set('mautic_push_stats', $stats);

    // We need to process one segment at a time.
    $groups = CRM_Mautic_Utils::getGroupsToSync();
    CRM_Mautic_Utils::checkDebug('CRM_Mautic_Form_PushSync getRunner $groups= ', $groups);

    // Each segment is a task.
    foreach ($groups as $group_id => $details) {
      if (empty($details['segment_name'])) {
        // This segment has been deleted at Mautic, or for some other reason we
        // could not access its name. Best not to sync it.
        continue;
      }

      $stats[$details['segment_id']] = [
        'c_count'      => 0,
        'mautic_count' => 0,
        'in_sync'      => 0,
        'updates'      => 0,
        'additions'    => 0,
        'unsubscribes' => 0,
      ];

      $identifier = "Segment {$details['segment_id']} {$details['civigroup_title']}";

      $task  = new CRM_Queue_Task(
        ['CRM_Mautic_Form_PushSync', 'syncPushSegment'],
        [$details['segment_id'], $identifier, $dry_run],
        "$identifier: collecting data from CiviCRM."
      );

      // Add the Task to the Queue
      $queue->createItem($task);
    }
    if (count($stats)==1) {
      // Nothing to do. (only key is 'dry_run')
      return FALSE;
    }

    // Setup the Runner
    $runnerParams = [
      'title' => ($dry_run ? ts('Dry Run: ') : '') . ts('Mautic Push Sync: update Mautic from CiviCRM'),
      'queue' => $queue,
      'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
      'onEndUrl' => CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE),
    ];
    // Skip End URL to prevent redirect
    // if calling from cron job
    if ($skipEndUrl == TRUE) {
        unset($runnerParams['onEndUrl']);
    }
    $runner = new CRM_Queue_Runner($runnerParams);

    self::updatePushStats($stats);
    CRM_Mautic_Utils::checkDebug('End-CRM_Mautic_Form_PushSync getRunner $identifier= ', $identifier);

    return $runner;
  }

  /**
   * Set up (sub)queue for syncing a Mautic Segment.
   */
  public static function syncPushSegment(CRM_Queue_TaskContext $ctx, $segmentID, $identifier, $dry_run) {
    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_PushSync syncPushSegment $segmentID= ', $segmentID);
    // Split the work into parts:

    // Add the CiviCRM collect data task to the queue
    // It's important that this comes before the Mautic one, as some
    // fast contact matching SQL can run if it's done this way.
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Mautic_Form_PushSync', 'syncPushCollectCiviCRM'],
      [$segmentID],
      "$identifier: Fetched data from CiviCRM, fetching from Mautic..."
    ));

    // Add the Mautic collect data task to the queue
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Mautic_Form_PushSync', 'syncPushCollectMautic'],
      [$segmentID],
      "$identifier: Fetched data from Mautic. Matching..."
    ));

    // Match contacts.
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Mautic_Form_PushSync', 'syncPushMatchContacts'],
      [$segmentID],
      "$identifier: Matched up contacts. Comparing..."
    ));
    // Populate CiviCRM contacts with the mautic contact reference.
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Mautic_Form_PushSync', 'syncPushUpdateReferenceFields'],
      [$segmentID, $dry_run],
      "$identifier: Updated contact reference fields."
    ));

    // Add the Mautic collect data task to the queue
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Mautic_Form_PushSync', 'syncPushIgnoreInSync'],
      [$segmentID],
      "$identifier: Ignored any in-sync already. Updating Mautic..."
    ));

    // Add the Mautic changes
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Mautic_Form_PushSync', 'syncPushToMautic'],
      [$segmentID, $dry_run],
      "$identifier: Completed additions/updates/unsubscribes."
    ));

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Collect CiviCRM data into temporary working table.
   */
  public static function syncPushCollectCiviCRM(CRM_Queue_TaskContext $ctx, $segmentID) {
    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_PushSync syncPushCollectCiviCRM $segmentID= ', $segmentID);

    $sync = new CRM_Mautic_Sync($segmentID);
    $stats[$segmentID]['c_count'] = $sync->collectCiviCrm('push');

    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_PushSync syncPushCollectCiviCRM $stats[$segmentID][c_count]= ', $stats[$segmentID]['c_count']);
    self::updatePushStats($stats);

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Collect Mautic data into temporary working table.
   */
  public static function syncPushCollectMautic(CRM_Queue_TaskContext $ctx, $segmentID) {
    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_PushSync syncPushCollectMautic $segmentID= ', $segmentID);

    // Nb. collectCiviCrm must have run before we call this.
    $sync = new CRM_Mautic_Sync($segmentID);
    $stats[$segmentID]['mautic_count'] = $sync->collectMautic('push', $civi_collect_has_already_run=TRUE);

    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_PushSync syncPushCollectMautic $stats[$segmentID][mautic_count]', $stats[$segmentID]['mautic_count']);
    self::updatePushStats($stats);

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Bulk match civi and mautic contacts.
   */
  public static function syncPushMatchContacts(CRM_Queue_TaskContext $ctx, $segmentID) {

    // Nb. collectCiviCrm must have run before we call this.
    $sync = new CRM_Mautic_Sync($segmentID);
    $c = $sync->matchMauticMembersToContacts();
    CRM_Mautic_Utils::checkDebug('CRM_Mautic_Form_PushSync syncPushMatchContacts count=', $c);
    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Collect Mautic data into temporary working table.
   */
  public static function syncPushIgnoreInSync(CRM_Queue_TaskContext $ctx, $segmentID) {
    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_PushSync syncPushIgnoreInSync $segmentID= ', $segmentID);

    $sync = new CRM_Mautic_Sync($segmentID);
    $stats[$segmentID]['in_sync'] = $sync->removeInSync('push');

    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_PushSync syncPushIgnoreInSync $stats[$segmentID][in_sync]', $stats[$segmentID]['in_sync']);
    self::updatePushStats($stats);

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Batch update Mautic with new contacts.
   *
   * @param \CRM_Queue_TaskContext $ctx
   * @param int $segmentID
   * @param bool $dry_run
   *
   * @return int
   */
  public static function syncPushToMautic(CRM_Queue_TaskContext $ctx, $segmentID, $dry_run) {
    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_PushSync syncPushAdd $segmentID= ', $segmentID);

    // Do the batch update.
    $sync = new CRM_Mautic_Sync($segmentID);
    $sync->dry_run = $dry_run;
    // this generates updates and unsubscribes
    $stats[$segmentID] = $sync->updateMauticFromCivi($ctx);
    self::updatePushStats($stats);

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Batch update CiviCRM reference fields to mautic contacts.
   */
  public static function syncPushUpdateReferenceFields(CRM_Queue_TaskContext $ctx, $segmentID, $dry_run) {
    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_PushSync syncPushUpdateReferenceFields $segmentID= ', $segmentID);

    $sync = new CRM_Mautic_Sync($segmentID);
    $sync->dry_run = $dry_run;
    $stats[$segmentID] = $sync->updateContactReferenceFields();
    self::updatePushStats($stats);

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Update the push stats setting.
   */
  public static function updatePushStats($updates) {
    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_PushSync updatePushStats $updates= ', $updates);

    $stats = \Civi::settings()->get('mautic_push_stats');
    foreach ($updates as $segmentId => $settings) {
      if ($segmentId == 'dry_run') {
        continue;
      }
      foreach ($settings as $key=>$val) {
        $stats[$segmentId][$key] = $val;
      }
    }
    \Civi::settings()->set('mautic_push_stats', $stats);
  }
}
