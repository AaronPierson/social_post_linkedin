<?php

namespace Drupal\social_post_linkedin;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Drupal\social_post\PostManager;

/**
 * Manages the authorization process and post on user behalf.
 */
class LinkedinPostAuthManager extends PostManager\PostManager {
  /**
   * The session manager.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * The Linkedin client object.
   *
   * @var \League\OAuth2\Client\Provider\Linkedin
   */
  protected $client;

  /**
   * The HTTP client object.
   *
   * @var \League\OAuth2\Client\Provider\Linkedin
   */
  protected $httpClient;


  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The Linkedin access token.
   *
   * @var \League\OAuth2\Client\Token\AccessToken
   */
  protected $token;

  /**
   * LinkedinPostManager constructor.
   *
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to get the parameter code returned by Linkedin.
   */
  public function __construct(Session $session, RequestStack $request) {
    $this->session = $session;
    $this->request = $request->getCurrentRequest();
  }

  /**
   * Saves access token.
   */
  public function authenticate() {
    $this->token = $this->client->getAccessToken('authorization_code',
      ['code' => $_GET['code']]);
  }

  /**
   * Returns the Linkedin login URL where user will be redirected.
   *
   * @return string
   *   Absolute Linkedin login URL where user will be redirected
   */
  public function getFbLoginUrl() {
    $scopes = ['r_basicprofile','r_emailaddress','rw_company_admin','w_share'];

    $login_url = $this->client->getAuthorizationUrl([
      'scope' => $scopes,
    ]);
    // Generate and return the URL where we should redirect the user.
    return $login_url;
  }

  /**
   * Gets the data by using the access token returned.
   *
   * @return League\OAuth2\Client\Provider\LinkedinUser
   *   User Info returned by the linkedin.
   */
  public function getUserInfo() {
    $this->user = $this->client->getResourceOwner($this->token);
    return $this->user;
  }

  /**
   * Returns token generated after authorization.
   *
   * @return string
   *   Used for making API calls.
   */
  public function getAccessToken() {
    return $this->token;
  }

  /**
   * Makes an API call to linkedin server.
   */
  public function requestApiCall($message, $token, $userId) {

    $url = 'https://api.linkedin.com/v1/people/~/shares?oauth2_access_token='.$token;
    $json_request = '{
      "comment": "Check out developer.linkedin.com! http://linkd.in/1FC2PyG",
      "visibility":  {
        "code": "'.$message.'"
      }
    }';

    $headers = array(
      "Content-Type: application/json",
      "x-li-format: json",
    );

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_request);

    // execute!
    $response = curl_exec($ch);

    var_dump($response);

    $this->getAccessTokedn();
    // Close the connection, release resources used.
    curl_close($ch);
  }

  /**
   * Returns the Linkedin login URL where user will be redirected.
   *
   * @return string
   *   Absolute Linkedin login URL where user will be redirected
   */
  public function getState() {
    $state = $this->client->getState();

    // Generate and return the URL where we should redirect the user.
    return $state;
  }

}
