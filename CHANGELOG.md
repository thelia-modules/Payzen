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
