<?php

require_once('../src/Dotenvy.php');

$options = [
  'custom_validators' => [
    'uppercase' => function (string $key, string $value, array $args) {
      return strtoupper($value);
    },
    'lowercase' => function (string $key, string $value, array $args) {
      return strtolower($value);
    },
    'throw_exception' => function (string $key, string $value, array $args) {
      throw new Exception(sprintf('%s=%s %s', $key, $value, implode(' - ', $args)));
    }
  ]
];

$dotenvy = new \Dotenvy\Dotenvy(__DIR__, $options);

$environment = $dotenvy->execute();
if (is_string($environment)) throw new Exception('Invalid env: ' . $environment);

var_dump($environment);



// if ($dotenvy->hasCacheFile()) {
//   $dotenvy->executeFromCache();
// } else {
//   $envresult = $dotenvy->execute();
//   if (is_array($envresult)) {
//     $dotenvy->writeCache($envresult);
//     var_dump($envresult);
//   } else {
//     throw new \Exception($envresult); //string
//   }
// }
