<?php

namespace Drupal\social_post_linkedin\Settings;

/**
 * Defines an interface for Social Post Linkedin settings.
 */
interface LinkedinPostSettingsInterface {

  /**
   * Gets the application ID.
   *
   * @return mixed
   *   The application ID.
   */
  public function getClientId();

  /**
   * Gets the application secret.
   *
   * @return string
   *   The application secret.
   */
  public function getClientSecret();

}
