# dotenvy

PHP Environment Variables Manager with dotenv (.env) files and validation by example

The goal is to provide a simple way to work with environment variables

## Installation

```shell
composer require jpolvora/dotenvy
```

## Usage

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

- options - This validator
