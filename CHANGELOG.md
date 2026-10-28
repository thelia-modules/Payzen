# 3.2.0

- The module can be combined with another means of payment, a gift card for one: it declares `Thelia\Module\BalanceChargingPaymentModuleInterface` and sends PayZen what `getAmountToCharge()` answers, the balance left by the other parts of the order, instead of the order total. This covers `vads_amount`, the 3D Secure threshold and the amounts of the instalments of a multiple times payment.
- The confirmation of a payment carries the code of the module the order was paid with (PayzenMulti for an instalment payment), so that the core accepts the late confirmation of a payment part it released.
- Requires Thelia 3.3.0 or later, where the interface exists.

# 1.3.2

- Alphanumeric secret keys are now allowed.
- Signature is now crypted using HMAC-SHA-256 instead of the deprecated SHA-1 algorithm.

# 1.3.1

- Email management fixes
- Obsolete files are now automatically removed.

# 1.3.0

- Minimum Thelia version is now 2.3.0
- module.xml uses the v2.2 module schema
- Using hooks for configuration instead of AdminIncludes.
- Added the choice of sending the order confirmation email on successful payment only
- Added the choice of sending a payment confirmation email. 

# 1.2.3

- Fix the post activation method, because it delete the config values

# 1.2

- Fix psr2
- Fix email templating

# 1.1

- Add compatibility with the module PayzenOneOffSEPA
