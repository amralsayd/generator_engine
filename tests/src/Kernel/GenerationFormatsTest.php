<?php

namespace Drupal\Tests\generator_engine\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests that JSON, YAML and PHP array input produce identical results.
 *
 * All three go through HelpersService::generateFromArray(), the dispatch
 * used by the "Using JSON" and "Using YAML" admin forms and by the
 * generator_engine:generate Drush command.
 *
 * @group generator_engine
 */
class GenerationFormatsTest extends GeneratorEngineKernelTestBase {

  /**
   * The same target-entities structure as a JSON string.
   */
  protected function jsonFixture() {
    return '{
      "references": {
        "tags_info": [
          {"name": "Technology"},
          {"name": "Science"},
          {"name": "Education"},
          {"name": "Innovation"}
        ]
      },
      "0": {
        "taxonomy_vocabulary": {
          "tags": {
            "check": 1,
            "count": 1,
            "references": {"count": 4, "result_index": "tags"},
            "fields": {"name": "tags_info|name"},
            "properties": {}
          }
        }
      },
      "1": {
        "node_type": {
          "article": {
            "check": 1,
            "count": 1,
            "references": {"count": 1},
            "fields": {
              "title": "Test Article",
              "body": "Test Body",
              "field_tags": "tags|tags"
            },
            "properties": {"uid": null}
          }
        }
      }
    }';
  }

  /**
   * The same target-entities structure as a YAML string.
   */
  protected function yamlFixture() {
    return <<<YAML
references:
  tags_info:
    - name: Technology
    - name: Science
    - name: Education
    - name: Innovation
'0':
  taxonomy_vocabulary:
    tags:
      check: 1
      count: 1
      references:
        count: 4
        result_index: tags
      fields:
        name: tags_info|name
      properties: {}
'1':
  node_type:
    article:
      check: 1
      count: 1
      references:
        count: 1
      fields:
        title: Test Article
        body: Test Body
        field_tags: tags|tags
      properties:
        uid: null
YAML;
  }

  /**
   * The same target-entities structure as a native PHP array.
   */
  protected function phpArrayFixture() {
    $target_entities['references'] = [
      'tags_info' => [
        ['name' => 'Technology'],
        ['name' => 'Science'],
        ['name' => 'Education'],
        ['name' => 'Innovation'],
      ],
    ];
    $target_entities[] = [
      'taxonomy_vocabulary' => [
        'tags' => [
          'check' => 1,
          'count' => 1,
          'references' => ['count' => 4, 'result_index' => 'tags'],
          'fields' => ['name' => 'tags_info|name'],
          'properties' => [],
        ],
      ],
    ];
    $target_entities[] = [
      'node_type' => [
        'article' => [
          'check' => 1,
          'count' => 1,
          'references' => ['count' => 1],
          'fields' => [
            'title' => 'Test Article',
            'body' => 'Test Body',
            'field_tags' => 'tags|tags',
          ],
          'properties' => ['uid' => NULL],
        ],
      ],
    ];
    return $target_entities;
  }

  /**
   * Asserts the fixture above generated the expected tags + article.
   */
  protected function assertFixtureResult() {
    $tags = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'tags']);
    $this->assertCount(4, $tags, 'Four tags were generated.');
    $tag_names = array_map(function (Term $term) {
      return $term->label();
    }, $tags);
    sort($tag_names);
    $this->assertEquals(['Education', 'Innovation', 'Science', 'Technology'], $tag_names);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'article']);
    $this->assertCount(1, $articles, 'One article was generated.');
    /** @var \Drupal\node\Entity\Node $article */
    $article = reset($articles);
    $this->assertEquals('Test Article', $article->getTitle());
    $this->assertEquals('Test Body', $article->body->value);

    $tag_ids = array_map(function (Term $term) {
      return (int) $term->id();
    }, $tags);
    $referenced_tid = (int) $article->field_tags->target_id;
    $this->assertContains($referenced_tid, $tag_ids, 'The article references one of the generated tags.');
  }

  /**
   * Tests generating from a JSON document.
   */
  public function testJsonInput() {
    $target_entities = json_decode($this->jsonFixture(), TRUE);
    $this->generateHelpers()->generateFromArray($target_entities);
    $this->assertFixtureResult();
  }

  /**
   * Tests generating from a YAML document.
   */
  public function testYamlInput() {
    $target_entities = Yaml::decode($this->yamlFixture());
    $this->generateHelpers()->generateFromArray($target_entities);
    $this->assertFixtureResult();
  }

  /**
   * Tests generating from a PHP array.
   */
  public function testPhpArrayInput() {
    $target_entities = $this->phpArrayFixture();
    $this->generateHelpers()->generateFromArray($target_entities);
    $this->assertFixtureResult();
  }

  /**
   * Directly asserts generateFromArray()'s is_int-key branch selection.
   */
  public function testDependantKeyDetection() {
    // String keys -> generateEngine().
    $simple = [
      'taxonomy_vocabulary' => [
        'tags' => [
          'check' => 1,
          'count' => 1,
          'fields' => ['name' => 'Solo Tag'],
          'properties' => [],
        ],
      ],
    ];
    $this->generateHelpers()->generateFromArray($simple);
    $solo = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'tags',
      'name' => 'Solo Tag',
    ]);
    $this->assertCount(1, $solo, 'generateEngine() path created the tag from a string-keyed array.');

    // Numeric keys -> generateEngineDependant().
    $dependant = [
      0 => [
        'taxonomy_vocabulary' => [
          'tags' => [
            'check' => 1,
            'count' => 1,
            'fields' => ['name' => 'Dependant Tag'],
            'properties' => [],
          ],
        ],
      ],
    ];
    $this->generateHelpers()->generateFromArray($dependant);
    $dependant_tag = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'tags',
      'name' => 'Dependant Tag',
    ]);
    $this->assertCount(1, $dependant_tag, 'generateEngineDependant() path created the tag from a numeric-keyed array.');
  }

}
