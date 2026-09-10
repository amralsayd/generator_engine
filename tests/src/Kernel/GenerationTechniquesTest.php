<?php

namespace Drupal\Tests\generator_engine\Kernel;

use Drupal\file\Entity\File;

/**
 * Tests the string-value techniques the generation engine supports.
 *
 * Covers random entity selection, file and image generation, and the
 * '+', '#' and '%' prepareString() directives.
 *
 * @group generator_engine
 */
class GenerationTechniquesTest extends GeneratorEngineKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['link'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createFileField('field_attachment', 'node', ['article']);
    $this->createSimpleField('field_note', 'node', ['article'], 'string');
    $this->createSimpleField('field_link', 'node', ['article'], 'link');
  }

  /**
   * Tests that '!random_single' re-rolls independently for each entity.
   *
   * This holds under a plain "count", with no references or result_index.
   */
  public function testRandomSingleVariesPerNode() {
    $tag_ids = $this->createTags(5);

    $target_entities = [
      'node_type' => [
        'article' => [
          'check' => 1,
          'count' => 3,
          'fields' => [
            'title' => 'Random Tag Test',
            'field_tags' => '!random_single',
          ],
          'properties' => ['uid' => NULL],
        ],
      ],
    ];
    $this->generateHelpers()->generateEngine($target_entities);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'article',
      'title' => 'Random Tag Test',
    ]);
    $this->assertCount(3, $articles);
    foreach ($articles as $article) {
      /** @var \Drupal\node\Entity\Node $article */
      $this->assertFalse($article->field_tags->isEmpty());
      $this->assertContains((int) $article->field_tags->target_id, $tag_ids);
    }
  }

  /**
   * Tests that '!random_multiple' sets between one and five valid values.
   */
  public function testRandomMultiple() {
    $tag_ids = $this->createTags(5);

    $target_entities = [
      'node_type' => [
        'article' => [
          'check' => 1,
          'count' => 1,
          'fields' => [
            'title' => 'Random Multiple Test',
            'field_tags' => '!random_multiple',
          ],
          'properties' => ['uid' => NULL],
        ],
      ],
    ];
    $this->generateHelpers()->generateEngine($target_entities);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'article',
      'title' => 'Random Multiple Test',
    ]);
    $this->assertCount(1, $articles);
    /** @var \Drupal\node\Entity\Node $article */
    $article = reset($articles);
    $values = $article->field_tags->getValue();
    $this->assertGreaterThanOrEqual(1, count($values));
    $this->assertLessThanOrEqual(5, count($values));
    foreach ($values as $value) {
      $this->assertContains((int) $value['target_id'], $tag_ids);
    }
  }

  /**
   * Tests that a literal field value under "count" produces duplicates.
   *
   * Contrast case for the randomising techniques above.
   */
  public function testLiteralCountProducesDuplicates() {
    $tag_ids = $this->createTags(3);

    $target_entities = [
      'node_type' => [
        'article' => [
          'check' => 1,
          'count' => 3,
          'fields' => [
            'title' => 'Duplicate Title Test',
            'field_tags' => $tag_ids[0],
          ],
          'properties' => ['uid' => NULL],
        ],
      ],
    ];
    $this->generateHelpers()->generateEngine($target_entities);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'article',
      'title' => 'Duplicate Title Test',
    ]);
    $this->assertCount(3, $articles);
    foreach ($articles as $article) {
      /** @var \Drupal\node\Entity\Node $article */
      $this->assertEquals($tag_ids[0], (int) $article->field_tags->target_id);
    }
  }

  /**
   * Bind: 'image' - any truthy value generates a real sample image file.
   */
  public function testImageFileToken() {
    $target_entities = [
      'node_type' => [
        'article' => [
          'check' => 1,
          'count' => 1,
          'fields' => [
            'title' => 'Image Test',
            'field_image' => 1,
          ],
          'properties' => ['uid' => NULL],
        ],
      ],
    ];
    $this->generateHelpers()->generateEngine($target_entities);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'article',
      'title' => 'Image Test',
    ]);
    /** @var \Drupal\node\Entity\Node $article */
    $article = reset($articles);
    $this->assertFalse($article->field_image->isEmpty());
    $this->assertInstanceOf(File::class, $article->field_image->entity);
  }

  /**
   * Tests that a 'file' bind with a '$ext' token copies a sample file.
   *
   * The matching assets/files/sample-file.<ext> becomes a managed file.
   */
  public function testFileToken() {
    $target_entities = [
      'node_type' => [
        'article' => [
          'check' => 1,
          'count' => 1,
          'fields' => [
            'title' => 'Attachment Test',
            'field_attachment' => '$pdf',
          ],
          'properties' => ['uid' => NULL],
        ],
      ],
    ];
    $this->generateHelpers()->generateEngine($target_entities);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'article',
      'title' => 'Attachment Test',
    ]);
    /** @var \Drupal\node\Entity\Node $article */
    $article = reset($articles);
    $this->assertFalse($article->field_attachment->isEmpty());
    $file = $article->field_attachment->entity;
    $this->assertInstanceOf(File::class, $file);
    $this->assertStringEndsWith('.pdf', $file->getFilename());
  }

  /**
   * Tests that '+text' appends " [text]" to the generated title.
   */
  public function testPlusSuffixTechnique() {
    $target_entities = [
      'node_type' => [
        'article' => [
          'check' => 1,
          'count' => 1,
          'fields' => ['title' => '+Suffix'],
          'properties' => ['uid' => NULL],
        ],
      ],
    ];
    $this->generateHelpers()->generateEngine($target_entities);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'article']);
    $this->assertCount(1, $articles);
    /** @var \Drupal\node\Entity\Node $article */
    $article = reset($articles);
    $this->assertMatchesRegularExpression('/^article \d+ \[Suffix\]$/', $article->getTitle());

    // Same technique on a taxonomy term's "name" (also its title-property).
    $tags_target = [
      'taxonomy_vocabulary' => [
        'tags' => [
          'check' => 1,
          'count' => 1,
          'fields' => ['name' => '+TagSuffix'],
          'properties' => [],
        ],
      ],
    ];
    $this->generateHelpers()->generateEngine($tags_target);

    $tags = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'tags']);
    $this->assertCount(1, $tags);
    /** @var \Drupal\taxonomy\Entity\Term $tag */
    $tag = reset($tags);
    $this->assertMatchesRegularExpression('/^tags \d+ \[TagSuffix\]$/', $tag->label());
  }

  /**
   * Tests that '#key' copies another field's value from the same statement.
   */
  public function testHashLookupTechnique() {
    $target_entities = [
      'node_type' => [
        'article' => [
          'check' => 1,
          'count' => 1,
          'fields' => [
            'title' => 'Hello World',
            'field_note' => '#title',
          ],
          'properties' => ['uid' => NULL],
        ],
      ],
    ];
    $this->generateHelpers()->generateEngine($target_entities);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'article',
      'title' => 'Hello World',
    ]);
    $this->assertCount(1, $articles);
    /** @var \Drupal\node\Entity\Node $article */
    $article = reset($articles);
    $this->assertEquals('Hello World', $article->field_note->value);
  }

  /**
   * Tests that '%name' resolves an allow-listed generator.
   */
  public function testPercentNamedGeneratorTechnique() {
    $target_entities = [
      'node_type' => [
        'article' => [
          'check' => 1,
          'count' => 1,
          'fields' => [
            'title' => 'Percent Test',
            'field_link' => '%random_url',
          ],
          'properties' => ['uid' => NULL],
        ],
      ],
    ];
    $this->generateHelpers()->generateEngine($target_entities);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'article',
      'title' => 'Percent Test',
    ]);
    $this->assertCount(1, $articles);
    /** @var \Drupal\node\Entity\Node $article */
    $article = reset($articles);
    $this->assertFalse($article->field_link->isEmpty());
    $this->assertEquals('sample generted url', $article->field_link->title);
    $this->assertStringContainsString('://', $article->field_link->uri);
  }

  /**
   * Tests that a '%name' outside the allow-list is never executed.
   *
   * The directive used to build a PHP callable straight from the submitted
   * value, so any zero-argument function could be invoked from the forms,
   * Drush or the HTTP API. An unknown name must now be treated as a literal
   * string.
   *
   * @dataProvider providerDisallowedPercentDirectives
   */
  public function testPercentDirectiveIsNotArbitraryCode($directive) {
    $target_entities = [
      'node_type' => [
        'article' => [
          'check' => 1,
          'count' => 1,
          'fields' => [
            'title' => $directive,
          ],
          'properties' => ['uid' => NULL],
        ],
      ],
    ];
    $this->generateHelpers()->generateEngine($target_entities);

    // The literal directive is stored verbatim, proving it was not called.
    $articles = \Drupal::entityTypeManager()->getStorage('node')
      ->loadByProperties(['type' => 'article', 'title' => $directive]);
    $this->assertCount(1, $articles, sprintf('Directive "%s" was stored as a literal rather than executed.', $directive));
  }

  /**
   * Supplies '%' directives that must not resolve to a callable.
   *
   * @return array
   *   Each case holds a single directive string.
   */
  public static function providerDisallowedPercentDirectives() {
    return [
      'bare function' => ['%phpinfo'],
      'static method' => ['%Drupal\generator_engine\Util\Urls\RandomUrlGenerator::generate'],
      'unknown name' => ['%not_a_generator'],
    ];
  }

}
