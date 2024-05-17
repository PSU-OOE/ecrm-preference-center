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
    // @TODO: Remove after form styles land...
    if ($manifest_json['name'] === '@psu-ooe/base') {
      continue;
    }
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

if (!file_exists('dist') && !mkdir('dist') && !is_dir('dist')) {
  throw new \RuntimeException(sprintf('Directory "%s" was not created', 'dist'));
}

$config = json_decode(file_get_contents('config.json'), TRUE, 512, JSON_THROW_ON_ERROR);
foreach (glob('src/*.twig') as $filename) {
  $filename = basename($filename);
  $dest_name = str_replace('.twig', '.html', $filename);
  $artifact = $twig->render($filename, $config);
  file_put_contents("dist/$dest_name", $artifact);

}


