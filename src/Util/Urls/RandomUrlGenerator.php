<?php

namespace Drupal\generator_engine\Util\Urls;

/**
 * Generates random values for link fields.
 */
class RandomUrlGenerator {

  /**
   * Protocols to choose from.
   *
   * @var array
   */
  protected static array $protocols = ['http', 'https'];

  /**
   * Domains to choose from.
   *
   * @var array
   */
  protected static array $domains = ['example.com', 'test.com', 'mysite.org'];

  /**
   * Paths to choose from.
   *
   * @var array
   */
  protected static array $paths = [
    'home',
    'about',
    'contact',
    'api/v1/resource',
    'blog/post',
  ];

  /**
   * Generates a random link field value.
   *
   * @param int $paramCount
   *   Number of query parameters to append.
   *
   * @return array
   *   A link field value with "uri" and "title" keys.
   */
  public static function generate(int $paramCount = 2): array {
    $protocol = self::$protocols[array_rand(self::$protocols)];
    $domain = self::$domains[array_rand(self::$domains)];
    $path = self::$paths[array_rand(self::$paths)];

    $url = "{$protocol}://{$domain}/{$path}";

    $params = [];
    for ($i = 0; $i < $paramCount; $i++) {
      $key = self::randomString(4);
      $value = self::randomString(6);
      $params[$key] = $value;
    }

    if (!empty($params)) {
      $url .= '?' . http_build_query($params);
    }

    return [
      'uri' => $url,
      'title' => 'sample generted url',
    ];
  }

  /**
   * Generates a random alphanumeric string.
   *
   * @param int $length
   *   The desired length.
   *
   * @return string
   *   The generated string.
   */
  protected static function randomString(int $length = 6): string {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, $length);
  }

  /**
   * Overrides the protocols used when generating URLs.
   *
   * @param array $protocols
   *   The protocols to choose from.
   */
  public static function setProtocols(array $protocols): void {
    self::$protocols = $protocols;
  }

  /**
   * Overrides the domains used when generating URLs.
   *
   * @param array $domains
   *   The domains to choose from.
   */
  public static function setDomains(array $domains): void {
    self::$domains = $domains;
  }

  /**
   * Overrides the paths used when generating URLs.
   *
   * @param array $paths
   *   The paths to choose from.
   */
  public static function setPaths(array $paths): void {
    self::$paths = $paths;
  }

}
