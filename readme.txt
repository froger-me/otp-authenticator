=== OTP Authenticator  ===
Contributors: frogerme
Tags: 2fa, OTP, passwordless login
Requires at least: 4.9.5
Tested up to: 5.5
Stable tag: trunk
Requires PHP: 7.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

One-Time Password Authentication for WordPress

== Description ==

Add Two-Factor Authentication, Passwordless Authentication and Account Validation to your WordPress website.

== Overview ==

This plugin adds the following major features to WordPress:

* **3 One-Time Password modes**: Two-Factor Authentication, Passwordless Authentication and Account Validation.
* **3 Authentication Gateways for OTP Verification Codes:** WordPress Email, Twilio SMS, Alibaba Cloud SMS ; all with option to use a sandbox mode.
* **Two-Factor Authentication:** allow (or force) users to authenticate with a second factor on top of their password.
* **Passwordless Authentication:** allow users to login simply with their username, an identifier, and an OTP Verification Code (identifier depending on the Authentication Gateway - email or phone number supported with the default gateways).
* **Account Validation:** force users to validate their account by entering an OTP Verification Code at first login, on a set regular basis at login, or at each login.
* **Synchronize OTP identifiers with existing data:** wish to use Twilio SMS, but already have a phone number field saved in database? Perhaps with the "Billing Phone" in WooCommerce? Use this field by indicating its user meta key in the gateway settings (Note: duplicate identifiers will require users to choose a different one on update).
* **Simple yet customizable appearance:** forms used to request OTP Verification Codes use a neutral dedicated style compatible with most themes, with customizable logo and call-to-action colors.
* **Activity Logs:** when enabled, administrators can follow the activity of the enabled gateway. Critical messages regarding gateway malfunction are always logged.
* **Customizable for developers:** developers can add their own gateways or add custom One-Time Password modes using action and filter hooks, and more - see the [developers documentation](https://github.com/froger-me/otp-authenticator).
* **Integration-friendly:** specific integration with Ultimate Member and WooCommerce is included by default ; developers can easily plug into OTP Authenticator with a multitude of functions, filter hooks and action hooks - see the [developers documentation](https://github.com/froger-me/otp-authenticator) - contributions to integrations are welcome.
* **Unlimited features:** there are no premium version feature restrictions shenanigans - OTP Authenticator is fully-featured right out of the box.

== Troubleshooting ==

OTP Authenticator is regularly updated, and bug reports are welcome, preferably on [Github](https://github.com/froger-me/otp-authenticator/issues), especially for advanced troubleshooting.  

Each **bug** report will be addressed in a timely manner, but general inquiries and issues reported on the WordPress forum may take significantly longer to receive a response.  

**Only issues occurring with included integrated plugins (or plugin features), core WordPress and default WordPress themes (incl. WooCommerce Storefront) will be considered without compensation.**  

**Troubleshooting involving 3rd-party plugins or themes will require compensation in any case, and will not be addressed on the WordPress support forum**.

== Integrations ==

Although OTP Authenticator is designed to work out of the box with most combinations of WordPress plugins and themes, there are some edge cases necessitating integration, with code included in the core files of OTP Authenticator executing under certain conditions.  

Integrations added to core are limited to popular plugins and themes: any extra code specific to a handful of installations require a separate custom integration plugin not shared with the community (decision at the discretion of the OTP Authenticator plugin author).  

If such need for plugin integration arises, website administrators may contact the author of OTP Authenticator to become a patron.  

**All integrations are to be funded by plugin users, with downpayment and delivery payment, at the plugin author's discretion, without exception**.  
The patron in return may be credited with their name (or company name) and a link to a page of their choice in the plugin's Changelog.  

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the plugin files to the `/wp-content/plugins/otpa` directory, or install the plugin through the WordPress plugins screen directly for all websites to connect together
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Edit plugin settings - follow on-screen help if necessary

== Screenshots ==
 
1. Two-Factor Authentication Form
2. Passwordless Authentication Form
3. Account Validation Form
4. Set One-Time Password Identifier Form
5. WordPress login with Passwordless Authentication link 
6. OTP Authenticator General Settings
7. OTP Form Style Settings
8. WordPress Email Authentication Gateway Settings
9. Twilio Authentication Gateway Settings
10. Alibaba Cloud SMS Authentication Gateway Settings
11. Activity Logs

== Frequently Asked Questions ==

= Where to find more help? =

More help can be found on <a href="https://wordpress.org/support/plugin/otp-authenticator/">the WordPress support forum</a> for general inquiries and on <a href="https://github.com/froger-me/otp-authenticator">Github</a> for advanced troubleshooting, integration, and feature requests.  

Help is provided without compensation for general enquiries and bug fixes only: feature requests, extra integration or conflict resolution with third-party themes or plugins, and specific setup troubleshooting requests will not be addressed without a fee (transfer method and amount at the discretion of the plugin author).

== Changelog ==

= 1.0 =
* First version