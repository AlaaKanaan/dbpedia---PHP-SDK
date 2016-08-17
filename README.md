# CashU SOAP SDK for PHP


This repository contains CashU's PHP SDK and samples for SOAP API.

> **Before starting to use the sdk, please be aware of the [existing issues and currently unavailable or upcoming features](https://packagist.org/packages/alaakanaan/cashu-soap-php-sdk) for the SOAP APIs. (which the sdks are based on)**

## The SDK include this features:

   - Standard
   - Premier
   - Basic security token
   - Enhanced security token
   - Standard StandingOrder
   - Refund
   - Merchant Services

## Prerequisites

   - PHP 5.3 or above
   - [curl](http://php.net/manual/en/book.curl.php) extension must be enabled

## Installation

### - Using Composer
[**composer**](https://getcomposer.org/) is the recommended way to install the SDK. To use the SDK with project, add the following dependency to your application's composer.json and run `composer update --no-dev` to fetch the SDK.

You can download composer using instructions on [Composer Official Website.](https://getcomposer.org/download/)

#### Prerequisites
- *composer* for fetching dependencies (See [http://getcomposer.org](http://getcomposer.org))

#### Steps to Install :

Currently, CashU SOAP SDK for PHP is available at [https://packagist.org](https://packagist.org/packages/alaakanaan/cashu-soap-php-sdk). To use it in your project, you need to include it as a dependency in your project composer.json file. It can be done either:

* Running `composer require alaakanaan/cashu-soap-php-sdk: dev-master` command on your project root location (where project composer.json is located.)

* Or, manually editing composer.json file `require` field, and adding `"alaakanaan/cashu-soap-php-sdk" :  "dev-master"` inside it.

The resultant sample *composer.json* would look like this:

```php
{
  ...

  "name": "sample/website",
  "require": {
  	"alaakanaan/cashu-soap-php-sdk" : "dev-master"
  }

  ...
}
```
## Usage

To write an app that uses the SDK

   * Update your project's composer.json file, and add dependency on PHP Rest API SDK by running `composer require alaakanaan/cashu-soap-php-sdk:dev-master` and run `composer update --no-dev` to fetch all dependencies.
   * open the configuration file `src/CashU/SOAP/Config/config.yml` and change the SDK config to your merchant account details.
   * Obtain your merchant Id and encryption keyword from the [CashU portal](https://sandbox.cashu.com/Merchants/en/login). You will use them in the config file.
   * Now you are all set to make your first API call. Create a resource object as per your need and call the relevant operation.

```php

    use Cashu\CashuClient;

        $client = new CashuClient();

        $payment = new \Cashu\lib\model\Payment();

        $payment->setAmount(200);
        $payment->setCurrency('USD');
        $payment->setDisplayText('test payment');
        $payment->setLanguage('en');
        $payment->setSessionId('11');
        $payment->setTxt1('test text 1');

        $html=$client->getPremierMethod($payment->getTransactionCode());
        return $html;


```
## Workflow Logger

To check the workflow in the SDK..like the notification url..edit the path to your log file from config.yml


## API Tests Files

   * http://127.0.0.1/payment-getaway-soap-sdk/tests/index.html