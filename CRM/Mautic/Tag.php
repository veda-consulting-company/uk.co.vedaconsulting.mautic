<?php
use CRM_Mautic_Utils as U;

class CRM_Mautic_Tag {

  const MAUTIC_TAG_COLOR = '#4E5E9E';

  public static function getParentTagID() {

  }



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
          'color' => self::MAUTIC_TAG_COLOR,
        ];
      $result = U::civiApi('Tag', 'create', $params);
      U::checkDebug("Create Parent Tag", $result);
    }
    U::checkDebug('create parent tag results', $result);
    return CRM_Utils_Array::value('id', $result);
  }


}
