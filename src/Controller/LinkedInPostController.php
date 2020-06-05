<?php

namespace Drupal\social_post_linkedin\Controller;

use Drupal\social_post\Controller\OAuth2ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Social Post LinkedIn routes.
 */
class LinkedInPostController extends OAuth2ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      'Social Post Linkedin',
      'social_post_linkedin',
      $container->get('plugin.network.manager'),
      $container->get('social_post.user_authenticator'),
      $container->get('linkedin_post.manager'),
      $container->get('request_stack'),
      $container->get('social_post.data_handler'),
      $container->get('renderer'),
      $container->get('entity_type.manager')->getListBuilder('social_post')
    );
  }

  /**
   * Response for path 'user/social-post/linkedin/auth/callback'.
   *
   * LinkedIn returns the user here after user has authenticated.
   */
  public function callback() {
    // Checks if there was an authentication error.
    $redirect = $this->checkAuthError();
    if ($redirect) {
      return $redirect;
    }

    /** @var \League\OAuth2\Client\Provider\LinkedInResourceOwner|null $profile */
    $profile = $this->processCallback();

    // If authentication was successful.
    if ($profile !== NULL) {

      if (!$this->userAuthenticator->getDrupalUserId($profile->getId())) {
        $name = $profile->getFirstName() . ' ' . $profile->getLastName();
        $id = $profile->getId();
        $url = $profile->getUrl();

        $this->userAuthenticator->addUserRecord($name, $id, $url, $this->providerManager->getAccessToken());

        $this->messenger()->addStatus($this->t('Account added successfully.'));
      }
      else {
        $this->messenger()->addWarning($this->t('You have already authorized to post on behalf of this user.'));
      }
    }

    return $this->redirect('entity.user.edit_form', [
      'user' => $this->userAuthenticator->currentUser()->id(),
    ]);
  }

}
