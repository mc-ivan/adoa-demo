# Processmaker Adoa
This package provides the necessary base code to start the developing a package in ProcessMaker 4.

## Development
If you need to create a new ProcessMaker package run the following commands:

```
git clone https://github.com/mc-ivan/adoa-demo.git
cd adoa-demo
php rename-project.php adoa
composer install
npm install
npm run dev
```

## Installation
* Use `composer require mc-ivan/adoa-demo` to install the package.
* Use `php artisan adoa:install` to install generate the dependencies.

## Navigation and testing
* Navigate to administration tab in your ProcessMaker 4
* Select `Skeleton Package` from the administrative sidebar

## Uninstall
* Use `php artisan adoa:uninstall` to uninstall the package
* Use `composer remove mc-ivan/adoa-demo` to remove the package completely
