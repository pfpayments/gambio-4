

PostFinance Checkout Plugin for Gambio
=============================

The PostFinance Checkout plugin wraps around the PostFinance Checkout API. This library facilitates your interaction with various services such as transactions.

## Requirements

- PHP 7.2 to PHP 8.2
- Gambio 4.5 to Gambio 4.9
- Gambio 26.x

We only support the Gambio standard checkout (without modifications)

## Installation

**Please install it manually**

### Manual Installation


1. You can download the package in its entirety. The [Releases](../../releases) page lists all stable versions.

2. Uncompress the zip file you download

3. Include it to your Gambio shop root folder

4. Run the install command
```bash
# Please go to /GXModules/PostFinanceCheckout/PostFinanceCheckoutPayment and run the command
composer install
```

5. Login to Admin Panel

6. Click on Toolbox > Clear Cache and clear all caches

### for Gambio 26.x

7. Click on Store

8. Find PostFinance Checkout Payment and click on it

9. Clear the cache again (repeat step 5)

10. Select Modules > Module Center > PostFinance Checkout Payment again and click Edit

11. Enter correct data from PostFinance Checkout API and click Save. Payment methods will be synchronised

12. Navigate To Settings -> Payment Systems -> Added Modules -> PostFinance Checkout Payment

13. Install the PostFinance Checkout Payment System

14. Click Edit, select payment methods that you want to use and save configuration (Payment methods are synchronized from PostFinance Checkout and only if they are enabled)

### For Gambio 4.x

7. Click on Modules > Module Center > PostFinance Checkout Payment

8. Install the module and clear the cache again (repeat step 5)

9. Select Modules > Module Center > PostFinance Checkout Payment again and click Edit

10. Enter correct data from PostFinance Checkout API and click Save. Payment methods will be synchronised

11. Navigate To Modules -> Payment Systems

12. Click on "Miscellaneous" tab and find "added modules" and click the PostFinance Checkout Payment.

13. Install the PostFinance Checkout Payment System

14. Click Edit, select payment methods that you want to use and save configuration (Payment methods are synchronized from PostFinance Checkout and only if they are enabled)

## Usage
The library needs to be configured with your account's space id, user id, and application key which are available in your PostFinance Checkout
account dashboard.

## Documentation

[Documentation](https://plugin-documentation.postfinance-checkout.ch/pfpayments/gambio-4/1.0.32/docs/en/documentation.html)

## License

Please see the [license file](https://github.com/pfpayments/gambio-4/blob/master/LICENSE.txt) for more information.
