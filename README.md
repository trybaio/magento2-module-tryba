=== Tryba Payment Gateway for Magento 2 ===

== Description ==

Accept Credit card, Debit card or Tryba account payment.

= Send and receive money from anyone with Our Borderless Payment Collection Platform. Payout straight to your bank account. =

Signup for an account [here](https://tryba.io/login)

== Installation ==

= via Composer =

1. composer require trybaio/magento2-module-tryba

2. php bin/magento module:enable WAF_Tryba --clear-static-content

3. php bin/magento setup:upgrade

== Configuration ==

Stores -> Configuration -> Payment Methods -> Tryba

1. Enabled: Mark this as "Yes" to enable Tryba extension.

2. Title: Method name to be shown to user during checkout.

3. Public Key: Enter the Public Key from your Tryba Account. This field is required.

4. Secret Key: Enter the Secret Key from your Tryba Account. This field is required.

== Changelog ==

= 1.0.0 =
* Initial version