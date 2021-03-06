<?php
namespace p3k\XRay\Formats;

use HTMLPurifier, HTMLPurifier_Config;
use DOMDocument, DOMXPath;
use p3k\XRay\Formats;
use SimplePie;

class XML extends Format {

  public static function matches_host($url) { return true; }
  public static function matches($url) { return true; }

  public static function parse($http_response) {
    $xml = $http_response['body'];
    $url = $http_response['url'];

    $result = [
      'data' => [
        'type' => 'unknown',
      ],
      'url' => $url,
      'source-format' => 'xml',
      'code' => $http_response['code'],
    ];

    try {
      $feed = new SimplePie();
      $feed->set_stupidly_fast(true);  // Bypass sanitization (and a few more things), which we'll tackle in a second
      $feed->set_url_replacements([]); // Bypass relative URL resolution (handled in `self::_hEntryFromFeedItem()`)
      $feed->set_raw_data($xml);
      $feed->init();
      $feed->handle_content_type();

      $result['data']['type'] = 'feed';
      $result['data']['items'] = [];

      foreach($feed->get_items() as $item) {
        $result['data']['items'][] = self::_hEntryFromFeedItem($item, $feed);
      }

    } catch(\Throwable $t) {
      // At least provide some output.
      // \Log::error($t->getMessage());
      return [
        'error' => 'invalid_xml',
        'error_description' => trim($t->getMessage() ?: 'The feed could not be parsed'),
      ];
    }

    return $result;
  }

  private static function _hEntryFromFeedItem($item, $feed) {
    $entry = [
      'type' => 'entry',
      'author' => [
        'name' => null,
        'url' => null,
        'photo' => null
      ]
    ];

    $entry['uid'] = $item->get_id();
    $entry['url'] = $item->get_link() ?: null; // We'll remove empty elements afterward.

    if($item->get_gmdate('c'))
      $entry['published'] = $item->get_gmdate('c');

    if($item->get_content()) {
      $entry['content'] = [
        'html' => self::sanitizeHTML(
          \p3k\XRay\resolve_urls($item->get_content(), $entry['url'] ?? '')
        ),
        'text' => self::stripHTML($item->get_content())
      ];
    }

    if($item->get_title() && $item->get_title() !== $item->get_link()) {
      $title = self::stripHTML($item->get_title());
      $entry['name'] = $title;

      // Check if the title is a prefix of the content and drop if so
      if(isset($entry['content'])) {
        if(substr($title, -3) == '...' || substr($title, -1) == '…') {
          if(substr($title, -3) == '...') {
            $trimmedTitle = substr($title, 0, -3);
          } else {
            $trimmedTitle = substr($title, 0, -1);
          }
          if(substr($entry['content']['text'], 0, strlen($trimmedTitle)) == $trimmedTitle) {
            unset($entry['name']);
          }
        }
      }
    }

    $author = $item->get_author();

    if($author) {
      $entry['author']['name'] = $author->get_name() ?: null;
      $entry['author']['url']  = $author->get_link() ?: $feed->get_link();

      $entry['author'] = array_filter($entry['author']); // Remove empty elements
    }

    $enclosure = $item->get_enclosure();

    if($enclosure) {
      $prop = false;
      switch($enclosure->get_type()) {
        case 'audio/mpeg':
          $prop = 'audio'; break;
        case 'image/jpeg':
        case 'image/png':
        case 'image/gif':
          $prop = 'photo'; break;
      }
      if($prop)
        $entry[$prop] = [$enclosure->get_link()];
    }

    $entry = array_filter($entry); // Remove empty elements

    $entry['post-type'] = \p3k\XRay\PostType::discover($entry);

    return $entry;
  }

}
