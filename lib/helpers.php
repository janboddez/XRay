<?php
namespace p3k\XRay;

// Attempts to resolve relative links inside a chunk of HTML
function resolve_urls(string $html, string $baseUrl): string
{
  if(empty($baseUrl))
    return $html;

  $html = mb_convert_encoding($html, 'HTML-ENTITIES', mb_detect_encoding($html));

  libxml_use_internal_errors(true);

  $doc = new \DOMDocument();
  $doc->loadHTML($html, LIBXML_HTML_NODEFDTD);

  $xpath = new \DOMXPath($doc);

  foreach ($xpath->query('//*[@src or @href or @data]') as $node) {
    // Currently leaves `srcset` untouched

    if ($node->hasAttribute('href')) {
      $node->setAttribute('href', \Mf2\resolveUrl($baseUrl, $node->getAttribute('href')));
    }

    if ($node->hasAttribute('src')) {
      $node->setAttribute('src', \Mf2\resolveUrl($baseUrl, $node->getAttribute('src')));
    }

    if ($node->hasAttribute('data')) {
      $node->setAttribute('data', \Mf2\resolveUrl($baseUrl, $node->getAttribute('data')));
    }
  }

  return $doc->saveHTML();
}

function view($template, $data=[]) {
  global $templates;
  return $templates->render($template, $data);
}

// Adds slash if no path is in the URL, and convert hostname to lowercase
function normalize_url($url) {
  $parts = parse_url($url);
  if(empty($parts['path']))
    $parts['path'] = '/';
  if(isset($parts['host']))
    $parts['host'] = strtolower($parts['host']);
  return build_url($parts);
}

function normalize_urls($urls) {
  return array_map('\p3k\XRay\normalize_url', $urls);
}

function urls_are_equal($url1, $url2) {
  $url1 = normalize_url($url1);
  $url2 = normalize_url($url2);
  return $url1 == $url2;
}

function build_url($parsed_url) {
  $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
  $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
  $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
  $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
  $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
  $pass     = ($user || $pass) ? "$pass@" : '';
  $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
  $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
  $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
  return "$scheme$user$pass$host$port$path$query$fragment";
}

function should_follow_redirects($url) {
  $host = parse_url($url, PHP_URL_HOST);
  if(preg_match('/brid\.gy|appspot\.com|blogspot\.com|youtube\.com/', $host)) {
    return false;
  } else {
    return true;
  }
}

function phpmf2_version() {
  $composer = json_decode(file_get_contents(dirname(__FILE__).'/../composer.lock'));
  $version = 'unknown';
  foreach($composer->packages as $pkg) {
    if($pkg->name == 'mf2/mf2') {
      $version = $pkg->version;
    }
  }
  return $version;
}

function allow_iframe_video($value = NULL) {
  static $allow_iframe_video = false;

  if (isset($value))
    $allow_iframe_video = $value;

  return $allow_iframe_video;
}
