<?php

/**
 * @ Author: Jone Pólvora
 * @ Create Time: 2020-01-08 14:53:16
 * @ Modified by: Jone Pólvora
 * @ Modified time: 2020-01-08 22:27:43
 * @ Description:
 */

namespace Dotenvy;

class Dotenvy
{
  private $directory;
  private $example;
  private $real;
  private $allow_overwrite;

  public static function autoExec(string $directory, array $storage, string $key, string $value)
  {
    $is_production = array_key_exists($key, $storage) && $storage[$key] === $value;

    $envhelper = new self($directory);
    if ($is_production) {
      //is production - write and use cache if necessary
      if ($envhelper->hasCacheFile()) {
        $envhelper->executeFromCache();
      } else {
        $envresult = $envhelper->execute();
        if (is_array($envresult)) {
          $envhelper->writeCache($envresult);
        } else {
          throw new \Exception($envresult); //string
        }
      }
    } else {
      //development mode - exec default
      $envhelper->clearCache();
      $envresult = $envhelper->execute();
      if (is_string($envresult)) {
        throw new \Exception($envresult); //string
      }
    }
  }

  public function __construct(string $directory, string $example = '.env.example', string $real = '.env', bool $allowOvewrite = true)
  {
    if (empty($directory)) throw new \Exception('argument "$directory" is required');
    $this->directory = $directory;
    if (!empty($example)) $this->example = $example;
    if (!empty($real)) $this->real = $real;
    $this->allow_overwrite = $allowOvewrite;
  }

  private function getCacheFileName(): string
  {
    return $this->directory . DIRECTORY_SEPARATOR . '.env.cache';
  }

  public function hasCacheFile(): bool
  {
    return is_file($this->getCacheFileName());
  }

  public function clearCache()
  {
    $cacheFileName = $this->getCacheFileName();
    if (is_file($cacheFileName)) unlink($cacheFileName);
  }

  public function writeCache(array $lines)
  {
    $cacheFileName = $this->getCacheFileName();

    $txt = '';
    foreach ($lines as $line) {
      $key = $line['key'];
      $val = $line['value'];
      $txt .= "$key=$val" . PHP_EOL;
    }

    $fp = fopen($cacheFileName, "w");
    if (flock($fp, LOCK_EX)) {  // acquire an exclusive lock
      fwrite($fp, $txt);
      fflush($fp);            // flush output before releasing the lock
      flock($fp, LOCK_UN);    // release the lock
    } else {
      echo "Couldn't get the lock!";
    }

    fclose($fp);
  }

  public function executeFromCache()
  {
    $cacheFileName = $this->getCacheFileName();
    if (is_file($cacheFileName)) {
      $cacheLines = $this->getLinesFromFile($cacheFileName);
      $cacheValues = $this->parseLines($cacheLines);
      foreach ($cacheValues as $key => $value) {
        $this->setEnvironmentValue($key, $value);
      }
      return true;
    }

    return false;
  }

  public function execute()
  {
    $exampleFileName = $this->directory . DIRECTORY_SEPARATOR . $this->example;
    $exampleLines = $this->getLinesFromFile($exampleFileName);
    $exampleSource = $this->parseLines($exampleLines);

    $realFileName = $this->directory . DIRECTORY_SEPARATOR . $this->real;
    $realLines = $this->getLinesFromFile($realFileName);
    $realSource = $this->parseLines($realLines);

    $results = $this->validate($exampleSource, $realSource);

    $errors = '';
    $errorCount = 0;
    foreach ($results as $result) {
      if (is_bool($result['is_valid']) && !$result['is_valid']) {
        $errorCount++;
        $errors .= PHP_EOL . 'Erro: ' . $result['message'];
      }
    }

    if ($errorCount > 0) return $errors;

    foreach ($results as $result) {
      $this->setEnvironmentValue($result['key'], $result['value']);
    }

    return $results;
  }

  private function getLinesFromFile(string $fileName): array
  {
    try {
      if (is_file($fileName)) {
        $contents = file_get_contents($fileName);
        if ($contents) {
          return explode("\n", $contents);
        }
      }
    } catch (\Throwable $th) {
    }

    return [];
  }

  private function parseLines(array $lines)
  {
    $result = array();
    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line)) continue;
      list($k, $v) = explode('=', $line);
      if (empty($k) || empty($v)) continue;

      $result[$k] = $v;
    }

    return $result;
  }

  private function validate(array $exampleSource, array $realSource): array
  {
    //verificar as chaves do example    

    $results = [];

    foreach ($exampleSource as $key => $validators) {
      $currentValue = $this->getCurrentEnvironmentValue($key);
      if (empty($currentValue)) $currentValue = array_key_exists($key, $realSource) ? $realSource[$key] : '';

      try {
        $validationResult = $this->validateItem($key, $validators, $currentValue);
        array_push($results, ['key' => $key, 'value' => $validationResult, 'is_valid' => TRUE, 'message' => 'ok']);
      } catch (\Throwable $th) {
        array_push($results, ['key' => $key, 'value' => $currentValue, 'is_valid' => FALSE, 'message' => $th->getMessage()]);
      }
    }

    return $results;
  }

  private function getCurrentEnvironmentValue(string $key): string
  {
    if (array_key_exists($key, $_SERVER)) return $_SERVER[$key];

    if (array_key_exists($key, $_ENV)) return $_ENV[$key];

    if (function_exists('getenv')) {
      $val = getenv($key);
      if (!empty($val)) return $val;
    }

    if (function_exists('apache_getenv')) {
      $val = apache_getenv($key);
      if (!empty($val)) return $val;
    }

    return '';
  }

  private function validateItem(string $key, string $validators, string $currentValue): string
  {
    $fns = $this->getValidators($validators);
    $retval = $currentValue;

    foreach ($fns as $fn) {
      $out = call_user_func_array(array($this, $fn['method']), array($key, $retval, $fn['args']));
      if (!empty($out)) $retval = $out;
    }

    return $retval;
  }

  private function getValidators(string $validators): array
  {
    $result =  [];

    $fns = explode('|', $validators);
    foreach ($fns as $fn) {

      $tuple = $this->sliceMethodArgs($fn);

      $searchMethod = 'validator_' . $tuple['method'];
      if (method_exists($this, $searchMethod)) {
        array_push($result, array('method' => $searchMethod, 'args' => $tuple['args']));
      }
    }

    return $result;
  }

  private function sliceMethodArgs(string $str)
  {
    $str = trim($str);

    $pos = strrpos($str, "(");
    if (is_bool($pos) && !$pos) {
      return ['method' => $str, 'args' => []];
    }

    $method = substr($str, 0, $pos);

    $endpos = strrpos($str, ")");
    if (is_bool($endpos) && !$endpos) {
      return ['method' => $method, 'args' => []];
    }

    $args_str = substr($str, $pos + 1, -1);
    $args = explode(',', $args_str);

    return ['method' => $method, 'args' => $args];
  }

  private function setEnvironmentValue($key, $value)
  {
    if (!array_key_exists($key, $_SERVER) || $this->allow_overwrite) {
      $_SERVER[$key] = $value;
    }
    if (!array_key_exists($key, $_ENV) || $this->allow_overwrite) {
      $_ENV[$key] = $value;
    }

    if (function_exists('putenv') && function_exists('getenv')) {
      if (empty(getenv($key)) || $this->allow_overwrite) {
        putenv("$key=$value");
      }
    }

    if (function_exists('apache_setenv') && function_exists('apache_getenv')) {
      if (empty(apache_getenv($key)) || $this->allow_overwrite) {
        apache_setenv($key, $value);
      }
    }
  }

  /* #region validators */

  public function validator_required(string $key, string $value, array $args): string
  {
    if (empty($value)) throw new \Exception(sprintf('[required_validator]: value for %s cannot be empty.', $key));
    return $value;
  }


  public function validator_default(string $key, string $value, array $args): string
  {
    if (empty($args)) throw new \Exception(sprintf('[default_validator]: No default value was provided for key %s', $key));
    if (empty($value)) $value = $args[0];

    return $value;
  }

  public function validator_options(string $key, string $value, array $args): string
  {
    if (empty($value)) throw new \Exception(sprintf('[options_validator]: missing options for key %s', $key));
    if (!in_array($value, $args)) {
      throw new \Exception(sprintf('[options_validator]: invalid value for key %s. Should be one of these values: %s. Value provided is %s', $key, implode(', ', $args), $value));
    }

    return $value;
  }

  public function validator_number(string $key, string $value, array $args): string
  {
    if (!is_numeric($value)) throw new \Exception(sprintf('[number]: invalid argument for [%s]. Should be numeric: %s - %s', $key, $value, $args));

    return $value + 0;
  }

  public function validator_boolean(string $key, string $value, array $args): string
  {
    if (empty($value)) throw new \Exception(sprintf('[boolean_validator]: empty value cannot be processed as boolean for key %s'));
    $isbool =  filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if (!is_bool($isbool) || !$isbool) throw new \Exception(sprintf('[boolean_validator]: invalid argument for [%s]. Should be boolean: %s', $key, $value));
    return (bool) $value;
  }

  public function validator_trim(string $key, string $value, array $args): string
  {
    return trim($value);
  }

  /* #endregion */
}
