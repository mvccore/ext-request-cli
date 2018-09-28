# MvcCore Extension - Request - CLI

[![Latest Stable Version](https://img.shields.io/badge/Stable-v4.3.1-brightgreen.svg?style=plastic)](https://github.com/mvccore/ext-request-cli/releases)
[![License](https://img.shields.io/badge/Licence-BSD-brightgreen.svg?style=plastic)](https://mvccore.github.io/docs/mvccore/4.0.0/LICENCE.md)
![PHP Version](https://img.shields.io/badge/PHP->=5.3-brightgreen.svg?style=plastic)

MvcCore Request extension to parse console arguments into \MvcCore\Request object.

## Installation
```shell
composer require mvccore/ext-request-cli
```

## Usage
```cli
# equivalent to: index.php?controller=controller-name&action=action-name&id=10
php index.php -c controller-name -a action-name -id 10
```

### PHP application Bootstrap.php
Put this patching code in very beginning of your application:
```php
\MvcCore::GetInstance()->SetRequestClass('\\MvcCore\\Ext\\Request\\Cli');
```
