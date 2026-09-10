<?php

namespace Drupal\generator_engine\Procedures;

/**
 * Generates users, tags, and content authored by and tagged with them.
 *
 * Steps:
 *   1. Create $usersCount users.
 *   2. Create $tagsCount "tags" taxonomy terms.
 *   3. Create $articlesCount "article" nodes, each authored by one of the
 *      generated users and tagged with one of the generated tags.
 *   4. Create $pagesCount "page" nodes, same authoring/tagging.
 *
 * Users/tags are consumed round-robin (index modulo count), so this still
 * works if the counts are overridden to unequal values.
 */
class UsersTagsContentProcedures extends EntitiesProcedures {

  /**
   * Number of users to generate.
   *
   * @var int
   */
  public $usersCount = 4;

  /**
   * Number of taxonomy terms to generate.
   *
   * @var int
   */
  public $tagsCount = 4;

  /**
   * Number of article nodes to generate.
   *
   * @var int
   */
  public $articlesCount = 4;

  /**
   * Number of page nodes to generate.
   *
   * @var int
   */
  public $pagesCount = 4;

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->usersCount = $this->options['users_count'] ?? 4;
    $this->tagsCount = $this->options['tags_count'] ?? 4;
    $this->articlesCount = $this->options['articles_count'] ?? 4;
    $this->pagesCount = $this->options['pages_count'] ?? 4;

    return $this->mainLogic();
  }

  /**
   * {@inheritdoc}
   */
  public function mainLogic($log_progress = TRUE) {
    $users = $this->generateUsers();
    $tags = $this->generateTags();
    $articles = $this->generateContent('article', $this->articlesCount, $users, $tags);
    $pages = $this->generateContent('page', $this->pagesCount, $users, $tags);

    $total = count($users) + count($tags) + count($articles) + count($pages);

    // Cast to string before concatenating: appending to a TranslatableMarkup
    // forces __toString() on it and loses the translation context.
    $message = (string) generator_engine_handle_generate_result_batch($this->batchId, $total);
    $message .= ' ' . $this->t('Users: @users. Tags: @tags. Articles: @articles. Pages: @pages.', [
      '@users' => implode(',', array_column($users, 'user')),
      '@tags' => implode(',', $tags),
      '@articles' => implode(',', $articles),
      '@pages' => implode(',', $pages),
    ]);

    return $message;
  }

  /**
   * Creates $this->usersCount distinct users.
   *
   * @return array
   *   List of ['user' => uid] items, one per generated user.
   */
  protected function generateUsers() {
    $users = [];
    for ($i = 0; $i < $this->usersCount; $i++) {
      $this->callConsole("generate user $i/{$this->usersCount} \r\n\r\n", TRUE);
      $unique = time() . '-' . rand(1000, 9999) . '-' . $i;
      $target_entities = [
        "user" => [
          "user" => [
            "check" => 1,
            "count" => 1,
            "fields" => [
              "name" => "gen-user-" . $unique,
              "user_picture" => 1,
            ],
            "properties" => [
              "name" => "gen-user-" . $unique,
            ],
          ],
        ],
      ];

      $result = $this->generateEngineWrapper($target_entities);
      $users[] = $result['items'][0]['user'];
      if (empty($this->batchId)) {
        $this->batchId = $result['batch_id'];
      }
    }
    return $users;
  }

  /**
   * Creates $this->tagsCount distinct "tags" taxonomy terms.
   *
   * @return array
   *   List of term ids.
   */
  protected function generateTags() {
    $tags = [];
    for ($i = 0; $i < $this->tagsCount; $i++) {
      $this->callConsole("generate tag $i/{$this->tagsCount} \r\n\r\n", TRUE);
      $target_entities = [
        "taxonomy_vocabulary" => [
          "tags" => [
            "check" => 1,
            "count" => 1,
            "fields" => [
              "name" => "Generated Tag " . ($i + 1) . ' ' . time() . rand(100, 999),
            ],
            "properties" => [],
          ],
        ],
      ];

      $result = $this->generateEngineWrapper($target_entities);
      $tags[] = $result['items'][0]['taxonomy_term']['tags'];
      if (empty($this->batchId)) {
        $this->batchId = $result['batch_id'];
      }
    }
    return $tags;
  }

  /**
   * Creates $count nodes of the given bundle, authored/tagged round-robin.
   *
   * @return array
   *   List of node ids.
   */
  protected function generateContent($bundle, $count, array $users, array $tags) {
    $generateHelpers = $this->generateHelpers;
    $items = [];
    for ($i = 0; $i < $count; $i++) {
      $this->callConsole("generate $bundle $i/$count \r\n\r\n", TRUE);
      $author = $users[$i % count($users)]['user'];
      $tag = $tags[$i % count($tags)];

      $target_entities = [
        "node_type" => [
          $bundle => [
            "check" => 1,
            "count" => 1,
            "fields" => [
              "title" => "Generated " . ucfirst($bundle) . " " . ($i + 1),
              "body" => $generateHelpers->generateTextLorem(1), // field_body for drupal11.
              "field_tags" => $tag,
              "field_image" => 1, // field_media_image for drupal11.
            ],
            "properties" => [
              "uid" => $author,
            ],
          ],
        ],
      ];

      $result = $this->generateEngineWrapper($target_entities);
      $items[] = $result['items'][0]['node'][$bundle];
      if (empty($this->batchId)) {
        $this->batchId = $result['batch_id'];
      }
    }
    return $items;
  }

}
