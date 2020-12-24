<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

return [
  0 =>
  [
    'name' => 'Process Mautic Webhooks',
    'entity' => 'Job',
    'params' =>
    [
      'version' => 3,
      'name' => 'Process Mautic Webhooks',
      'description' => 'Process webhooks from Mautic',
      'run_frequency' => 'Always',
      'api_entity' => 'MauticWebHook',
      'api_action' => 'process',
    ],
  ],
];
