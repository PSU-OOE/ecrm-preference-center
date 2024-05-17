<?php

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

require_once 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$loader = new FilesystemLoader('src');
$loader->addPath('node_modules/@psu-ooe', 'psu-ooe');
$twig = new Environment($loader);

function moveKeyBefore($arr, $find, $move) {
  if (!isset($arr[$find], $arr[$move])) {
    return $arr;
  }

  $elem = [$move=>$arr[$move]];  // cache the element to be moved
  $start = array_splice($arr, 0, array_search($find, array_keys($arr), TRUE));
  unset($start[$move]);  // only important if $move is in $start
  return $start + $elem + $arr;
}

$twig->addFunction(new TwigFunction('get_favicon', function() {
  return base64_encode(file_get_contents('src/favicon.ico'));
}));

$twig->addFunction(new TwigFunction('get_component_stylesheets', function () {
  $manifests = [];
  foreach (glob('node_modules/@psu-ooe/*/package.json') as $manifest) {
    $manifest_json = json_decode(file_get_contents($manifest), TRUE, 512, JSON_THROW_ON_ERROR);
    $manifests[$manifest_json['name']] = $manifest_json;
  }
  // Recursively sort the manifests until dependency order is met...
  while (TRUE) {
    $modified = FALSE;
    foreach ($manifests as $component => $manifest) {
      $component_position = array_search($component, array_keys($manifests), TRUE);
      if (isset($manifest['dependencies'])) {
        foreach ($manifest['dependencies'] as $dependency => $version) {
          $dependency_position = array_search($dependency, array_keys($manifests), TRUE);
          if ($component_position < $dependency_position) {
            $manifests = moveKeyBefore($manifests, $component, $dependency);
            $modified = TRUE;
            break 2;
          }
        }
      }
    }
    if (!$modified) {
      break;
    }
  }

  $styles = '';
  foreach (array_keys($manifests) as $manifest) {
    $component = str_replace('@psu-ooe/', '', $manifest);
    $potential_css_file = "node_modules/@psu-ooe/$component/dist/styles.css";
    if (file_exists($potential_css_file)) {
      $file_content = trim(str_replace('/*# sourceMappingURL=styles.css.map */', '', file_get_contents($potential_css_file)));
      // Strip out any UTF-8 BOM sequences before inlining.
      $styles .= preg_replace("/^\xEF\xBB\xBF/", '', $file_content);
    }
  }
  return $styles;
}));

$artifact = $twig->render('index.twig', [
  'phone' => '814-865-5403',
  'email' => 'worldcampus@psu.edu',
  'phone_toll_free' => '800-252-3592',
  'fax' => '814-865-3290',
  'address_line_1' => 'The Pennsylvania State University',
  'address_line_2' => '128 Outreach Building',
  'address_city' => 'University Park',
  'address_state' => 'PA',
  'address_zip' => '16802',
  'social_platforms' => [
    'instagram' => 'https://www.instagram.com/pennstateworldcampus/',
    'facebook' => 'https://www.facebook.com/psuworldcampus',
    'linkedin' => 'https://www.linkedin.com/company/penn-state-world-campus',
    'twitter' => 'https://twitter.com/PSUWorldcampus',
    'youtube' => 'https://www.youtube.com/user/PSUWorldCampus',
    'flickr' => 'https://www.flickr.com/photos/psuworldcampus/albums',
    'pinterest' => 'https://www.pinterest.com/PSUWorldCampus/',
    'blog' => 'https://blog.worldcampus.psu.edu/',
  ],
  'legal_links' => [
    'Accessibility' => 'https://www.worldcampus.psu.edu/accessibility',
    'Equal Opportunity' => 'https://policy.psu.edu/policies/hr11',
    'Nondiscrimination' => 'https://policy.psu.edu/policies/ad85',
    'Privacy' => 'https://www.worldcampus.psu.edu/privacy-policy',
    'Consumer Information and Disclosures' => 'https://www.worldcampus.psu.edu/consumer-information-and-disclosures',
    'The Pennsylvania State University © [[COPYRIGHT_YEAR]]' => 'https://www.psu.edu/copyright-information',
  ],
]);
file_put_contents('dist/index.html', $artifact);
