<?php

require_once('../src/Dotenvy.php');

$options = [
  //  'envfile' => 'my.env',
  'cachefile' => date("YmdHi") . '.env',
  'custom_validators' => [
    'mycustomvalidator' => function (string $key, string $value, array $args) {
      return '(-' . $value . '-)';
    }
  ]
];

//$environment = Dotenvy\Dotenvy::exec_development(__DIR__, $options);
$environment = Dotenvy\Dotenvy::exec_production(__DIR__, $options);

//$dotenvy = new \Dotenvy\Dotenvy(__DIR__, $options);
//$environment = $dotenvy->execute();

if (is_string($environment)) throw new Exception('Invalid env: ' . $environment);

var_dump($environment);

print_r($_ENV);



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
