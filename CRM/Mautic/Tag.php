<?php
use CRM_Mautic_Utils as U;

class CRM_Mautic_Tag {

  const MAUTIC_TAG_COLOR = '#4E5E9E';

  private $syncTagMethod  = '';

  private $tagParent = NULL;

  private $contactData = [];

  /**
   * Creates a tag to be the parent of tags imported from Mautic.
   *
   * @return int
   *  ID of tag.
   */
  public static function createParentTag() {
    $params = [
      'name' => 'Mautic',
      'used_for' => 'civicrm_contact',
    ];
    $result = U::civiApi('Tag', 'get', $params);
    if (empty($result['id'])) {
      $params += [
        'parent' => NULL,
         'description' => ts('Parent tag for tags synched from Mautic'),
         'is_tagset' => 1,
         'color' => self::MAUTIC_TAG_COLOR,
        ];
      $result = U::civiApi('Tag', 'create', $params);
      U::checkDebug("Create Parent Tag", $result);
    }
    U::checkDebug('create parent tag results', $result);
    return CRM_Utils_Array::value('id', $result);
  }

  public function __construct($params = []) {
    $this->syncTagMethod = CRM_Utils_Array::value('sync_tag_method', $params, Civi::settings()->get('mautic_sync_tag_method'));
    $this->tagParent = CRM_Utils_Array::value('sync_tag_parent', $params, Civi::settings()->get('mautic_sync_tag_parent'));
  }

  public function isSync() {
    return $this->syncTagMethod != 'none';
  }

  /**
   * Optionally set object state to use instead of retrieving contact data.
   */
  public function setData($civicrmContact = [], $mauticContact = []) {
    if (!empty($contact['id'])) {
      $this->contactData[$contact['id']] = [
        'civicrm_contact' => $contact,
        'mautic_contact' => $mauticContact,
      ];
    }
  }

  /**
   * Get some cached contact data.
   *
   * @param unknown $contactId
   * @
   */
  protected function getData($contactId, $from = 'civicrm_contact', $property = NULL) {
    $return = [];
    if ($contactId && !empty($this->contactData[$contactId][$from])) {
      $return = $this->contactData[$contactId][$from];
      if ($property && empty($return[$property])) {
        return;
      }
      else {
        $return = $return[$property];
      }
    }
    return $return;
  }

  /**
   * Builds mautic tag data for a contact.
   *
   * @param int $contactId
   *  CiviCRM Contact ID.
   *
   * @param bool $emptyIfNoChange
   *  If true will return an empty array if no changes to the Mautic contact tags are required to be pushed.
   *
   */
  public function getCiviTagsForMautic($contactId, $emptyIfNoChange = FALSE) {
    $tags = [];
    switch ($this->syncTagMethod) {
      case 'none' :
       break;
      case 'no_remove':
        $tags = $this->getContactTags($contactId);
        break;
      case 'sync_tag_children':
        if (!$this->tagParent) {
          U::checkDebug('Missing tag sync parent.');
        }
        $tags = $this->getContactTags($contactId, $this->tagParent);
        break;
    }
    $mauticTags = $this->getMauticContactTags($contactId);
    U::checkDebug('mautictags', $mauticTags);
    $removeTags = array_filter(
        array_map(function($tag) use ($tags)  {
          return !empty($tag['tag']) && !in_array($tag['tag'], $tags) ? '-' . $tag['tag'] : NULL;
    }, $mauticTags));
    $tags = array_merge($tags, $removeTags);
    $changeRequired = !$removeTags && count($mauticTags) == count($tags);
    U::checkDebug('tags', $tags);
    return !$changeRequired && $emptyIfNoChange ? [] : array_values($tags);
  }

  /**
   * Gets the Mautic tags for a contact.
   *
   * @param int $contactId
   * @param int|null $parentId
   * @param boolean $keyByEntityTagId
   *   Whether to return results keyed by EntityTag Id. By default results are keyed by Tag Id.
   * @return string[]
   *  Array of tag names, keyed by tag Id or entity tag Id.
   */
  private function getContactTags($contactId, $parentId = NULL, $keyByEntityTagId = FALSE) {

    $params = [
      'entity_id' => $contactId,
      'entity_table' => 'civicrm_contact',
      'sequential' => TRUE,
      'api.Tag.get' => [
        'id' => "\$value.tag_id",
      ],
    ];
    if ($parentId) {
      $params['api.Tag.get']['parent_id'] = $parentId;
    }
    $result = U::civiApi('EntityTag', 'get', $params);
    $tags = [];
    foreach ($result['values'] as $item) {
      if (empty($item['api.Tag.get']['values'])) {
        continue;
      }
      $tag = $item['api.Tag.get']['values'][0];
      $key = $keyByEntityTagId ? $item['id'] : $tag['id'];
      $tags[$key] = $tag['name'];
    }
    return $tags;
  }

  /**
   * Get tags for the mautic contact.
   *
   * @param int $contactId
   * @return void|unknown|mixed
   */
  private function getMauticContactTags($contactId) {
    $tags = $this->getData($contactId, 'mautic_contact', 'tags');
    if (!$tags) {
      $contact = $this->getData($contactId, 'civicrm_contact');
      $contact = $contact ? $contact : ['id' => $contactId];
      $mauticId = CRM_Mautic_Contact_ContactMatch::getMauticFromCiviContact($contact);
      if ($mauticId) {
        $api = CRM_Mautic_Connection::singleton()->newApi('contacts');
        $mauticContactResult = $api->get($mauticId);
        $mauticContact = CRM_Utils_Array::value('contact', $mauticContactResult, []);
        U::checkDebug(__FUNCTION__ . 'maucicContactResult', $mauticContactResult);
        $this->setData(NULL, $mauticContact);
        $tags = CRM_Utils_Array::value('tags', $mauticContact);
      }
    }
    return $tags;
  }

  /**
   * Returns tags from a collection of names, creating them in CiviCRM if they do not exist.
   *
   * @param string[] $tagNames
   * @param int $parentId
   *
   * @return string[]
   *   Array of tag names keyed by tag id.
   */
  private function addTags($tagNames, $parentId = NULL) {
    if (!$tagNames) {
      return [];
    }
    $params = [
      'name' => ['IN' => $tagNames],
      'sequential' => FALSE,
    ];
    $result = U::civiApi('Tag', 'get', $params);
    $inCivi = [];
    foreach ($result['values'] as $tag) {
      // Ensure the tag has the Mautic parent otherwise it will not be included in push syncs.
      if ($parentId && empty($tag['parent_id'])) {
        $tag['parent_id'] = $parentId;
        $tag['color'] = self::MAUTIC_TAG_COLOR;
        U::civiApi('Tag', 'create', $tag);
      }
      $inCivi[$tag['id']] = $tag['name'];
    }
    $newTags = array_diff($tagNames, $inCivi);
    U::checkDebug('newtagsAdded', ['newtags' => $newTags, 'incivi' => $inCivi, 'tagnames' => $tagNames]);
    // Within tagset.
    if ($parentId) {
      $params['parent_id'] = $parentId;
    }
    foreach ($newTags as $tagName) {
      // Resuse params from the get call, replacing name.
      $params['name'] = $tagName;
      $params['color'] = self::MAUTIC_TAG_COLOR;
      $result = U::civiApi('Tag', 'create', $params);
      if (!empty($result['id'])) {
        $inCivi[$result['id']] = $tagName;
      }
    }
    return $inCivi;
  }

  /**
   * Save incoming tags from Mautic to CiviCRM Contact, according to settings.
   *
   * @param string[] $tagNames
   * @param int $contactId
   */
  public function saveContactTags($tagNames, $contactId = NULL) {
    if ($this->syncTagMethod == 'none') {
      return;
    }
    $parentId = $this->syncTagMethod == 'sync_tag_children' && $this->tagParent ? $this->tagParent : NULL;
    $civiTags = $this->addTags($tagNames, $parentId);
    // If we have a contact ID then add tags
    if ($contactId) {
      // Remove existing EntityTags that are not included in this set of names.
      // We only do this if Mautic tags are configured to be children of a tag set.
      if ($parentId) {
        $currTags = $this->getContactTags($contactId, $parentId);
        $toRemove = array_diff($currTags, $civiTags);
        if ($toRemove) {
          foreach ($toRemove as $tid => $name) {
            // Api EntityTag delete will only delete all tags for contact even though entityTag id is passed.
            // Use BAO directly.
            $delParam = [
              'entity_table' => 'civicrm_contact',
              'entity_id' => $contactId,
              'tag_id' => $tid,
              ];
            CRM_Core_BAO_EntityTag::del($delParam);
          }
        }
      }
      // Now add tags to contact.
      $toAdd = $currTags ? array_diff($civiTags, $currTags) : $civiTags;
      if ($toAdd) {
        $contactTagParams = [
          'entity_table' => 'civicrm_contact',
          'entity_id' => $contactId,
          'tag_id' => $toAdd,
        ];
        $res = U::civiApi('EntityTag', 'create', $contactTagParams);
      }
    }
  }


}
