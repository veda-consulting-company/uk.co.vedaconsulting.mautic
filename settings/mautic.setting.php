<?php

return [
  // Basic Auth Settings.
  'mautic_authentication_method' => [
    'group_name' => 'Mautic Settings',
    'group' => 'mautic',
    'name' => 'mautic_authentication_method',
    'type' => 'String',
    'add' => '4.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'The Authentication method enabled on your Mautic installation',
    'title' => 'Mautic Authentication method',
    'help_text' => '',
    'html_type' => 'radios',
    ''

  ],
  'mautic_basic_username' => [
    'group_name' => 'Mautic Settings',
    'group' => 'mautic',
    'name' => 'mautic_basic_username',
    'type' => 'String',
    'add' => '4.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Mautic Basic Authentication User Name',
    'title' => 'Mautic Basic Authentication User Name',
    'help_text' => '',
    'html_type' => 'Text',
    'html_attributes' => [
      'size' => 50,
    ],
    // No form element
  ],
  'mautic_basic_password' => [
    'group_name' => 'Mautic Settings',
    'group' => 'mautic',
    'name' => 'mautic_basic_password',
    'type' => 'String',
    'add' => '4.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Mautic Basic Authentication Password.',
    'title' => 'Mautic Basic Authentication Password.',
    'help_text' => '',
    'html_type' => 'Password',
    'html_attributes' => [
      'size' => 50,
    ],
  ],
  // OAuth 2.0 Settings.
  'mautic_client_id' => [
    'group_name' => 'Mautic Settings',
    'group' => 'mautic',
    'name' => 'mautic_client_id',
    'type' => 'String',
    'add' => '4.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Mautic Client ID',
    'title' => 'Mautic OAuth 2.0 Client ID',
    'help_text' => '',
    'html_type' => 'Text',
    'html_attributes' => [
      'size' => 50,
    ],
    'quick_form_type' => 'Element',
  ],
  // OAuth 2.0 mautic (Client) Secret
  'mautic_client_secret' => [
    'group_name' => 'Mautic Settings',
    'group' => 'mautic',
    'name' => 'mautic_client_secret',
    'type' => 'String',
    'add' => '4.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Mautic Client Secret',
    'title' => 'Mautic OAuth 2.0 Client Secret',
    'help_text' => '',
    'html_type' => 'Password',
    'html_attributes' => [
      'size' => 50,
    ],
    'quick_form_type' => 'Element',
  ],
  // OAuth 2.0, No UI. Retrieved and stored on Authentication/Refresh.
  // Temporary, lifespan 30 mins.
  // Stored as serialized array.
  // Can be used to initialize League\OAuth2\Client\Token\AccessToken().
  // Includes refresh_token property so should always be stored even if expired.
  'mautic_access_token' => [
    'group_name' => 'Mautic Settings',
    'group' => 'mautic',
    'name' => 'mautic_access_token',
    'type' => 'String',
    'add' => '4.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Mautic Access Token',
    'title' => 'Mautic Access Token',
    'help_text' => '',
    // No form element
  ],
  // OAuth 2.0. Obtained during Mautic authentication.
  'mautic_tenant_id' => [
    'group_name' => 'Mautic Settings',
    'group' => 'mautic',
    'name' => 'mautic_tenant_id',
    'type' => 'String',
    'add' => '4.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Mautic Tenant ID (Organization)',
    'title' => 'Mautic Tenant ID',
    'help_text' => '',
    // No form element
  ],

];
