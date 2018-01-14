<?php

namespace Drupal\social_post_linkedin;

use Drupal\social_post\PostManager\PostManager;

/**
 * Manages the authorization process and post on user behalf.
 */
class LinkedInPostAuthManager extends PostManager {

  /**
   * The LinkedIn client object.
   *
   * @var \League\OAuth2\Client\Provider\LinkedIn
   */
  protected $client;

  /**
   * The LinkedIn user.
   *
   * @var \League\OAuth2\Client\Provider\LinkedInResourceOwner
   */
  protected $user;

  /**
   * Saves access token.
   */
  public function authenticate() {
    $this->accessToken = $this->client->getAccessToken('authorization_code',
      ['code' => $_GET['code']]);
  }

  /**
   * Returns the LinkedIn login URL where user will be redirected.
   *
   * @return string
   *   Absolute LinkedIn login URL where user will be redirected
   */
  public function getLoginUrl() {
    $scopes = [
      'r_basicprofile',
      'r_emailaddress',
      'w_share',
    ];

    $login_url = $this->client->getAuthorizationUrl([
      'scope' => $scopes,
    ]);
    // Generate and return the URL where we should redirect the user.
    return $login_url;
  }

  /**
   * Gets the data by using the access token returned.
   *
   * @return \League\OAuth2\Client\Provider\LinkedInResourceOwner
   *   User Info returned by the linkedIn.
   */
  public function getUserInfo() {
    if (!$this->user) {
      $this->user = $this->client->getResourceOwner($this->getAccessToken());
    }

    return $this->user;
  }

  /**
   * Returns the LinkedIn login URL where user will be redirected.
   *
   * @return string
   *   Absolute LinkedIn login URL where user will be redirected
   */
  public function getState() {
    $state = $this->client->getState();

    // Generate and return the URL where we should redirect the user.
    return $state;
  }

}
