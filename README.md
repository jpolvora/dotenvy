# dotenvy

PHP Environment Variables Manager with dotenv (.env) files and validation by example

The goal is to provide a simple way to work with environment variables

## Installation

```shell
composer require jpolvora/dotenvy
```

## Setup

Create a file named `.env.example` which will be shared through your dev team. This file must be commited to your repo.
Place you keys and values of your application variables.
The format is `KEY=__validation-string__`

For example:

```env
API_KEY = trim|default(123)|number
CI_ENV = trim|required|enum(development,production)
```

Validators are built-in into library

`// todo: Provide a way to extend with custom validators`

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
APP_LANG=fallback(en-us)
# .env
APP_LANG=pt-br # $_ENV['APP_LANG'] will be 'pt-br'
APP_LANG= # $_ENV['APP_LANG'] will fallback to 'en-us'
```

# Usage

```php
$dotenvy = new \Dotenvy\Dotenvy(__DIR__);
$environment = $dotenvy->execute();
if (is_string($environment)) throw new Exception('Invalid env: ' . $environment);

var_dump($environment);

```

# Environment Results

After running

# Performance Optimization

Dotenvy can use a compiled cache file for maximum performance.
The following code can be used to boost performance in production mode:

```php

$is_production = TRUE; //my custom logic to get info about production mode

$dotenvy = new \Dotenvy\Dotenvy(__DIR__);

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
