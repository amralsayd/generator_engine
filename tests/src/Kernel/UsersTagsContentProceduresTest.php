<?php

namespace Drupal\Tests\generator_engine\Kernel;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * End-to-end test of the UsersTagsContentProcedures procedure.
 *
 * Runs it exactly as `drush generator_engine:seed_users_tags_content` does.
 *
 * The procedure also sets several custom user fields that only exist on the
 * real site (field_id, field_english_name, field_u_job_title, etc.) - under
 * strict Kernel-test error handling a missing custom_fields config entry is
 * a hard error (not a silently-skipped notice, as it would be in production
 * PHP 7.4), so this test recreates that exact field schema to match reality.
 *
 * @group generator_engine
 */
class UsersTagsContentProceduresTest extends GeneratorEngineKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  } 

  /**
   * Tests that the procedure generates users, tags, articles and pages.
   */
  public function testGeneratesUsersTagsArticlesPages() {
    $options = [
      'users_count' => 2,
      'tags_count' => 2,
      'articles_count' => 2,
      'pages_count' => 2,
    ];
    $this->generateHelpers()->runUsersTagsContentProcedure([], $options);

    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    // Filter to just the accounts this procedure created (name prefix
    // "gen-user-"); this also naturally excludes the anonymous uid 0 user,
    // whose account name is an empty string.
    $generated_users = [];
    $uids = [];
    foreach ($user_storage->loadByProperties([]) as $user) {
      /** @var \Drupal\user\Entity\User $user */
      if (strpos($user->getAccountName(), 'gen-user-') === 0) {
        $generated_users[] = $user;
        $uids[] = (int) $user->id();
      }
    }
    $this->assertCount(2, $generated_users, 'Two users were generated.');

    $tags = $term_storage->loadByProperties(['vid' => 'tags']);
    $this->assertCount(2, $tags, 'Two tags were generated.');
    $tids = [];
    foreach ($tags as $term) {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      $tids[] = (int) $term->id();
    }

    $articles = $node_storage->loadByProperties(['type' => 'article']);
    $this->assertCount(2, $articles, 'Two articles were generated.');
    $pages = $node_storage->loadByProperties(['type' => 'page']);
    $this->assertCount(2, $pages, 'Two pages were generated.');

    foreach (array_merge($articles, $pages) as $node) {
      /** @var \Drupal\node\Entity\Node $node */
      $this->assertContains((int) $node->getOwnerId(), $uids, 'Node authored by a generated user.');
      $this->assertFalse($node->field_tags->isEmpty(), 'Node has a tag.');
      $this->assertContains((int) $node->field_tags->target_id, $tids, 'Node references a generated tag.');
      $this->assertFalse($node->field_image->isEmpty(), 'Node has a generated image.');
    }

    foreach ($generated_users as $user) {
      /** @var \Drupal\user\Entity\User $user */
      $this->assertFalse($user->user_picture->isEmpty(), 'User has a generated picture.');
    }
  }

}
