<?php

namespace Drupal\social_post_linkedin\Plugin\RulesAction;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rules\Core\RulesActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_post\SocialPostManager;
use Drupal\social_post_linkedin\LinkedinPostAuthManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Provides a 'Post' action.
 *
 * @RulesAction(
 *   id = "social_post_linkedin",
 *   label = @Translation("Linkedin Post"),
 *   category = @Translation("Social Post"),
 *   context = {
 *     "status" = @ContextDefinition("string",
 *       label = @Translation("Post content"),
 *       description = @Translation("Specifies the status to post.")
 *     )
 *   }
 * )
 */
class Post extends RulesActionBase implements ContainerFactoryPluginInterface {
  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;

  /**
   * The Linkedin authentication manager.
   *
   * @var \Drupal\social_post_linkedin\LinkedinPostAuthManager
   */
  private $linkedinManager;

  /**
   * The social post manager.
   *
   * @var \Drupal\social_post\SocialPostManager
   */
  protected $postManager;

  /**
   * The linkedin post network plugin.
   *
   * @var \Drupal\social_post_linkedin\Plugin\Network\LinkedinPostInterface
   */
  protected $linkedinPost;

  /**
   * The social post linkedin entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $linkedinEntity;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;
  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('plugin.network.manager'),
      $container->get('social_post.post_manager'),
      $container->get('linkedin_post.social_post_auth_manager'),
      $container->get('logger.factory')

    );
  }

  /**
   * Linkedin Post constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Network plugin manager.
   * @param \Drupal\social_post\SocialPostManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_post_linkedin\LinkedinPostAuthManager $linkedin_manager
   *   Used to manage authentication methods.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Used for logging errors.
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              EntityTypeManagerInterface $entity_manager,
                              AccountInterface $current_user,
                              NetworkManager $network_manager,
                              SocialPostManager $user_manager,
                              LinkedinPostAuthManager $linkedin_manager,
                              LoggerChannelFactoryInterface $logger_factory) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->linkedinEntity = $entity_manager->getStorage('social_post');
    $this->currentUser = $current_user;
    $this->networkManager = $network_manager;
    $this->postManager = $user_manager;
    $this->linkedinManager = $linkedin_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Executes the action with the given context.
   *
   * @param string $status
   *   The Post text.
   */
  protected function doExecute($status) {
    $linkedin = $this->networkManager->createInstance('social_post_linkedin')->getSdk();

    // If linkedin client could not be obtained.
    if (!$linkedin) {
      drupal_set_message($this->t('Social Auth Linkedin not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Linkedin service was returned, inject it to $linkedinManager.
    $this->linkedinManager->setClient($linkedin);

    $accounts = $this->postManager->getList('social_post_linkedin', \Drupal::currentUser()->id());

    /* @var \Drupal\social_post_linkedin\Entity\LinkedinUserInterface $account */
    foreach ($accounts as $account) {
      $access_token = $this->postManager->getToken('social_post_linkedin', $account->getSocialNetworkID())->access_token;
      $provider_user_id = $account->getSocialNetworkID();
      $this->linkedinManager->requestApiCall($status, $access_token, $provider_user_id);
    }
  }

}
