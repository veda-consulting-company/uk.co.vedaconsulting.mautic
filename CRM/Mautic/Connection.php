<?php
Use CRM_Mautic_Utils as U;
/**
 * A wrapper to create a Mautic Auth object using extension settings.
 *
 * @code
 *  $contactApi = CRM_Mautic_Connection::singleton()->newApi('contacts');
 * @endcode
 *
 * Also handles access token data storage.
 *
 * @see
 *
 *
 */
use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;

use CRM_Mautic_Setting as S;

class CRM_Mautic_Connection {



  protected static $singleton = NULL;

  /**
   *
   * @var Mautic\Auth\ApiAuth
   */
  protected $mauticAuth = NULL;

  /**
   *
   * @var Mautic\MauticApi
   */
  protected $api = NULL;

  /**
   * Base url of Mautic intallation.
   *
   * @var string
   */
  protected $baseUrl = '';

  /**
   * Base path for authorization callback.
   *
   * @var string
   */
  protected $callbackBaseUrl = "civicrm/admin/mautic/connection";

  /**
   * Connection settings.
   *
   * @var array
   */
  protected $settings = [];


  protected $errors = [];

  /**
   * Contact data for the Connected Mautic user.
   * @var array
   */
  protected $connectedUser = [];


  /**
   * Singleton method.
   *
   * @return CRM_Mautic_Connection
   */
  public static function singleton($params = []) {
    if (!static::$singleton) {
      static::$singleton = new CRM_Mautic_Connection($params);
    }
    return static::$singleton;
  }

  /**
  * @params $params
  *  Optional connection parameters for subclasses.
  *  Will default to the values from this extension's settings.
  *  Subclasses can bypass the singleton pattern and override the default settings.
  */
  protected function __construct($params = []) {
    $this->init($params);
  }

  public function getConnectedUser() {
    if (!$this->connectedUser) {
      $userApi = $this->newApi('users');
      $this->connectedUser = $userApi->getSelf();
    }
    return $this->connectedUser;
  }

  /**
   * Creates a Mautic API instance.
   *
   * @param string $context
   *
   * @return \Mautic\Api\Api
   */
  public function newApi($context) {
    if ($context && $this->api) {
      $auth = $this->getAuth();
      if ($auth) {
        return $this->api->newApi($context, $auth, $this->getBaseUrl());
      }
    }
  }

  /**
   * Gets a Mautic Api Auth object.
   *
   * @param boolean $authorize
   *  If true and OAuth authorization is required then the User
   *  will be redirected to the Mautic installation to authorize.
   *
   * @return \Mautic\Auth\ApiAuth
   */
  public function getAuth($authorize = FALSE) {
    if (!$this->getBaseUrl() || !$this->getauthMethod()) {
      return;
    }
    if (!$this->mauticAuth) {
      $params = ['baseUrl' => $this->getBaseUrl()];
      $authMethod = $checkTokenKey = '';
      switch ($this->getauthMethod()) {
        case 'basic':
          $authMethod = 'BasicAuth';
          $params += [
            'userName' => CRM_Utils_Array::value('mautic_basic_username', $this->settings),
            'password' => CRM_Utils_Array::value('mautic_basic_password', $this->settings),
          ];
          break;
        case 'oauth1':
          $authMethod = 'OAuth';
          $params += [
            'version' => 'OAuth1a',
            'clientKey' => CRM_Utils_Array::value('mautic_oauth1_consumer_key', $this->settings),
            'clientSecret' => CRM_Utils_Array::value('mautic_oauth1_consumer_secret', $this->settings),
            'callback' => $this->getCallbackUrl(),
          ];
          $checkTokenKey = 'accessTokenSecret';
          break;
        case 'oauth2':
          $authMethod = 'OAuth';
          $params += [
            'version' => 'OAuth2',
            'clientKey' => CRM_Utils_Array::value('mautic_oauth2_client_id', $this->settings),
            'clientSecret' => CRM_Utils_Array::value('mautic_oauth2_client_secret', $this->settings),
            'callback' => $this->getCallbackUrl(),
          ];
          $checkTokenKey = 'refreshToken';
          break;
      }
      if ($checkTokenKey) {
        $accessToken = $this->getAccessTokenData(TRUE);
        if (CRM_Utils_Array::value($checkTokenKey, $accessToken)) {
           $params += $accessToken;
        }
      }
      try {
        $initAuth = new ApiAuth();
        $this->mauticAuth = $initAuth->newAuth($params, $authMethod);
        $this->mauticAuth->enableDebugMode();
        if ($authMethod == 'OAuth' && !$this->mauticAuth->isAuthorized()) {
          // Validate access token, and authorize if necessary.
          if ($this->mauticAuth->validateAccessToken($authorize)) {

            if ($this->mauticAuth->accessTokenUpdated()) {

              // $accessTokenData will have the following keys:
              // For OAuth1.0a: access_token, access_token_secret, expires
              // For OAuth2: access_token, expires, token_type, refresh_token

              $accessTokenData = $this->mauticAuth->getAccessTokenData();
              $this->saveAccessTokenData($accessTokenData);
            }
          }
        }
      }
      catch (Exception $e) {
        Civi::log()->critical($e->getMessage());
        $this->logError($e->getMessage());
      }
    }
    return $this->mauticAuth;

  }

  /**
   * Initialize instance properties.
   *
   * @params $params
   *
   */
  protected function init($params = []) {
    if (!$params) {
      $settings = CRM_Mautic_Setting::getAll();
    }
    else {
      $settings = $params;
    }
    $missing = [];
    if (!CRM_Mautic_Setting::validate($settings, $missing)) {
      $this->logError('Missing settings for Mautic Connection', [
        'settingsValues' => $settings,
        'missingSettings' => $missing,
      ]);
      return;
    }
    $this->settings = $settings;
    $this->baseUrl = $settings['mautic_connection_url'];
    $this->api = new MauticApi();
  }

  public function getErrors() {
    return $this->errors;
  }

  public function logError($message, $context = []) {
    $this->errors += ['message' => $message, 'context' => $context];
    Civi::log()->error($message, $context);
  }

  /**
   * Get the URL to the mautic installation.
   *
   * @return string
   */
  public function getBaseUrl() {
    // Mautic Auth will append the endpoint path.
    return $this->baseUrl;
  }

  /**
   * Get the URL for the authorization callback.
   *
   * @return string
   */
  public function getCallbackUrl() {
    $url = CRM_Utils_System::url(
     $this->callbackBaseUrl,
      NULL,
      TRUE,
      NULL,
      FALSE,
      FALSE,
      TRUE
    );
    // Mautic stores callback uri with filtered special chars.
    // eg. '&' is replaced with '&#38;'
    // This results in urls not matching when they contain a querystring.
    return filter_var($url, FILTER_SANITIZE_SPECIAL_CHARS);
  }

  /**
   * Get access token data
   *
   * @param bool $keysToCamelCase
   *  Whether to format the keys in camel case.
   *  The default is in snake-case.
   *
   * @return array
   */
  public function getAccessTokenData($keysToCamelCase = FALSE) {
    $data = Civi::settings()->get('mautic_access_token');
    $data = is_array($data) ? $data : unserialize($data);
    if (!$data) {
      return [];
    }
    if ($keysToCamelCase) {
      // Mautic returns token data in snake case, but the client library takes camel-cased args.
      $newData = [];
      foreach ($data as $key => $value) {
        $parts = explode('_', $key);
        $newKey = array_shift($parts);
        foreach ($parts as $part) {
          $newKey .= ucfirst($part);
        }
        if ($newKey == 'expires') {
          $newKey = 'accessTokenExpires';
        }
        $newData[$newKey] = $value;
      }
      $data = $newData;
    }
    return $data;
  }

  protected function saveAccessTokenData($data) {
    Civi::settings()->set('mautic_access_token', $data);
  }

  public function clearAccessToken() {
    $this->saveAccessTokenData([]);
    // Auth stores token data in session.
    unset($_SESSION['oauth']);
  }

  /**
   * Get the authorization/authentication method.
   * @return string
   *  One of: basic|oauth1|oauth2
   */
  public function getAuthMethod() {
    return CRM_Utils_Array::value('mautic_connection_authentication_method', $this->settings);
  }
}
