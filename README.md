# Tryba Payment Gateway for Magento 2

## About

Tryba is a banking and management platform built for individuals, self employed and companies. Tryba is equipped with modern e-money accounts and Visa cards, website and storefront builders, invoicing, payment links, expense and budgeting etc.

The ideal online payment page for your webshop:

-  Accept payments in Magento through Tryba payment gateway

-  Wide range of payment methods

-  Get the list of all the transactions under the ‘Merchant transactions’ grid.

-  Tryba account can view detailed information about each of the transactions.

-  Tryba invoicing enables your business to take invoice payment globally with enhanced payment options and transaction recording, making it impossible to miss any business.

-  Provide clear dashboard for all your payment, revenue and administrative functions

-  Ability to test the integration with PayPal Sandbox


## Version number                 
 
* Latest version 1.0.0

## Requirements:

- PHP v7.4 to v8.2

- Magento v2.2.x to v2.4.5-p1
                              
## Supported methods ##

* Credit/Debit Card

* Open Banking

* Tryba Account

* Paypal

## Installation using Composer ##
Magento 2 uses the Composer to manage the module package and the library. Composer is a dependency manager for PHP. Composer declares the libraries your project depends on and it will manage (install/update) them for you.

Check if your server has composer installed by running the following command: composer –v

If your server doesn’t have composer installed, you can easily install it by using this manual: https://getcomposer.org/doc/00-intro.md

Step-by-step to install the Magento 2 extension through Composer:

1.	Connect to your server running Magento® 2 using SSH or another method (make sure you have access to the command line).

2.	Locate your Magento 2 project root.

3.	Install the Magento 2 extension through composer and wait until it's completed:
    - composer require trybaio/magento2-module-tryba

4.	After that run the Magento upgrade and clean the caches: 
    - php bin/magento setup:upgrade

5.  If Magento is running in production mode you also need to redeploy the static content:
    - php bin/magento setup:static-content:deploy

6.  After the installation: Go to your Magento admin portal and open 'Stores' > 'Configuration' > 'Payment Methods' > 'Tryba'.


## Additional ways to install ##

### Manual instalation ###

1. Go to app/code folder

2. Unzip magento2-module-tryba.zip file which attached to [release](https://github.com/trybaio/magento2-module-tryba/releases/) 

3. Continue installation from step 4 in "Installation using Composer"