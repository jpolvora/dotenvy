<?php

require_once('../src/Dotenvy.php');

$dotenvy = new \Dotenvy\Dotenvy(__DIR__);

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
