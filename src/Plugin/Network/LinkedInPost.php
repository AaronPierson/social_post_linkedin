<?php

namespace Drupal\social_post_linkedin\Plugin\Network;

use Drupal\social_api\SocialApiException;
use Drupal\social_post\Plugin\Network\NetworkBase;
use Drupal\social_post_linkedin\Settings\LinkedInPostSettings;
use League\OAuth2\Client\Provider\LinkedIn;

/**
 * Defines Social Post LinkedIn Network Plugin.
 *
 * @Network(
 *   id = "social_post_linkedin",
 *   social_network = "LinkedIn",
 *   type = "social_post",
 *   handlers = {
 *     "settings": {
 *        "class": "\Drupal\social_post_linkedin\Settings\LinkedInPostSettings",
 *        "config_id": "social_post_linkedin.settings"
 *      }
 *   }
 * )
 */
class LinkedInPost extends NetworkBase {

  /**
   * Sets the underlying SDK library.
   *
   * @return \League\OAuth2\Client\Provider\LinkedIn
   *   The initialized 3rd party library instance.
   *
   * @throws \Drupal\social_api\SocialApiException
   *   If the SDK library does not exist.
   */
  protected function initSdk() {

    $class_name = '\League\OAuth2\Client\Provider\LinkedIn';
    if (!class_exists($class_name)) {
      throw new SocialApiException(sprintf('The LinkedIn library for the PHP League OAuth2 Client not found. Class: %s.', $class_name));
    }

    /** @var \Drupal\social_post_linkedin\Settings\LinkedInPostSettings $settings */
    $settings = $this->settings;
    if ($this->validateConfig($settings)) {

      // All these settings are mandatory.
      $league_settings = [
        'clientId' => $settings->getClientId(),
        'clientSecret' => $settings->getClientSecret(),
        'redirectUri' => $GLOBALS['base_url'] . '/user/social-post/linkedin/auth/callback',
      ];

      return new LinkedIn($league_settings);
    }

    return FALSE;
  }

  /**
   * Checks that module is configured.
   *
   * @param \Drupal\social_post_linkedin\Settings\LinkedInPostSettings $settings
   *   The Social Post LinkedIn settings.
   *
   * @return bool
   *   True if module is configured.
   *   False otherwise.
   */
  protected function validateConfig(LinkedInPostSettings $settings) {
    $client_id = $settings->getClientId();
    $client_secret = $settings->getClientSecret();

    if (!$client_id || !$client_secret) {
      $this->loggerFactory
        ->get('social_post_linkedin')
        ->error('Define Client ID and Client Secret on module settings.');

      return FALSE;
    }

    return TRUE;
  }

}
