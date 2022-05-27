<?php

namespace Drupal\social_post_linkedin;

use Drupal\social_api\SocialApiException;

/**
 * Contains all the information about the post.
 */
class Post {

  /**
   * The text for the post.
   *
   * @var string
   */
  protected $text;

  /**
   * The author's id.
   *
   * @var string
   */
  protected $author;
  /**
   * The profile type: person or organization
   *
   * @var string
   */
  protected $profileType = 'organization';
  /**
   * The post's visitibility. PUBLIC or CONNECTIONS.
   *
   * @var string
   */
  protected $visibility = 'PUBLIC';

  /**
   * The state of the share. The life cycle state will always be PUBLISHED.
   *
   * @var string
   */
  protected $lifeCycle = 'PUBLISHED';

  /**
   * Post constructor.
   *
   * @param string $text
   *   The text for the post.
   * @param string $author
   *   The author's ID.
   * @param string $visibility
   *   The post's visibility.
   */
  public function __construct($text, $author = '', $visibility = 'PUBLIC') {
    $this->text = $text;
    $this->author = $author;
    $this->visibility = $visibility;
  }

  /**
   * Returns the post's author.
   *
   * @return string
   *   The author's id.
   */
  public function getAuthor() {
    return $this->author;
  }

  /**
   * Sets the post's author.
   *
   * @param string $author
   *   The author's id.
   */
  public function setAuthor($author) {
    $this->author = $author;
  }

  public function setProfileType($profileType) {
    $this->profileType = $profileType;
  }

  /**
   * Returns the body of the share request in json format.
   * sets the profile type: person or organization.
   * @return string
   *   The body of the share request.
   */
  public function getPostBody() {

    if (!$this->author) {
      throw new SocialApiException('Author was not specified');
    }

    $status = [
      'author' => 'urn:li:'. $this->profileType .':' . $this->author, 'lifecycleState' => $this->lifeCycle,
      'specificContent' => [
        'com.linkedin.ugc.ShareContent' => [
          // 'media' => [
          //   // 'media' => 'urn:li:digitalmediaRecipe:feedshare-image:',
          //   // 'status' => 'READY',
          // ],
          'shareCommentary' => [
            'text' => $this->text,
          ],
          // TODO: Support media.
          'shareMediaCategory' => 'NONE',
          // 'shareMediaCategory' => 'RICH',
        ],
      ],
      'visibility' => [
        'com.linkedin.ugc.MemberNetworkVisibility' => $this->visibility,
      ],
    ];

    return json_encode($status);
  }

}
