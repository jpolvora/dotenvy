# dotenvy

PHP Environment Variables Manager with dotenv (.env) files and validation by example

The goal is to provide a simple way to work with environment variables

## Installation

```shell
composer require jpolvora/dotenvy
```

# WorkFlow

Create a file named `.env.example` which will be shared through your dev team. This file must be commited to your repo.
Place you keys and values of your application variables.
The format is `KEY=__validation-string__`

For example:

```env
#.env.example
API_KEY = trim|fallback(123)|number
CI_ENV = trim|required|enum(development,production)
```

There are some validators are built-in into library

Current available validators are:

- `required`: value cannot be empty, can be set to any string

```shell
# .env.example
API_KEY=required
# .env
API_KEY=sdkfjsdlk8349759843udj #pass
API_KEY= #fail
```

- `number`: value cannot be empty and must be convertible to an integer (is_numeric)

```shell
# .env.example
PORT=number
# .env
PORT=8080 #pass
PORT=abc #fail
```

- `boolean`: value cannot be empty and must be convertible to boolean (filter_var)

```shell
# .env.example
ENABLED=boolean
# .env
ENABLED=1 #pass
ENABLED=true #pass
ENABLED=0 #pass
ENABLED=FALSE #pass
ENABLED=foo #fail
```

- `enum`: value cannot be empty and should be co

```shell
# .env.example
NODE_ENV=enum(development,production)
# .env
NODE_ENV=development #pass
NODE_ENV=production #pass
NODE_ENV= #FAIL
NODE_ENV=staging #fail
```

- `fallback`:Value can be empty, but will be fallback to desired value

```shell
# .env.example
APP_LANG=fallback(en-us)
# .env
APP_LANG=pt-br # $_ENV['APP_LANG'] will be 'pt-br'
APP_LANG= # $_ENV['APP_LANG'] will fallback to 'en-us'
```

- `trim`:Just trims the string before set to env vars

```shell
# .env.example
WHITE_SPACES=trim
# .env
WHITE_SPACES=   string_that_should_be_trimmed #will trim left and right trailling white spaces
```

### important:

Order of validators are mandatory. They will run sequentially, passing the resulting value to the next validator, in a middleware mode. In case validator evaluates to invalid, the pipeline will be interrupted

## Custom validators

You can create custom validators and feed Dotenvy with an array with key:value function name, function ref.
Rules:

- Validators must be functions with the following signature:

```php
function (string $key, string $value, array $args)
```

- You must always return a string that will be passed to next validator in validator chain/pipeline. If you pass null or empty string, the value will be ignored.
- If you want to invalidate the value, you must throw an exception and tell the user what happened.

```php
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
```

Reference your validator in `.env.example`

```shell
#.env.example
MY_ENV_VAR=my_custom_validator_name(my_custom_parameter)
ANOTHER_ENV_VAR=uppercase
```

```shell
#.env
ANOTHER_ENV_VAR=this_value_will_be_uppercased #will evaluate to THIS_VALUE_WILL_BE_UPPERCASED
```

# Usage

```php
$dotenvy = new \Dotenvy\Dotenvy(__DIR__); //directory of containing files (.env and .env.example)

$environment = $dotenvy->execute();
if (is_string($environment)) throw new Exception('Invalid env: ' . $environment);

var_dump($environment);

```

# Environment Results

After running Dotenvy, environment variables will be available through:

- `$_SERVER`
- `$_ENV`
- `getenv()`
- `apache_getenv()`

### Important

Order of values precedence:

- `$_SERVER`
- `$_ENV`
- `getenv()`
- `apache_getenv()`
- `.env` file
- fallback validator
- throw exception

# Performance Optimization

Dotenvy can use a compiled cache file for maximum performance.
The following code can be used to boost performance in production mode:

```php
$dotenvy = new \Dotenvy\Dotenvy(__DIR__);

$is_production = TRUE; //my custom logic to get info about production mode

if ($is_production) {
  if ($dotenvy->hasCacheFile()) {
    $dotenvy->executeFromCache();
  } else {
    $envresult = $dotenvy->execute();
    if (is_array($envresult)) {
      $dotenvy->writeCache($envresult);
    } else {
      throw new \Exception($envresult);
    }
  }
} else {
  //not in production mode
  //delete cache file if exist
  $dotenvy->clearCache();
  $dotenvy->execute();
}

```

There's a static helper method that simplifies all logical above. The signature is:

```php
\Dotenvy\Dotenvy::autoExec(string $directory, array $storage, string $key, string $value);
```

Below we tell Dotenvy to look in `$_SERVER` array for a key named `CI_ENV` and check if its value matches `production`. Case this expression evaluates to `TRUE` then `Dotenvy` will run through cache.

```php
\Dotenvy\Dotenvy::autoExec(__DIR__, $_SERVER, 'CI_ENV', 'production');
```

# Run tests

```
cd tests && php index.php
```

# contributing

// todo:
Fork and make pull requests.
