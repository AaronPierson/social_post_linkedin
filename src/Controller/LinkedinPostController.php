<?php

namespace Drupal\social_post_linkedin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_post\SocialPostDataHandler;

use Drupal\social_post\SocialPostManager;
use Drupal\social_post_linkedin\LinkedinPostAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Returns responses for Simple Linkedin Connect module routes.
 */
class LinkedinPostController extends ControllerBase {

  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;

  /**
   * The Linkedin authentication manager.
   *
   * @var \Drupal\social_auth_linkedin\LinkedinAuthManager
   */
  private $linkedinManager;

  /**
   * The Social Auth Data Handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  private $dataHandler;

  /**
   * Used to access GET parameters.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $request;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The social post manager.
   *
   * @var \Drupal\social_post\SocialPostManager
   */
  protected $postManager;

  /**
   * LinkedinAuthController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_linkedin network plugin.
   * @param \Drupal\social_post\SocialPostManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_post_linkedin\LinkedinPostAuthManager $linkedin_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to access GET parameters.
   * @param \Drupal\social_post\SocialPostDataHandler $social_auth_data_handler
   *   SocialAuthDataHandler object.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Used for logging errors.
   */
  public function __construct(NetworkManager $network_manager, SocialPostManager $user_manager, LinkedinPostAuthManager $linkedin_manager, RequestStack $request, SocialPostDataHandler $social_auth_data_handler, LoggerChannelFactoryInterface $logger_factory) {

    $this->networkManager = $network_manager;
    $this->postManager = $user_manager;
    $this->linkedinManager = $linkedin_manager;
    $this->request = $request;
    $this->dataHandler = $social_auth_data_handler;
    $this->loggerFactory = $logger_factory;

    // Sets session prefix for data handler.
    $this->dataHandler->getSessionPrefix('social_post_linkedin');

    // Sets the plugin id.
    // Sets the session keys to nullify if user could not logged in.
    // $this->linkedinManager->setSessionKeysToNullify(['access_token']);.
    $this->setting = $this->config('social_post_linkedin.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.network.manager'),
      $container->get('social_post.post_manager'),
      $container->get('linkedin_post.social_post_auth_manager'),
      $container->get('request_stack'),
      $container->get('social_post.social_post_data_handler'),
      $container->get('logger.factory')
    );
  }

  /**
   * Redirects the user to Linkedin for authentication.
   */
  public function redirectToFb() {
    /* @var \League\OAuth2\Client\Provider\Linkedin false $linkedin */
    $linkedin = $this->networkManager->createInstance('social_post_linkedin')->getSdk();

    // If linkedin client could not be obtained.
    if (!$linkedin) {
      drupal_set_message($this->t('Social Auth Linkedin not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Linkedin service was returned, inject it to $linkedinManager.
    $this->linkedinManager->setClient($linkedin);

    // Generates the URL where the user will be redirected for Linkedin login.
    // If the user did not have email permission granted on previous attempt,
    // we use the re-request URL requesting only the email address.
    $linkedin_login_url = $this->linkedinManager->getFbLoginUrl();

    $state = $this->linkedinManager->getState();

    $this->dataHandler->set('oAuth2State', $state);

    return new TrustedRedirectResponse($linkedin_login_url);
  }

  /**
   * Response for path 'user/login/linkedin/callback'.
   *
   * Linkedin returns the user here after user has authenticated in Linkedin.
   */
  public function callback() {
    // Checks if user cancel login via Linkedin.
    $error = $this->request->getCurrentRequest()->get('error');
    if ($error == 'access_denied') {
      drupal_set_message($this->t('You could not be authenticated.'), 'error');
      return $this->redirect('user.login');
    }

    /* @var \League\OAuth2\Client\Provider\Linkedin false $linkedin */
    $linkedin = $this->networkManager->createInstance('social_post_linkedin')->getSdk();

    // If linkedin client could not be obtained.
    if (!$linkedin) {
      drupal_set_message($this->t('Social Auth Linkedin not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    $state = $this->dataHandler->get('oAuth2State');

    // Retrieves $_GET['state'].
    $retrievedState = $this->request->getCurrentRequest()->query->get('state');

    $this->linkedinManager->setClient($linkedin)->authenticate();

    if (!$linkedin_profile = $this->linkedinManager->getUserInfo()) {
      drupal_set_message($this->t('Linkedin login failed, could not load Linkedin profile. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    if (!$this->postManager->checkIfUserExists($this->linkedinManager->getUserInfo()->getId())) {
      $this->postManager->addRecord('social_post_linkedin', $this->linkedinManager->getUserInfo()->getId(), $this->linkedinManager->getAccessToken(), $this->linkedinManager->getUserInfo()->getFirstName(), '');
    }
    return $this->redirect('entity.user.edit_form', ['user' => $this->postManager->getCurrentUser()]);
  }

}
