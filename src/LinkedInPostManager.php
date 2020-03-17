<?php

namespace Drupal\social_post_linkedin;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\social_api\SocialApiException;
use Drupal\social_post\PostManager\OAuth2Manager;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Contains all the logic for LinkedIn OAuth2 authentication and posting.
 */
class LinkedInPostManager extends OAuth2Manager {

  /**
   * The post's request.
   *
   * @var string
   */
  protected $status;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Used for accessing configuration object factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Used to get the authorization code from the callback request.
   */
  public function __construct(ConfigFactory $config_factory,
                              LoggerChannelFactoryInterface $logger_factory,
                              RequestStack $request_stack) {

    parent::__construct($config_factory->get('social_auth_dropbox.settings'),
                        $logger_factory,
                        $request_stack->getCurrentRequest());
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate() {
    $this->setAccessToken($this->client->getAccessToken('authorization_code',
      ['code' => $this->request->get('code')]));
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationUrl() {
    $scopes = [
      'r_liteprofile',
      'r_emailaddress',
      'w_member_social',
    ];

    return $this->client->getAuthorizationUrl([
      'scope' => $scopes,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserInfo() {
    if (!$this->user) {
      $this->user = $this->client->getResourceOwner($this->getAccessToken());
    }

    return $this->user;
  }

  /**
   * {@inheritdoc}
   */
  public function getState() {
    return $this->client->getState();
  }

  /**
   * Sets up the posting request.
   *
   * @param string $access_token
   *   The access token to use in the request.
   * @param \Drupal\social_post_linkedin\Post $status
   *   The post's information.
   */
  public function doPost($access_token, Post $status) {
    $this->accessToken = $access_token;
    $this->status = $status->getPostBody();

    return $this->post();
  }

  /**
   * Executes posting request.
   */
  private function post() {
    if (!$this->client) {
      throw new SocialApiException("An instance of the provider's client was not set");
    }

    $response = $this->makePostRequest();

    if ($response->getStatusCode() !== 201) {
      $this->loggerFactory->get('social_post_linkedin')
        ->error('Error posting on LinkedIn. Error: @error', [
          '@error' => $response->getBody()->__toString(),
        ]);

      return FALSE;
    }

    return TRUE;
  }

  /**
   * Makes the post request to LinkedIn's API.
   */
  private function makePostRequest() {
    /** @var \Psr\Http\Message\RequestInterface $request */
    $request = $this->client->getAuthenticatedRequest('POST',
      'https://api.linkedin.com/v2/ugcPosts',
      $this->accessToken
    );

    $body = \GuzzleHttp\Psr7\stream_for($this->status);

    $request = $request->withAddedHeader('Content-Type', 'application/json')
      ->withAddedHeader('X-Restli-Protocol-Version', '2.0.0')
      ->withBody($body);

    return $this->client->getResponse($request);
  }

}
