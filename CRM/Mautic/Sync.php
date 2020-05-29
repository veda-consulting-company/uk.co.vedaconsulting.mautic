<?php

use CRM_Mautic_Connection as MC;
use CRM_Mautic_Utils as U;

/**
 * @file
 * This class holds all the sync logic for a particular segment.
 */
class CRM_Mautic_Sync {
  
  
  protected const MAUTIC_FETCH_BATCH_SIZE = 100;
  protected const MAUTIC_PUSH_BATCH_SIZE = 500;
  
  /**
   * Holds the Mautic List ID.
   *
   * This is accessible read-only via the __get().
   */
  protected $segment_id;
  
  /**
   * The segment alias is used when filtering contacts.
   * @var string
   */
  protected $segment_alias;
  
  /**
   * Cache of details from CRM_Mautic_Utils::getGroupsToSync.
   ▾ $this->group_details['61'] = (array [12])
     ⬦ $this->group_details['61']['segment_id']
     ⬦ $this->group_details['61']['segment_name']
     ⬦ $this->group_details['61']['civigroup_title'] = (string [28]) `mautic_integration_test_1`
     ⬦ $this->group_details['61']['civigroup_uses_cache'] = (bool) 0
   */
  protected $group_details;
  /**
   * As above but without membership group.
   */
  protected $interest_group_details;
  /**
   * The CiviCRM group id responsible for membership at Mautic.
   */
  protected $membership_group_id;

  /** If true no changes will be made to Mautic or CiviCRM. */
  protected $dry_run = FALSE;
 
  /**
   * Get Mautic API client object for Contacts.
   * @return NULL|\Mautic\Api\Api
   */
  protected function getApi($context) {
    return MC::singleton()->newApi($context);
  }
 
  /**
   * Returns a key => value array of the current CiviCRM group and Mautic Segment.
   * @return NULL[]
   */
  protected function singleGroupMapping() {
    return [$this->membership_group_id => $this->segment_id];
  }
  
  public function __construct($segment_id) {
    $this->segment_id = $segment_id;
    $this->segment_alias = U::getMauticSegment($segment_id, 'alias');
    $this->group_details = CRM_Mautic_Utils::getGroupsToSync();
    foreach ($this->group_details as $group_id => $group_details) {
      if ($group_details['segment_id'] == $segment_id) {
        $this->membership_group_id = $group_id;
      }
    }
    if (empty($this->membership_group_id)) {
      throw new InvalidArgumentException("Failed to find mapped membership group for segment '$segment_id'");
    }
    // Also cache without the membership group, i.e. interest groups only.
    $this->interest_group_details = $this->group_details;
    unset($this->interest_group_details[$this->membership_group_id]);
  }
  /**
   * Getter.
   */
  public function __get($property) {
    switch ($property) {
    case 'segment_id':
    case 'membership_group_id':
    case 'group_details':
    case 'interest_group_details':
    case 'dry_run':
      return $this->$property;
    }
    throw new InvalidArgumentException("'$property' property inaccessible or unknown");
  }
  /**
   * Setter.
   */
  public function __set($property, $value) {
    switch ($property) {
    case 'dry_run':
      return $this->$property = (bool) $value;
    }
    throw new InvalidArgumentException("'$property' property inaccessible or unknown");
  }
   
  // The following methods are the key steps of the pull and push syncs.
  /**
   * Collect Mautic data into temporary working table.
   *
   * There are two modes of operation:
   *
   * In **pull** mode we only collect data that comes from Mautic that we are
   * allowed to update in CiviCRM.
   *
   * In **push** mode we collect data that we would update in Mautic from
   * CiviCRM.
   *
   *
   * @param string $mode pull|push.
   * @return int   number of contacts collected.
   */
  public function collectMautic($mode) {
    U::checkDebug('Start-CRM_Mautic_Form_Sync syncCollectMautic $this->segment_id= ', $this->segment_id);
    if (!in_array($mode, ['pull', 'push'])) {
      throw new InvalidArgumentException(__FUNCTION__ . " expects push/pull but called with '$mode'.");
    }
    $dao = static::createTemporaryTableForMautic();

    // Access the database directly to obtain a prepared statement.
    $db = $dao->getDatabaseConnection();
    $insert = $db->prepare('INSERT INTO tmp_mautic_push_m
             (email, first_name, last_name, hash, group_info, mautic_contact_id, civicrm_contact_id)
      VALUES (?,     ?,          ?,         ?,    ?, ?, ?)');
    

    CRM_Mautic_Utils::checkDebug('CRM_Mautic_Form_Sync syncCollectMautic: ', $this->interest_group_details);
    //
    // Main loop of all the records.
    $collected = 0;
    $batchAPI = new CRM_Mautic_APIBatchList('contacts', 
        self::MAUTIC_FETCH_BATCH_SIZE,
        ['search' => 'segment:' . $this->segment_alias]
        ); 
    
    // All mautic contacts are in the current segment. 
    while ($members = $batchAPI->fetchBatch()) {
      $start = microtime(TRUE);
      foreach ($members as $member) {
        $first_name = CRM_Mautic_Contact_FieldMapping::getValue($member, 'first_name');
        $last_name = CRM_Mautic_Contact_FieldMapping::getValue($member, 'last_name');
        $email = CRM_Mautic_Contact_FieldMapping::getValue($member, 'email');
        // Serialize the grouping array for SQL storage - this is the fastest way.
        $groupInfo = serialize($this->singleGroupMapping());
        $civicrm_contact_id = CRM_Mautic_Contact_FieldMapping::getValue($member, 'civicrm_contact_id', 0);
        $mautic_contact_id = $member['id']; 
        // for comparison with the hash created from the CiviCRM data (elsewhere).
  
        $hash = md5($first_name . $last_name . $email . $groupInfo);
        
        // run insert prepared statement
        $result = $db->execute($insert, [
          $email,
          $first_name,
          $last_name,
          $hash,
          $groupInfo,
          $mautic_contact_id,
          $civicrm_contact_id
        ]);
        if ($result instanceof DB_Error) {
          throw new Exception ($result->message . "\n" . $result->userinfo);
        }
        $collected++;
      }
      CRM_Mautic_Utils::checkDebug('collectMautic took ' . round(microtime(TRUE) - $start,2) . 's to copy ' . count($members) . ' mautic Members to tmp table.');
    }

    // Tidy up.
    $db->freePrepared($insert);
    return $collected;
  }

  /**
   * Collect CiviCRM data into temporary working table.
   *
   * Speed notes.
   *
   * Various strategies have been tried here to speed things up. Originally we
   * used the API with a chained API call, but this was very slow (~10s for
   * ~5k contacts), so now we load all the contacts, then all the emails in a
   * 2nd API call. This is about 10x faster, taking less than 1s for ~5k
   * contacts. Likewise the structuring of the emails on the contact array has
   * been tried various ways, and this structure-by-type way has reduced the
   * origninal loop time from 7s down to just under 4s.
   *
   *
   * @param string $mode pull|push.
   * @return int number of contacts collected.
   */
  public function collectCiviCrm($mode) {
    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_Sync syncCollectCiviCRM $this->segment_id= ', $this->segment_id);
    CRM_Mautic_Utils::checkDebug('Start-CRM_Mautic_Form_Sync syncCollectCiviCRM $this->interest_group_details= ', $this->interest_group_details);
    if (!in_array($mode, ['pull', 'push'])) {
      throw new InvalidArgumentException(__FUNCTION__ . " expects push/pull but called with '$mode'.");
    }
    // Cheekily access the database directly to obtain a prepared statement.
    $dao = static::createTemporaryTableForCiviCRM();
    $db = $dao->getDatabaseConnection();

    // There used to be a distinction between the handling of 'normal' groups
    // and smart groups. But now the API will take care of this but this
    // requires the following function to have run.
    foreach ($this->interest_group_details as $group_id => $details) {
      if ($mode == 'push' || $details['is_mautic_update_grouping'] == 1) {
        // Either we are collecting for a push from C->M,
        // or we're pulling and this group is configured to allow updates.
        // Therefore we need to make sure the cache is filled.
        CRM_Contact_BAO_GroupContactCache::loadAll($group_id);
      }
    }

    // Use a nice API call to get the information for tmp_mautic_push_c.
    // The API will take care of smart groups.
    $start = microtime(TRUE);
    $result = civicrm_api3('Contact', 'get', [
      'is_deleted' => 0,
      // The email filter in comment below does not work (CRM-18147)
      // 'email' => array('IS NOT NULL' => 1),
      // Now I think that on_hold is NULL when there is no e-mail, so if
      // we are lucky, the filter below implies that an e-mail address
      // exists ;-)
      'is_opt_out' => 0,
      'do_not_email' => 0,
      'on_hold' => 0,
      'is_deceased' => 0,
      'group' => $this->membership_group_id,
      'return' => ['first_name', 'last_name', 'group'],
      'options' => ['limit' => 0],
      //'api.Email.get' => ['on_hold'=>0, 'return'=>'email,is_bulkmail'],
    ]);

    if ($result['count'] == 0) {
      // No-one is in the group according to CiviCRM.
      return 0;
    }

    // Load emails for these contacts.
    $emails = civicrm_api3('Email', 'get', [
      'on_hold' => 0,
      'return' => 'contact_id,email,is_bulkmail,is_primary',
      'contact_id' => ['IN' => array_keys($result['values'])],
      'options' => ['limit' => 0],
    ]);
    // Index emails by contact_id.
    foreach ($emails['values'] as $email) {
      if ($email['is_bulkmail']) {
        $result['values'][$email['contact_id']]['bulk_email'] = $email['email'];
      }
      elseif ($email['is_primary']) {
        $result['values'][$email['contact_id']]['primary_email'] = $email['email'];
      }
      else {
        $result['values'][$email['contact_id']]['other_email'] = $email['email'];
      }
    }
    /**
     * We have a contact that has no other deets.
     */

    $start = microtime(TRUE);

    $collected = 0;
    $insert = $db->prepare('INSERT IGNORE INTO tmp_mautic_push_c 
   (contact_id, email,
      first_name, last_name, hash, group_info, mautic_contact_id)
    VALUES(?, ?, ?, ?, ?, ?, ?)');
    
    // Loop contacts:
    foreach ($result['values'] as $id => $contact) {
      // Which email to use?
      $email = isset($contact['bulk_email'])
        ? $contact['bulk_email']
        : (isset($contact['primary_email'])
          ? $contact['primary_email']
          : (isset($contact['other_email'])
            ? $contact['other_email']
            : NULL));
      if (!$email) {
        // Hmmm.
        continue;
      }

      if (!(filter_var($email, FILTER_VALIDATE_EMAIL))) { 
        continue;
      }

      // Store the fact that the contact is a member of the current group.
      $info = serialize($this->singleGroupMapping());

      // we're ready to store this but we need a hash that contains all the info
      // for comparison with the hash created from the CiviCRM data (elsewhere).
      //          email,           first name,      last name,      groupings
      // See note above about why we don't include email in the hash.
      // $hash = md5($email . $contact['first_name'] . $contact['last_name'] . $info);
      $hash = md5($contact['first_name'] . $contact['last_name'] . $email . $info);
      
      // Set mautic id to a numeric value.
      $mautic_contact_id = 0;
      // run insert prepared statement
      try {
        $db->execute($insert, array(
          $contact['id'],
          trim($email),
          $contact['first_name'],
          $contact['last_name'],
          $hash,
          $info,
          $mautic_contact_id
        ));
      }
      catch (PEAR_Exception $e) {
        if (get_class($e->getCause()) == 'DB_Error') {
          CRM_Mautic_Utils::checkDebug("Database error for {$contact['first_name']} {$contact['last_name']} ({$email}), segment ID: {$this->segment_id}.");
        }
        else {
          // Something else. Rethrow.
          throw $e;
        }
      }
      $collected++;
    }

    // Tidy up.
    $db->freePrepared($insert);

    return $collected;
  }

  /**
   * Match mautic records to particular contacts in CiviCRM.
   *
   * This requires that both collect functions have been run in the same mode
   * (push/pull).
   *
   * First we attempt a number of SQL based strategies as these are the fastest.
   *
   * If the fast SQL matches have failed, we need to do it the slow way.
   *
   * @return array of counts - for tests really.
   * - bySubscribers
   * - byUniqueEmail
   * - byNameEmail
   * - bySingle
   * - totalMatched
   * - newContacts (contacts that should be created in CiviCRM)
   * - failures (duplicate contacts in CiviCRM)
   */
  public function matchMauticMembersToContacts() {
    // Ensure we have the mautic_log table.
    $dao = CRM_Core_DAO::executeQuery(
      "CREATE TABLE IF NOT EXISTS mautic_log (
        id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        group_id int(20),
        email VARCHAR(200),
        name VARCHAR(200),
        message VARCHAR(512),
        KEY (group_id)
        );");
    // Clear out any old errors to do with this segment.
    CRM_Core_DAO::executeQuery(
      "DELETE FROM mautic_log WHERE group_id = %1;",
      [1 => [$this->membership_group_id, 'Integer' ]]);

    $stats = [
      'bySubscribers' => 0,
      'byUniqueEmail' => 0,
      'byNameEmail' => 0,
      'bySingle' => 0,
      'totalMatched' => 0,
      'newContacts' => 0,
      'failures' => 0,
    ];

    // Do the fast SQL identification against CiviCRM contacts.
    $start = microtime(TRUE);
    $stats['bySubscribers'] = static::guessContactIdsBySubscribers();
    CRM_Mautic_Utils::checkDebug('guessContactIdsBySubscribers took ' . round(microtime(TRUE) - $start, 2) . 's');
    $start = microtime(TRUE);
    $stats['byUniqueEmail'] = static::guessContactIdsByUniqueEmail();
    CRM_Mautic_Utils::checkDebug('guessContactIdsByUniqueEmail took ' . round(microtime(TRUE) - $start, 2) . 's');
    $start = microtime(TRUE);
    $stats['byNameEmail'] = static::guessContactIdsByNameAndEmail();
    CRM_Mautic_Utils::checkDebug('guessContactIdsByNameAndEmail took ' . round(microtime(TRUE) - $start, 2) . 's');
    $start = microtime(TRUE);

    // Now slow match the rest.
    $dao = CRM_Core_DAO::executeQuery( "SELECT * FROM tmp_mautic_push_m m WHERE cid_guess IS NULL;");
    $db = $dao->getDatabaseConnection();
    $update = $db->prepare('UPDATE tmp_mautic_push_m
      SET cid_guess = ? WHERE email = ? AND hash = ?');
    $failures = $new = 0;
    while ($dao->fetch()) {
      try {
        $contact_id = $this->guessContactIdSingle($dao->email, $dao->first_name, $dao->last_name);
        if (!$contact_id) {
          // We use zero to mean create a contact.
          $contact_id = 0;
          $new++;
        }
        else {
          // Successful match.
          $stats['bySingle']++;
        }
      }
      catch (CRM_Mautic_DuplicateContactsException $e) {
        $contact_id = NULL;
        $failures++;
      }
      if ($contact_id !== NULL) {
        // Contact found, or a zero (create needed).
        $result = $db->execute($update, [
          $contact_id,
          $dao->email,
          $dao->hash,
        ]);
        if ($result instanceof DB_Error) {
          throw new Exception ($result->message . "\n" . $result->userinfo);
        }
      }
    }
    $db->freePrepared($update);

    $took = microtime(TRUE) - $start;
    $took = round($took, 2);
    $secs_per_rec = $stats['bySingle'] ?
      round($took / $stats['bySingle'],2) : 0;
    CRM_Mautic_Utils::checkDebug("guessContactIdSingle took {$took} sec " .
      "for {$stats['bySingle']} records ({$secs_per_rec} s/record)");

    $stats['totalMatched'] = array_sum($stats);
    $stats['newContacts'] = $new;
    $stats['failures'] = $failures;

    if ($stats['failures']) {
      // Copy errors into the mautic_log table.
      CRM_Core_DAO::executeQuery(
        "INSERT INTO mautic_log (group_id, email, name, message)
         SELECT %1 group_id,
          email,
          CONCAT_WS(' ', first_name, last_name) name,
          'titanic' message
         FROM tmp_mautic_push_m
         WHERE cid_guess IS NULL;",
      [1 => [$this->membership_group_id, 'Integer']]);
    }

    return $stats;
  }

  /**
   * Removes from the temporary tables those records that do not need processing
   * because they are identical.
   *
   * In *push* mode this will also remove any rows in the CiviCRM temp table
   * where there's an email match in the mautic table but the cid_guess is
   * different. This is to cover the case when two contacts in CiviCRM have the
   * same email and both are added to the membership group. Without this the
   * Push operation would attempt to craeate a 2nd Mautic member but with the
   * email address that's already on the segment. This would mean the names kept
   * getting flipped around since it would be updating the same member twice -
   * very confusing.
   *
   * So for deleting the contacts from the CiviCRM table on *push* we avoid
   * this. However on *pull* we leave the contact in the table - they will then
   * get removed from the group, leaving just the single contact/member with
   * that particular email address.
   *
   * @param string $mode pull|push.
   * @return int
   */
  public function removeInSync($mode) {

    // In push mode, delete duplicate CiviCRM contacts.
    $doubles = 0;
    if ($mode == 'push') {
      $doubles = CRM_Mautic_Sync::runSqlReturnAffectedRows(
        'DELETE c
         FROM tmp_mautic_push_c c
         INNER JOIN tmp_mautic_push_m m ON c.email=m.email AND m.cid_guess != c.contact_id;
        ');
      if ($doubles) {
        CRM_Mautic_Utils::checkDebug("removeInSync removed $doubles contacts who are in the membership group but have the same email address as another contact that is also in the membership group.");
      }
    }

    // Delete records have the same hash - these do not need an update.
    // count for testing purposes.
    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(c.email) co FROM tmp_mautic_push_m m
      INNER JOIN tmp_mautic_push_c c ON m.cid_guess = c.contact_id AND m.hash = c.hash;");
    $dao->fetch();
    $count = $dao->co;
    if ($count > 0) {
      CRM_Core_DAO::executeQuery(
        "DELETE m, c
         FROM tmp_mautic_push_m m
         INNER JOIN tmp_mautic_push_c c ON m.cid_guess = c.contact_id AND m.hash = c.hash;");
    }
    CRM_Mautic_Utils::checkDebug("removeInSync removed $count in-sync contacts.");


    return $count + $doubles;
  }
  
  /**
   * "Push" sync.
   *
   * Sends additions, edits (compared to tmp_mautic_push_m), deletions.
   *
   * Note that an 'update' counted in the return stats could be a change or an
   * addition.
   *
   * @return array ['updates' => INT, 'unsubscribes' => INT]
   */
  public function updateMauticFromCivi() {
    CRM_Mautic_Utils::checkDebug("updateMauticFromCivi for group #$this->membership_group_id");
    $operations = [];
    $contactApi = self::getApi('contacts');
    $segmentApi = self::getApi('segments');
    
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT
      c.group_info c_group_info, c.first_name c_first_name, c.last_name c_last_name,
      c.email c_email, c.contact_id c_civicrm_contact_id, c.mautic_contact_id,
      m.group_info m_group_info, m.first_name m_first_name, m.last_name m_last_name,
      m.email m_email, m.mautic_contact_id m_mautic_contact_id, m.civicrm_contact_id m_civicrm_contact_id
      FROM tmp_mautic_push_c c
      LEFT JOIN tmp_mautic_push_m m ON c.email = m.email;");

    $changes = $additions = 0;
    $no_change = 0;
    // Field values for batch edit, including id.
    $edit = [];
    // Field values for create. We would then need to get the returned id and add to group.
    $create = [];
    // Contact ids to add to segment.
    $addToGroup = [];
    // Contact ids to remove from segment.
    $removals = [];
    while ($dao->fetch()) {
      $baseFields = [
        'email',
        'first_name', 
        'last_name', 
        'group_info',
        'mautic_contact_id',
        'civicrm_contact_id',
      ];
      $mParams = $cParams = [];
      foreach ($baseFields as $baseField) {
        $mField = 'm_' . $baseField;
        $cField = 'c_' . $baseField;
        $mParams[$baseField] = isset($dao->{$mField}) ? $dao->{$mField} : NULL;
        $cParams[$baseField] = isset($dao->{$cField}) ? $dao->{$cField} : NULL;
        
      }
      $params = static::updateMauticFromCiviLogic($cParams, $mParams);
      if (!$params) {
        // No change required.
        $no_change++;
        continue;
      }
      // Exists on mautic.
      if (!empty($params['mautic_contact_id'])) {
        $mautic_contact_id = $params['mautic_contact_id'];
        unset($params['mautic_contact_id']);
 
        // We haven't retrieved from Mautic since it is not in the segment.
        // But we have the reference to the mautic contact id in the civi contact.
        // So we only need to add to the segment.
        if (!$params || empty($dao->m_group_info)) {
          $addToGroup[] = $mautic_contact_id;
        }
        elseif ($dao->m_group_info && $params) {
          // Updating the contact details. Already in the group.
          $params['id'] = $mautic_contact_id;
          $edit[] = $params;
        }
      }
      else {
        // create. We will then need to get the id and add to group.
        // We might possibly lookup by id here.
        $create[] = $params;
      }
      
      

      if ($this->dry_run) {
        // Log the operation description.
        $_ = "Would " . ($dao->m_email ? 'update' : 'create')
          . " mautic member: $dao->m_email";
        if (key_exists('email_address', $params)) {
          $_ .= " change email to '$params[email]'";
        }
        CRM_Mautic_Utils::checkDebug($_);
      }
    }

    // Now consider removals of those not in membership group at CiviCRM but
    // in Mautic Segment.
    $removals = $this->getEmailsNotInCiviButInMautic();
    if ($this->dry_run) {
      // Just log.
      if ($removals) {
        CRM_Mautic_Utils::checkDebug("Would unsubscribe " . count($removals) . " Mautic members: " . implode(', ', $removals));
      }
      else {
        CRM_Mautic_Utils::checkDebug("No Mautic members would be unsubscribed.");
      }
    }

    if (!$this->dry_run) {
      // Don't print_r all operations in the debug, because deserializing
      // allocates way too much memory if you have thousands of operations.
      // Also split batches in blocks of $batchSize to
      // avoid memory limit problems.
      $batchSize = self::MAUTIC_PUSH_BATCH_SIZE;
      $operations['edit'] = [
        'callback' => [$this, 'processEditBatch'],
        'data' => $edit,
      ];
      $operations['addToSegment'] = [
        'callback' => [$this, 'processAddToSegmentBatch'],
        'data' => $addToGroup,
      ];
      $operations['removeFromSegment'] = [
        'callback' => [$this, 'processRemoveFromSegmentBatch'],
        'data' => $removals,
      ];
      $operations['create'] = [
        'callback' => [$this, 'processCreateBatch'],
        'data' => $create,
      ];
      foreach ($operations as $operation) {
        $this->batchAPIOperation($batchSize, $operation['data'], $operation['callback']);
      }
    }
    
    // Development debugging. Mautic API logs to SESSION
    // But is too verbose to be useful unless you need to drill down to requests.
    // U::checkDebug('sessiondump', $_SESSION);
    
    // Get in sync stats that were discovered via db.
    $stats = CRM_Mautic_Setting::get('mautic_push_stats');
    $in_sync = $stats[$this->segment_id]['in_sync'];
    $in_sync = $in_sync ? $in_sync + $no_change : $no_change;
    return [
      'additions' => count($create),
      'updates' => count($edit),
      'unsubscribes' => count($removals),
      'in_sync' => $in_sync, 
    ];
  }
  // @todo refactor to own class.
  
  protected function processEditBatch($data) {
    U::checkDebug('editBatch', $data);
    $api = $this->getApi('contacts');
    $result = $api->editBatch($data, TRUE);
    U::checkDebug('editBatchResult', $result);
  }
  
  protected function processAddToSegmentBatch($data) {
    U::checkDebug(__FUNCTION__, ['segment_id' => $this->segment_id, 'ids' => $data]);
    $api = $this->getApi('segments');
    // Data should be array of contact ids.
    $data = array_filter($data, 'is_numeric');
    // return;
    if ($data && $this->segment_id) {
      $result = $api->addContacts($this->segment_id, ['ids' => $data]);
      if (!empty($result['errors'])) {
        U::checkDebug(__FUNCTION__ . ': ErrorAddingBatchContacts', $result);
      }
      else {
        U::checkDebug(__FUNCTION__ . ': AddedContacts', $result);
      }
    }
    else {
      U::checkDebug(__FUNCTION__ . ': Invalid data', $data);
    }
  }
  
  protected function processRemoveFromSegmentBatch($data) {
    U::checkDebug(__FUNCTION__, $data);
    $api = $this->getApi('segments');
    $data = array_filter($data, 'is_numeric');
    if ($data && $this->segment_id) {
      // Segment API doesn't have a batch operation for removing contacts.
      foreach ($data as $id) {
        $api->removeContact($this->segment_id, $id);
      }
      if (!empty($result['errors'])) {
        U::checkDebug(__FUNCTION__ . 'resultError.', [$result, $api->getResponseInfo()]);
      }
      else {
        U::checkDebug(__FUNCTION__ . ': RemovedContacts', $result);
      }
    }
    else {
      U::checkDebug(__FUNCTION__ . ': Invalid data', $data);
    }
  }
  
  protected function processCreateBatch($data) {
    U::checkDebug(__FUNCTION__, $data);        
    $api = $this->getApi('contacts');
    $result = $api->createBatch($data);
    $ids = [];
    foreach ($result['contacts'] as $created) {
      if (!empty($created['id'])) {
        $ids[] = $created['id'];
      }
    }
    if ($ids) {
      $this->processAddToSegmentBatch($ids);
    }
    
  }
  
  
  /**
   * Perform an operation in batches.
   * 
   * @param int $batchSize
   * @param array $data
   * @param callable $function
   */
  protected function batchAPIOperation($batchSize, $data, $function) {
    if ($data && is_array($data)) {
      $batches = array_chunk($data, $batchSize, TRUE);
      CRM_Mautic_Utils::checkDebug("Batching " . count($data) . " operations into " . count($batches) . " batches.");
      foreach ($batches as &$batch) {
        call_user_func($function, $batch);
      }
      unset($batch);
    }
  }

  /**
   * "Pull" sync.
   *
   * Updates CiviCRM from Mautic using the tmp_mautic_push_[cm] tables.
   *
   * It is assumed that collections (in 'pull' mode) and `removeInSync` have
   * already run.
   *
   * 1. Loop the full tmp_mautic_push_m table:
   *
   *    1. Contact identified by collectMautic()?
   *       - Yes: update name if different.
   *       - No:  Create or find-and-update the contact.
   *
   *    2. Check for changes in groups; record what needs to be changed for a
   *       batch update.
   *
   * 2. Batch add/remove contacts from groups.
   *
   * @return array With the following keys:
   *
   * - created: was in MC not CiviCRM so a new contact was created
   * - joined : email matched existing contact that was joined to the membership
   *            group.
   * - in_sync: was in MC and on membership group already.
   * - removed: was not in MC but was on membership group, so removed from
   *            membership group.
   * - updated: No. in_sync or joined contacts that were updated.
   *
   * The initials of these categories c, j, i, r correspond to this diagram:
   *
   *     From Mautic: ************
   *     From CiviCRM  :         ********
   *     Result        : ccccjjjjiiiirrrr
   *
   * Of the contacts known in both systems (j, i) we also record how many were
   * updated (e.g. name, group_info).
   *
   * Work in pass 1:
   *
   * - create|find
   * - join
   * - update names
   * - update group_info
   *
   * Work in pass 2:
   *
   * - remove
   */
  public function updateCiviFromMautic() {

    // Ensure posthooks don't trigger while we make GroupContact changes.
    CRM_Mautic_Utils::$post_hook_enabled = FALSE;

    // This is a functional variable, not a stats. one
    $changes = ['removals' => [], 'additions' => []];

    CRM_Mautic_Utils::checkDebug("updateCiviFromMautic for group #$this->membership_group_id");

    // Stats.
    $stats = [
      'created' => 0,
      'joined'  => 0,
      'in_sync' => 0,
      'removed' => 0,
      'updated' => 0,
      ];

    // all Mautic table *except*  where the contact matches multiple
    // contacts in CiviCRM.
    $dao = CRM_Core_DAO::executeQuery( "SELECT m.*,
      c.contact_id c_contact_id,
      c.group_info c_group_info, c.first_name c_first_name, c.last_name c_last_name
      FROM tmp_mautic_push_m m
      LEFT JOIN tmp_mautic_push_c c ON m.cid_guess = c.contact_id
      WHERE m.cid_guess IS NOT NULL
      ;");

    // Create lookup hash to map Mautic Segment to CiviCRM Groups.
    $interest_to_group_id = [];
    foreach ($this->interest_group_details as $group_id=>$details) {
      $interest_to_group_id[$details['segment_id']] = $group_id;
    }

    // Loop records found at Mautic, creating/finding contacts in CiviCRM.
    while ($dao->fetch()) {
      $existing_contact_changed = FALSE;

      if (!empty($dao->cid_guess)) {
        // Matched existing contact: result: joined or in_sync
        $contact_id = $dao->cid_guess;

        if ($dao->c_contact_id) {
          // Contact is already in the membership group.
          $stats['in_sync']++;
        }
        else {
          // Contact needs joining to the membership group.
          $stats['joined']++;
          if (!$this->dry_run) {
            // Live.
            $changes['additions'][$this->membership_group_id][] = $contact_id;
          }
          else {
            // Dry Run.
            CRM_Mautic_Utils::checkDebug("Would add existing contact to membership group. Email: $dao->email Contact Id: $dao->cid_guess");
          }
        }

        // Update the first name and last name of the contacts we know
        // if needed and making sure we don't overwrite
        // something with nothing. See issue #188.
        $edits = static::updateCiviFromMauticContactLogic(
          ['first_name' => $dao->first_name,   'last_name' => $dao->last_name],
          ['first_name' => $dao->c_first_name, 'last_name' => $dao->c_last_name]
        );
        if ($edits) {
          if (!$this->dry_run) {
            // There are changes to be made so make them now.
            civicrm_api3('Contact', 'create', ['id' => $contact_id] + $edits);
          }
          else {
            // Dry run.
            CRM_Mautic_Utils::checkDebug("Would update CiviCRM contact $dao->cid_guess "
              . (empty($edits['first_name']) ? '' : "First name from $dao->c_first_name to $dao->first_name ")
              . (empty($edits['last_name']) ? '' : "Last name from $dao->c_last_name to $dao->last_name "));
          }
          $existing_contact_changed = TRUE;
        }
      }
      else {
        // Contact does not exist, create a new one.
        if (!$this->dry_run) {
          // Live:
          $result = civicrm_api3('Contact', 'create', [
            'contact_type' => 'Individual',
            'first_name'   => $dao->first_name,
            'last_name'    => $dao->last_name,
            'email'        => $dao->email,
            'sequential'   => 1,
            ]);
          $contact_id = $result['values'][0]['id'];
          $changes['additions'][$this->membership_group_id][] = $contact_id;
        }
        else {
          // Dry Run:
          CRM_Mautic_Utils::checkDebug("Would create new contact with email: $dao->email, name: $dao->first_name $dao->last_name");
          $contact_id = 'dry-run';
        }
        $stats['created']++;
      }

      // Do group_info need updating?
      if ($dao->c_group_info && $dao->c_group_info == $dao->group_info) {
        // Nothing to change.
      }
      else {
        // Unpack the group_info reported by MC
        $mautic_group_info = unserialize($dao->group_info);
        if ($dao->c_group_info) {
          // Existing contact.
          $existing_contact_changed = TRUE;
          $civi_group_info = unserialize($dao->c_group_info);
        }
        else {
          // Newly created contact is not in any interest groups.
          $civi_group_info = [];
        }

        // Discover what needs changing to bring CiviCRM inline with Mautic.
        foreach ($mautic_group_info as $interest=>$member_has_interest) {
          if ($member_has_interest && empty($civi_group_info[$interest])) {
            // Member is interested in something, but CiviCRM does not know yet.
            if (!$this->dry_run) {
              $changes['additions'][$interest_to_group_id[$interest]][] = $contact_id;
            }
            else {
              CRM_Mautic_Utils::checkDebug("Would add CiviCRM contact $dao->cid_guess to interest group "
                . $interest_to_group_id[$interest]);
            }
          }
          elseif (!$member_has_interest && !empty($civi_group_info[$interest])) {
            // Member is not interested in something, but CiviCRM thinks it is.
            if (!$this->dry_run) {
              $changes['removals'][$interest_to_group_id[$interest]][] = $contact_id;
            }
            else {
              CRM_Mautic_Utils::checkDebug("Would remove CiviCRM contact $dao->cid_guess from interest group "
                . $interest_to_group_id[$interest]);
            }
          }
        }
      }

      if ($existing_contact_changed) {
        $stats['updated']++;
      }
    }

    // And now, what if a contact is not in the Mautic segment?
    // We must remove them from the membership group.
    // Accademic interest (#188): what's faster, this or a 'WHERE NOT EXISTS'
    // construct?
    $dao = CRM_Core_DAO::executeQuery( "
    SELECT c.contact_id
      FROM tmp_mautic_push_c c
      LEFT OUTER JOIN tmp_mautic_push_m m ON m.cid_guess = c.contact_id
      WHERE m.email IS NULL;
      ");
    // Collect the contact_ids that need removing from the membership group.
    while ($dao->fetch()) {
      if (!$this->dry_run) {
        $changes['removals'][$this->membership_group_id][] =$dao->contact_id;
      }
      else {
        CRM_Mautic_Utils::checkDebug("Would remove CiviCRM contact $dao->contact_id from membership group - no longer subscribed at Mautic.");
      }
      $stats['removed']++;
    }

    if (!$this->dry_run) {
      // Log group contacts which are going to be added/removed to/from CiviCRM
      CRM_Mautic_Utils::checkDebug('Mautic $changes', $changes);

      // Make the changes.
      if ($changes['additions']) {
        // We have some contacts to add into groups...
        foreach($changes['additions'] as $groupID => $contactIDs) {
          CRM_Contact_BAO_GroupContact::addContactsToGroup($contactIDs, $groupID, 'Admin', 'Added');
        }
      }

      if ($changes['removals']) {
        // We have some contacts to add into groups...
        foreach($changes['removals'] as $groupID => $contactIDs) {
          CRM_Contact_BAO_GroupContact::removeContactsFromGroup($contactIDs, $groupID, 'Admin', 'Removed');
        }
      }
    }

    // Re-enable the post hooks.
    CRM_Mautic_Utils::$post_hook_enabled = TRUE;

    return $stats;
  }

  /**
   * Get segment of emails to unsubscribe.
   *
   * We *exclude* any emails in Mautic that matched multiple contacts in
   * CiviCRM - these have their cid_guess field set to NULL.
   *
   * @return array
   */
  public function getEmailsNotInCiviButInMautic($field = 'mautic_contact_id') {
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT m.email, m.mautic_contact_id
       FROM tmp_mautic_push_m m
       WHERE cid_guess IS NOT NULL
         AND NOT EXISTS (
           SELECT c.contact_id FROM tmp_mautic_push_c c WHERE c.contact_id = m.cid_guess
         );");

    $return = [];
    while ($dao->fetch()) {
      if ($field == 'email')
        $return[] = $dao->email;
      else {
        $return[] = intval($dao->mautic_contact_id);
      }
    }
    U::checkDebug('removals', $return);
    return $return;
  }
  /**
   * Return a count of the members on Mautic from the tmp_mautic_push_m
   * table.
   */
  public function countMauticMembers() {
    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(*) c  FROM tmp_mautic_push_m");
    $dao->fetch();
    return $dao->c;
  }

  /**
   * Return a count of the members on CiviCRM from the tmp_mautic_push_c
   * table.
   */
  public function countCiviCrmMembers() {
    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(*) c  FROM tmp_mautic_push_c");
    $dao->fetch();
    return $dao->c;
  }

  /**
   * Sync a single contact's membership and interests for this segment from their
   * details in CiviCRM.
   *
   */
  public function updateMauticFromCiviSingleContact($contact_id) {
    // @todo: implement.
    return;
    
    
    // Get all the groups related to this segment that the contact is currently in.
    // We have to use this dodgy API that concatenates the titles of the groups
    // with a comma (making it unsplittable if a group title has a comma in it).
    $contact = civicrm_api3('Contact', 'getsingle', [
      'contact_id' => $contact_id,
      'return' => ['first_name', 'last_name', 'email_id', 'email', 'group'],
      'sequential' => 1
      ]);

    $in_groups = CRM_Mautic_Utils::getGroupIds($contact['groups'], $this->group_details);
    $currently_a_member = in_array($this->membership_group_id, $in_groups);

    if (empty($contact['email'])) {
      // Without an email we can't do anything.
      return;
    }
    $subscriber_hash = md5(strtolower($contact['email']));
    $api = CRM_Mautic_Utils::getMauticApi();

    if (!$currently_a_member) {
      // They are not currently a member.
      //
      // We should ensure they are unsubscribed from Mautic. They might
      // already be, but as we have no way of telling exactly what just changed
      // at our end, we have to make sure.
      //
      // Nb. we don't bother updating their interests for unsubscribes.
      try {
        $result = $api->patch("/segments/$this->segment_id/members/$subscriber_hash",
          ['status' => 'unsubscribed']);
      }
      catch (CRM_Mautic_Exception_RequestErrorException $e) {
        if ($e->response->http_code == 404) {
          // OK. Mautic didn't know about them anyway. Fine.
        }
        else {
          CRM_Core_Session::setStatus(ts('There was a problem trying to unsubscribe this contact at Mautic; any differences will remain until a CiviCRM to Mautic Sync is done.'));
        }
      }
      catch (CRM_Mautic_Exception_NetworkErrorException $e) {
        CRM_Core_Session::setStatus(ts('There was a network problem trying to unsubscribe this contact at Mautic; any differences will remain until a CiviCRM to Mautic Sync is done.'));
      }
      return;
    }

    // Now left with 'subscribe' case.
    //
    // Do this with a PUT as this allows for both updating existing and
    // creating new members.
    $data = [
      'status' => 'subscribed',
      'email_address' => $contact['email'],
      'merge_fields' => [
        'FNAME' => $contact['first_name'],
        'LNAME' => $contact['last_name'],
        ],
    ];
    // Do interest groups.
    if (empty($data['interests'])) {
      unset($data['interests']);
    }
    try {
      $result = $api->put("/segments/$this->segment_id/members/$subscriber_hash", $data);
    }
    catch (CRM_Mautic_Exception_NetworkErrorException $e) {
      CRM_Core_Session::setStatus(ts('There was a network problem trying to unsubscribe this contact at Mautic; any differences will remain until a CiviCRM to Mautic Sync is done.'));
    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus(ts('There was a problem trying to subscribe this contact at Mautic:') . $e->getMessage());
    }
   
  }
  /**
   * Identify a contact who is expected to be subscribed to this segment.
   *
   * This is used in a couple of cases, for finding a contact from incomming
   * data for:
   * - a possibly new contact, 
   * - a contact that is expected to be in this membership group.
   *
   * Here's how we match a contact:
   *
   * - Only non-deleted contacts are returned.
   *
   * - Email is unique in CiviCRM
   *   Contact identified, unless limited to in-group only and not in group.
   *
   * - Email is entered 2+ times, but always on the same contact.
   *   Contact identified, unless limited to in-group only and not in group.
   *
   * - Email belongs to 2+ different contacts. In this situation, if there are
   *   some contacts that are in the membership group, we ignore the other match
   *   candidates. If limited to in-group contacts and there aren't any, we give
   *   up now.
   *
   *   - Email identified if it belongs to only one contact that is in the
   *     membership segment.
   *
   *   - Look to the candidates whose last name matches.
   *     - Email identified if there's only one last name match.
   *     - If there are any contacts that also match first name, return one of
   *       these. We say it doesn't matter if there's duplicates - just pick
   *       one since everything matches.
   *
   *   - Email identified if there's a single contact that matches on first
   *     name.
   *
   * We fail with a CRM_Mautic_DuplicateContactsException if the email
   * belonged to several contacts and we could not narrow it down by name.
   *
   * @param string $email
   * @param string|null $first_name
   * @param string|null $last_name
   * @param bool $must_be_on_segment    If TRUE, only return an ID if this contact
   *                                 is known to be on the segment. defaults to
   *                                 FALSE. 
   * @throw CRM_Mautic_DuplicateContactsException if the email is known bit
   * it fails to identify one contact.
   * @return int|null Contact Id if found.
   */
  public function guessContactIdSingle($email, $first_name=NULL, $last_name=NULL, $must_be_on_segment=FALSE) {

    // API call returns all matching emails, and all contacts attached to those
    // emails IF the contact is in our group.
    $result = civicrm_api3('Email', 'get', [
      'sequential'      => 1,
      'email'           => $email,
      'api.Contact.get' => [
              'is_deleted' => 0,
              'return'     => "first_name,last_name"],
    ]);

    // Candidates are any emails that belong to a not-deleted contact.
    $email_candidates = array_filter($result['values'], function($_) {
      return ($_['api.Contact.get']['count'] == 1);
    });
    if (count($email_candidates) == 0) {
      // Never seen that email, mate.
      return NULL;
    }

    // $email_candidates is currently a sequential segment of emails. Instead map it to
    // be indexed by contact_id.
    $candidates = [];
    foreach ($email_candidates as $_) {
      $candidates[$_['contact_id']] = $_['api.Contact.get']['values'][0];
    }

    // Now we need to know which, if any of these contacts is in the group.
    // Build segment of contact_ids.
    $result = civicrm_api3('Contact', 'get', [
      'group' => $this->membership_group_id,
      'contact_id' => ['IN' => array_keys($candidates)],
      'is_opt_out' => 0,
      'do_not_email' => 0,
      'on_hold' => 0,
      'is_deceased' => 0,
      'return' => 'contact_id',
      ]);
    $in_group = $result['values'];

    // If must be on the membership segment, then reduce the candidates to just
    // those on the segment.
    if ($must_be_on_segment) {
      $candidates = array_intersect_key($candidates, $in_group);
      if (count($candidates) == 0) {
        // This email belongs to a contact *not* in the group.
        return NULL;
      }
    }

    if (count($candidates) == 1) {
      // If there's only one one contact match on this email anyway, then we can
      // assume that's the person. (we make this assumption in
      // guessContactIdsByUniqueEmail too.)
      return key($candidates);
    }

    // Now we're left with the case that the email matched more than one
    // different contact.

    if (count($in_group) == 1) {
      // There's only one contact that is in the membership group with this
      // email, use that.
      return key($in_group);
    }

    // The email belongs to multiple contacts.
    if ($in_group) {
      // There are multiple contacts that share the same email and several are
      // in this group. Narrow our serach to just those in the group.
      $candidates = array_intersect_key($candidates, $in_group);
    }

    // Make indexes on names.
    $last_name_matches = $first_name_matches = [];
    foreach ($candidates as $candidate) {
      if (!empty($candidate['first_name']) && ($first_name == $candidate['first_name'])) {
        $first_name_matches[$candidate['contact_id']] = $candidate;
      }
      if (!empty($candidate['last_name']) && ($last_name == $candidate['last_name'])) {
        $last_name_matches[$candidate['contact_id']] = $candidate;
      }
    }

    // Now see if we can find them by name match.
    if ($last_name_matches) {
      // Some of the contacts have the same last name.
      if (count($last_name_matches) == 1) {
        // Only one contact with this email has the same last name, let's say
        // it's them.
        return key($last_name_matches);
      }
      // Multiple contacts with same last name. Reduce by same first name.
      $last_name_matches = array_intersect_key($last_name_matches, $first_name_matches);
      if (count($last_name_matches) > 0) {
        // Either there was only one with same last and first name.
        // Or, there were multiple contacts, but they have the same email and
        // name so let's say that we're safe enough to pick the first one of
        // them.
        return key($last_name_matches);
      }
    }
    // Last name didn't get there. Final chance. If the email and first name
    // match a single contact, we'll grudgingly(!) say that's OK.
    if (count($first_name_matches) == 1) {
      // Only one contact with this email has the same first name, let's say
      // it's them.
      return key($first_name_matches);
    }

    // The email given belonged to several contacts and we were unable to narrow
    // it down by the names, either. There's nothing we can do here, it's going
    // to get messy.
    throw new CRM_Mautic_Exception_DuplicateContactsException($candidates);
  }

  /**
   * Guess the contact id for contacts whose email is found in the temporary
   * table made by collectCiviCrm.
   *
   * If collectCiviCrm has been run, then we can identify matching contacts very
   * easily. This avoids problems with multiple contacts in CiviCRM having the
   * same email address but only one of them is subscribed. :-)
   *
   * **WARNING** it would be dangerous to run this if collectCiviCrm() had been run
   * on a different segment(!). For this reason, these conditions are checked by
   * collectMautic().
   *
   * This is in a separate method so it can be tested.
   *
   * @return int affected rows.
   */
  public static function guessContactIdsBySubscribers() {
    return static::runSqlReturnAffectedRows(
       "UPDATE tmp_mautic_push_m m
        INNER JOIN tmp_mautic_push_c c ON m.email = c.email
        SET m.cid_guess = c.contact_id
        WHERE m.cid_guess IS NULL");
  }
  
  // @todo: guessContactIdsByCustomField

  /**
   * Guess the contact id by there only being one email in CiviCRM that matches.
   *
   * Change in v2.0: it now checks uniqueness by contact id, so if the same
   * email belongs multiple times to one contact, we can still conclude we've
   * got the right contact.
   *
   * This is in a separate method so it can be tested.
   * @return int affected rows.
   */
  public static function guessContactIdsByUniqueEmail() {
    // If an address is unique, that's the one we need.
    return static::runSqlReturnAffectedRows(
        "UPDATE tmp_mautic_push_m m
        INNER JOIN (
          SELECT email, c.id AS contact_id
          FROM civicrm_email e
          JOIN civicrm_contact c ON e.contact_id = c.id AND c.is_deleted = 0
          AND c.do_not_email = 0 AND c.do_not_mail = 0 AND c.is_opt_out = 0 AND e.on_hold = 0
          GROUP BY email, c.id
          HAVING COUNT(DISTINCT c.id)=1
          ) uniques ON m.email = uniques.email
        SET m.cid_guess = uniques.contact_id
        WHERE m.cid_guess IS NULL
        ");
  }
  /**
   * Guess the contact id for contacts whose only email matches.
   *
   * This is in a separate method so it can be tested.
   * See issue #188
   *
   * v2 includes rewritten SQL because of a bug that caused the test to fail.
   * @return int affected rows.
   */
  public static function guessContactIdsByNameAndEmail() {

    // In the other case, if we find a unique contact with matching
    // first name, last name and e-mail address, it is probably the one we
    // are looking for as well.

    // look for email and names that match where there's only one match.
    return static::runSqlReturnAffectedRows(
        "UPDATE tmp_mautic_push_m m
        INNER JOIN (
          SELECT email, first_name, last_name, c.id AS contact_id
          FROM civicrm_email e
          JOIN civicrm_contact c ON e.contact_id = c.id AND c.is_deleted = 0
          AND c.do_not_email = 0 AND c.do_not_mail = 0 AND c.is_opt_out = 0 AND e.on_hold = 0
          GROUP BY email, first_name, last_name, c.id
          HAVING COUNT(DISTINCT c.id)=1
          ) uniques ON m.email = uniques.email AND m.first_name = uniques.first_name AND m.last_name = uniques.last_name
        SET m.cid_guess = uniques.contact_id
        WHERE m.first_name != '' AND m.last_name != ''  AND m.cid_guess IS NULL
        ");
  }
  /**
   * Drop tmp_mautic_push_m and tmp_mautic_push_c, if they exist.
   *
   * Those tables are created by collectMautic() and collectCiviCrm()
   * for the purposes of syncing to/from Mautic/CiviCRM and are not needed
   * outside of those operations.
   */
  public static function dropTemporaryTables() {
    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS tmp_mautic_push_m;");
    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS tmp_mautic_push_c;");
  }
  /**
   * Drop mautic_log table if it exists.
   *
   * This table holds errors from multiple segments in Mautic where the contact
   * could not be identified in CiviCRM; typically these contacts are
   * un-sync-able ("Titanics").
   */
  public static function dropLogTable() {
    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS mautic_log;");
  }
  /**
   * Create new tmp_mautic_push_m.
   *
   * Nb. these are temporary tables but we don't use TEMPORARY table because
   * they are needed over multiple sessions because of queue.
   *
   *
   * cid_guess column is the contact id that this record will be sync-ed to.
   * It after both collections and a matchMauticMembersToContacts call it
   * will be
   *
   * - A contact id
   * - Zero meaning we can create a new contact
   * - NULL meaning we must ignore this because otherwise we might end up
   *   making endless duplicates.
   *
   * Because a lot of matching is done on this, it has an index. Nb. a test was
   * done trying the idea of adding the non-unique key at the end of the
   * collection; heavily-keyed tables can slow down mass-inserts, so sometimes's
   * it's quicker to add an index after an update. However this only saved 0.1s
   * over 5,000 records import, so this code was removed for the sake of KISS.
   *
   * The speed of collecting from Mautic, is, as you might expect, determined
   * by Mautic's API which seems to take about 3s for 1,000 records.
   * Inserting them into the tmp table takes about 1s per 1,000 records on my
   * server, so about 4s/1000 members.
   */
  public static function createTemporaryTableForMautic() {
    CRM_Core_DAO::executeQuery( "DROP TABLE IF EXISTS tmp_mautic_push_m;");
    $dao = CRM_Core_DAO::executeQuery(
      "CREATE TABLE tmp_mautic_push_m (
        email VARCHAR(200) NOT NULL,
        first_name VARCHAR(100) NOT NULL DEFAULT '',
        last_name VARCHAR(100) NOT NULL DEFAULT '',
        hash CHAR(32) NOT NULL DEFAULT '',
        group_info VARCHAR(4096) NOT NULL DEFAULT '',
        cid_guess INT(10) DEFAULT NULL,
        mautic_contact_id INT(10) NOT NULL,
        civicrm_contact_id INT(10) DEFAULT NULL,
        PRIMARY KEY (email, hash),
        KEY (cid_guess)
        )
        ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;");

    // Convenience in collectMautic.
    return $dao;
  }
  /**
   * Create new tmp_mautic_push_c.
   *
   * Nb. these are temporary tables but we don't use TEMPORARY table because
   * they are needed over multiple sessions because of queue.
   */
  public static function createTemporaryTableForCiviCRM() {
    CRM_Core_DAO::executeQuery( "DROP TABLE IF EXISTS tmp_mautic_push_c;");
    $dao = CRM_Core_DAO::executeQuery("CREATE TABLE tmp_mautic_push_c (
        contact_id INT(10) UNSIGNED NOT NULL,
        email VARCHAR(200) NOT NULL,
        first_name VARCHAR(100) NOT NULL DEFAULT '',
        last_name VARCHAR(100) NOT NULL DEFAULT '',
        hash CHAR(32) NOT NULL DEFAULT '',
        group_info VARCHAR(4096) NOT NULL DEFAULT '',
        mautic_contact_id INT(10) DEFAULT NULL,
        PRIMARY KEY (email, hash),
        KEY (contact_id)
        )
        ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;");
    return $dao;
  }
  /**
   * Logic to determine update needed.
   *
   * This is separate from the method that collects a batch update so that it
   * can be tested more easily.
   *
   * @param array $merge_fields an array where the *keys* are 'tag' names from
   * Mautic's merge_fields resource. e.g. FNAME, LNAME.
   * @param array $civi_details Array of civicrm details from
   * tmp_mautic_push_c
   * @param array $mautic_details Array of mautic details from
   * tmp_mautic_push_m
   * @return array changes in format required by Mautic API.
   */
  public static function updateMauticFromCiviLogic($civi_details, $mautic_details) {
    $params = [];
    // I think possibly some installations don't have Multibyte String Functions
    // installed?
    $lower = function_exists('mb_strtolower') ? 'mb_strtolower' : 'strtolower';
    
    // The contact exists in mautic but is not a member of the group.
    // This will just need adding to the group.
    if (!empty($civi_details['mautic_contact_id']) && empty($mautic_details['mautic_contact_id'])) {
      $params['mautic_contact_id'] = $civi_details['mautic_contact_id'];
      return $params;
    }
    if ($civi_details['civicrm_contact_id'] && empty($mautic_details['civicrm_contact_id'])) {
      $params['civicrm_contact_id'] = $civi_details['civicrm_contact_id'];
    }
    
    if ($civi_details['email'] && $lower($civi_details['email']) != $lower($mautic_details['email'])) {
      // This is the case for additions; when we're adding someone new.
      $params['email'] = $civi_details['email'];
    }

    if ($civi_details['first_name'] && trim($civi_details['first_name']) != trim($mautic_details['first_name'])) {
      // First name mismatch.
      $params['firstname'] = $civi_details['first_name'];
    }
    if ($civi_details['last_name'] && trim($civi_details['last_name']) != trim($mautic_details['last_name'])) {
      $params['lastname'] = $civi_details['last_name'];
    }
    // Already exists on mautic and a change is required.
    if (!empty($params) && !empty($mautic_details['mautic_contact_id'])) {
      $params['mautic_contact_id'] = $mautic_details['mautic_contact_id'];
    }

    return $params;
  }

  /**
   * Logic to determine update needed for pull.
   *
   * This is separate from the method that collects a batch update so that it
   * can be tested more easily.
   *
   * @param array $mautic_details Array of mautic details from
   * tmp_mautic_push_m, with keys first_name, last_name
   * @param array $civi_details Array of civicrm details from
   * tmp_mautic_push_c, with keys first_name, last_name
   * @return array changes in format required by Mautic API.
   */
  public static function updateCiviFromMauticContactLogic($mautic_details, $civi_details) {

    $edits = [];

    foreach (['first_name', 'last_name'] as $field) {
      if ($mautic_details[$field] && $mautic_details[$field] != $civi_details[$field]) {
        $edits[$field] = $mautic_details[$field];
      }
    }

    return $edits;
  }

  /**
   * There's probably a better way to do this.
   */
  public static function runSqlReturnAffectedRows($sql, $params = array()) {
    $dao = new CRM_Core_DAO();
    $q = CRM_Core_DAO::composeQuery($sql, $params);
    $result = $dao->query($q);
    if (is_a($result, 'DB_Error')) {
      throw new Exception ($result->message . "\n" . $result->userinfo);
    }
    $dao->free();
    return $result;
  }
}

