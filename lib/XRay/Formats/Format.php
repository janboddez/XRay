<?php
namespace p3k\XRay\Formats;

use DOMDocument, DOMXPath;
use HTMLPurifier;
use HTMLPurifier_HTML5Config;
use HTMLPurifier_TagTransform_Simple;

interface iFormat {

  public static function matches_host($url);
  public static function matches($url);

}

abstract class Format implements iFormat {

  protected static function _unknown() {
    return [
      'data' => [
        'type' => 'unknown'
      ]
    ];
  }

  protected static function _loadHTML($html) {
    $doc = new DOMDocument();
    @$doc->loadHTML($html);

    if(!$doc) {
      return [null, null];
    }

    $xpath = new DOMXPath($doc);

    return [$doc, $xpath];
  }

  protected static function sanitizeHTML($html, $allowImg=true, $baseURL=false) {
    $allowed = [
      '*[class]',
      'a[href]',
      'abbr',
      'b',
      'br',
      'code',
      'del',
      'em',
      'i',
      'q',
      //'strike',
      'strong',
      'time[datetime]',
      'blockquote',
      'pre',
      'p',
      'h1',
      'h2',
      'h3',
      'h4',
      'h5',
      'h6',
      'ul',
      'li',
      'ol',
      //'span',
      // Allow `sub`, `sup`, tables, `figure`, and more.
      'sub',
      'sup',
      'table',
      'thead',
      'tbody',
      'tfoot',
      'tr',
      'th[colspan|rowspan]',
      'td[colspan|rowspan]',
      'caption',
      'figure',
      'figcaption',
      'audio[src|controls]',
      'div',
      'header',
      'footer',
    ];

    if($allowImg) {
      $allowed[] = 'picture';
      $allowed[] = 'img[src|alt]';
      $allowed[] = 'video[src|controls]';
      $allowed[] = 'source[src|type]';
    }

    $initial = HTMLPurifier_HTML5Config::createDefault();
    $config = HTMLPurifier_HTML5Config::create($initial);
    $config->set('Cache.DefinitionImpl', null);

    if (\p3k\XRay\allow_iframe_video()) {
      $allowed[] = 'iframe[src]';
      $config->set('HTML.SafeIframe', true);
      // Added CodePen embeds (`iframe` only).
      $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/|codepen\.io/(?:.+)/embed/)%');
      $config->set('AutoFormat.RemoveEmpty', true);
      // Removes iframe in case it has no src. This strips the non-allowed domains.
      $config->set('AutoFormat.RemoveEmpty.Predicate', array('iframe' => array(0 => 'src')));
    }

    $config->set('HTML.Allowed', implode(',', $allowed));

    if($baseURL) {
      $config->set('URI.MakeAbsolute', true);
      $config->set('URI.Base', $baseURL);
    }

    // Hoping this prevents, e.g., nested paragraph tags.
    $config->set('HTML.TidyLevel', 'heavy');

    $def = $config->maybeGetRawHTMLDefinition();

    // Add HTML `time` element.
    $def->addElement('time', 'Inline', 'Inline', 'Common', ['datetime' => 'Text']);

    // Transform `header`, `footer`, `div` to paragraphs (i.e., a block-level
    // element that's relatively easy to style).
    $def->info_tag_transform['header'] = new HTMLPurifier_TagTransform_Simple('p');
    $def->info_tag_transform['footer'] = new HTMLPurifier_TagTransform_Simple('p');
    $def->info_tag_transform['div']    = new HTMLPurifier_TagTransform_Simple('p');

    // Override the allowed classes to only support Microformats2 classes
    $def->manager->attrTypes->set('Class', new HTMLPurifier_AttrDef_HTML_Microformats2());
    $purifier = new HTMLPurifier($config);
    $sanitized = $purifier->purify($html);
    $sanitized = str_replace("&#xD;","\r",$sanitized);
    return trim($sanitized);
  }

  // Return a plaintext version of the input HTML
  protected static function stripHTML($html) {
    $initial = HTMLPurifier_HTML5Config::createDefault();
    $config = HTMLPurifier_HTML5Config::create($initial);
    $config->set('Cache.DefinitionImpl', null);
    $config->set('HTML.AllowedElements', ['br']);
    $purifier = new HTMLPurifier($config);
    $sanitized = $purifier->purify($html);
    $sanitized = str_replace("&#xD;","\r",$sanitized);
    $sanitized = html_entity_decode($sanitized);
    return trim(str_replace(['<br>','<br />'],"\n", $sanitized));
  }

}
