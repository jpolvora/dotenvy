<?php

/**
 * @ Author: Jone Pólvora
 * @ Create Time: 2020-01-08 14:53:16
 * @ Modified by: Jone Pólvora
 * @ Modified time: 2020-01-09 09:30:43
 * @ Description:
 */

namespace Dotenvy;

define('VALIDATOR_SEPARATOR_CHAR', '|');
define('VALIDATOR_ARGS_START', '(');
define('VALIDATOR_ARGS_END', ')');

class Dotenvy
{
  /**
   * @var string  Directory where look for .env files
   */
  private $directory;

  private $example = '.env.example';
  private $real = '.env';
  private $allow_overwrite = true;
  protected $custom_validators = [];

  /**
   * Creates Dotenvy instance and executes with Cache
   * @param string $directory   Directory containing .env files
   * @param array $options      Array with options
   * @return string|array       Return array in case of success, or string in case of errors.
   */
  public static function exec_production(string $directory, array $options)
  {
    $instance = new self($directory, $options);
    if ($instance->hasCacheFile()) {
      return $instance->executeFromCache();
    }

    $envresult = $instance->execute();
    if (is_array($envresult)) {
      $instance->writeCache($envresult);
    }

    return $envresult;
  }

  /**
   * Creates Dotenvy instance and executes it with default options
   * @param string $directory   Directory containing .env files
   * @param array $options      Array with options
   * @return string|array       Return array in case of success, or string in case of errors.
   */
  public static function exec_development(string $directory, array $options)
  {
    $instance = new self($directory, $options);
    $instance->clearCache();
    return $instance->execute();
  }

  public function __construct(string $directory, array $options = [])
  {
    if (empty($directory)) throw new \Exception('argument "$directory" is required');
    $this->directory = $directory;
    if (array_key_exists('example', $options)) $this->example = $options['example'];
    if (array_key_exists('envfile', $options)) $this->real = $options['envfile'];
    if (array_key_exists('allow_ovewrite', $options)) $this->allow_overwrite = $options['allow_ovewrite'];
    if (array_key_exists('custom_validators', $options)) $this->custom_validators = $options['custom_validators'];
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
      $result[$k] = $v;
    }

    return $result;
  }

  private function validate(array $exampleSource, array $realSource): array
  {
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

  private function validateItem(string $key, string $line, string $currentValue): string
  {
    $validators = $this->getValidators($line);
    $retval = $currentValue;

    foreach ($validators as list($custom, $method, $args)) {
      $out = '';
      if ($custom) {
        $out = call_user_func_array($this->custom_validators[$method], array($key, $retval, $args));
      } else {
        $out = call_user_func_array(array($this, $method), array($key, $retval, $args));
      }
      if (is_string($out) && strlen($out) === 0) continue;
      $retval = $out;
    }

    return $retval;
  }

  private function getValidators(string $line): array
  {
    $result =  [];

    $validators = explode(VALIDATOR_SEPARATOR_CHAR, $line);
    foreach ($validators as $validator) {
      list($method, $args) = $this->sliceMethodArgs($validator);
      $searchMethod = 'validator_' . $method;
      if (method_exists($this, $searchMethod)) {
        array_push($result, array(FALSE,  $searchMethod,  $args));
        continue;
      }

      if (array_key_exists($method, $this->custom_validators)) {
        array_push($result, array(TRUE, $method,  $args));
      }
    }

    return $result;
  }

  private function sliceMethodArgs(string $str)
  {
    $str = trim($str);

    $pos = strrpos($str, VALIDATOR_ARGS_START);
    if (is_bool($pos) && !$pos) {
      return [$str,  []];
    }

    $method = substr($str, 0, $pos);

    $endpos = strrpos($str, VALIDATOR_ARGS_END);
    if (is_bool($endpos) && !$endpos) {
      return [$method,  []];
    }

    $args_str = substr($str, $pos + 1, -1);
    $args = explode(',', $args_str);

    return [$method,  $args];
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

  /* #region built-in validators */

  public function validator_required(string $key, string $value, array $args): string
  {
    if (empty($value)) throw new \Exception(sprintf('[required_validator]: value for %s cannot be empty.', $key));
    return $value;
  }

  public function validator_fallback(string $key, string $value, array $args): string
  {
    if (empty($args) || strlen($args[0]) === 0) throw new \Exception(sprintf('[default_validator]: No default value was provided for key %s', $key));
    if (strlen($value) === 0) $value = $args[0];

    return $value;
  }

  public function validator_enum(string $key, string $value, array $args): string
  {
    if (empty($args)) throw new \Exception(sprintf('[enum_validator]: missing enumeration options for key %s', $key));
    if (strlen($value) === 0) throw new \Exception(sprintf('[enum_validator]: no value provided for key %s', $key));
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
    if (strlen($value) === 0) throw new \Exception(sprintf('[boolean_validator]: empty value cannot be processed as boolean for key %s', $key));
    $isbool =  filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if (!is_bool($isbool) || $isbool === NULL) throw new \Exception(sprintf('[boolean_validator]: invalid argument for [%s]. Should be boolean: %s', $key, $value));
    return $isbool;
  }

  public function validator_trim(string $key, string $value, array $args): string
  {
    return trim($value);
  }

  /* #endregion */
}
