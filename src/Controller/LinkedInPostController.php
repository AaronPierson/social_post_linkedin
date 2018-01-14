<?php

namespace Drupal\social_post_linkedin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_post\SocialPostDataHandler;

use Drupal\social_post\SocialPostManager;
use Drupal\social_post_linkedin\LinkedInPostAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Returns responses for Simple LinkedIn Connect module routes.
 */
class LinkedInPostController extends ControllerBase {

  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;

  /**
   * The LinkedIn authentication manager.
   *
   * @var \Drupal\social_auth_linkedin\LinkedInAuthManager
   */
  private $linkedInManager;

  /**
   * The Social Auth Data Handler.
   *
   * @var \Drupal\social_post\SocialPostDataHandler
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
   * LinkedInAuthController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_linkedin network plugin.
   * @param \Drupal\social_post\SocialPostManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_post_linkedin\LinkedInPostAuthManager $linkedin_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to access GET parameters.
   * @param \Drupal\social_post\SocialPostDataHandler $data_handler
   *   SocialAuthDataHandler object.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Used for logging errors.
   */
  public function __construct(NetworkManager $network_manager,
                              SocialPostManager $user_manager,
                              LinkedInPostAuthManager $linkedin_manager,
                              RequestStack $request,
                              SocialPostDataHandler $data_handler,
                              LoggerChannelFactoryInterface $logger_factory) {

    $this->networkManager = $network_manager;
    $this->postManager = $user_manager;
    $this->linkedInManager = $linkedin_manager;
    $this->request = $request;
    $this->dataHandler = $data_handler;
    $this->loggerFactory = $logger_factory;

    $this->postManager->setPluginId('social_post_linkedin');

    // Sets session prefix for data handler.
    $this->dataHandler->setSessionPrefix('social_post_linkedin');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.network.manager'),
      $container->get('social_post.post_manager'),
      $container->get('linkedin_post.auth_manager'),
      $container->get('request_stack'),
      $container->get('social_post.data_handler'),
      $container->get('logger.factory')
    );
  }

  /**
   * Redirects the user to LinkedIn for authentication.
   */
  public function redirectToProvider() {
    /* @var \League\OAuth2\Client\Provider\LinkedIn|false $linkedin */
    $linkedin = $this->networkManager->createInstance('social_post_linkedin')->getSdk();

    // If LinkedIn client could not be obtained.
    if (!$linkedin) {
      drupal_set_message($this->t('Social Post LinkedIn not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // LinkedIn service was returned, inject it to $linkedInManager.
    $this->linkedInManager->setClient($linkedin);

    // Generates the URL where the user will be redirected for LinkedIn login.
    $linkedin_login_url = $this->linkedInManager->getLoginUrl();

    $state = $this->linkedInManager->getState();

    $this->dataHandler->set('oauth2state', $state);

    return new TrustedRedirectResponse($linkedin_login_url);
  }

  /**
   * Response for path 'user/login/linkedin/callback'.
   *
   * LinkedIn returns the user here after user has authenticated in LinkedIn.
   */
  public function callback() {
    // Checks if user cancel login via LinkedIn.
    $error = $this->request->getCurrentRequest()->get('error');
    if ($error == 'user_cancelled_authorize') {
      drupal_set_message($this->t('You could not be authenticated.'), 'error');
      return $this->redirect('entity.user.edit_form', ['user' => $this->postManager->getCurrentUser()]);
    }

    /* @var \League\OAuth2\Client\Provider\LinkedIn|false $linkedin */
    $linkedin = $this->networkManager->createInstance('social_post_linkedin')->getSdk();

    // If LinkedIn client could not be obtained.
    if (!$linkedin) {
      drupal_set_message($this->t('Social Auth LinkedIn not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    $state = $this->dataHandler->get('oauth2state');
    // Retrieves $_GET['state'].
    $retrievedState = $this->request->getCurrentRequest()->query->get('state');
    if (empty($retrievedState) || ($retrievedState !== $state)) {
      $this->postManager->nullifySessionKeys();
      drupal_set_message($this->t('LinkedIn login failed. Unvalid OAuth2 state.'), 'error');
      return $this->redirect('user.login');
    }

    $this->linkedInManager->setClient($linkedin)->authenticate();

    if (!$linkedin_profile = $this->linkedInManager->getUserInfo()) {
      drupal_set_message($this->t('LinkedIn login failed, could not load LinkedIn profile. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    if (!$this->postManager->checkIfUserExists($linkedin_profile->getId())) {
      $name = $linkedin_profile->getFirstName() . ' ' . $linkedin_profile->getLastName();
      $this->postManager->addRecord($name, $linkedin_profile->getId(), $this->linkedInManager->getAccessToken());
      drupal_set_message($this->t('Account added successfully.'), 'status');
    }
    else {
      drupal_set_message($this->t('You have already authorized to post on behalf of this user.'), 'warning');
    }

    return $this->redirect('entity.user.edit_form', ['user' => $this->postManager->getCurrentUser()]);
  }

}
