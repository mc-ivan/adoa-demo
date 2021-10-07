# Processmaker Adoa
This package provides the necessary base code to start the developing a package in ProcessMaker 4.

## Development
If you need to create a new ProcessMaker package run the following commands:

```
git clone https://github.com/ProcessMaker/adoa.git
cd adoa
php rename-project.php adoa
composer install
npm install
npm run dev
```

## Installation
* Use `composer require processmaker/adoa` to install the package.
* Use `php artisan adoa:install` to install generate the dependencies.

## Navigation and testing
* Navigate to administration tab in your ProcessMaker 4
* Select `Skeleton Package` from the administrative sidebar

## Uninstall
* Use `php artisan adoa:uninstall` to uninstall the package
* Use `composer remove processmaker/adoa` to remove the package completely

# adoa-demo
