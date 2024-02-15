# OTP Authenticator - One-Time Password Authentication for WordPress

* [General Description](#user-content-general-description)
    * [Overview](#user-content-overview)
    * [Adding an Authentication Gateway](#user-content-adding-an-authentication-gateway)
* [Hooks - actions & filters](#user-content-hooks---actions--filters)
    * [Actions](#user-content-actions)
    * [Filters](#user-content-filters)

## General Description

Add Two-Factor Authentication, Passwordless Authentication and Account Validation to your WordPress website.

### Overview

This plugin adds the following major features to WordPress:

* **3 One-Time Password modes**: Two-Factor Authentication, Passwordless Authentication and Account Validation.
* **3 Authentication Gateways for OTP Verification Codes:** WordPress Email, Twilio SMS, Alibaba Cloud SMS, TunisieSMS ; all with option to use a sandbox mode.
* **Two-Factor Authentication:** allow (or force) users to authenticate with a second factor on top of their password.
* **Passwordless Authentication:** allow users to login simply with their username, an identifier, and an OTP Verification Code (identifier depending on the Authentication Gateway - email or phone number supported with the default gateways).
* **Account Validation:** force users to validate their account by entering an OTP Verification Code at first login, on a set regular basis at login, or at each login.
* **Synchronize OTP identifiers with existing data:** wish to use Twilio SMS, but already have a phone number field saved in database? Perhaps with the "Billing Phone" in WooCommerce? Use this field by indicating its user meta key in the gateway settings (Note: duplicate identifiers will require users to choose a different one on update).
* **Simple yet customizable appearance:** forms used to request OTP Verification Codes use a neutral dedicated style compatible with most themes, with customizable logo and call-to-action colors.
* **Activity Logs:** when enabled, administrators can follow the activity of the enabled gateway. Critical messages regarding gateway malfunction are always logged.
* **Customizable for developers:** developers can add their own gateways or add custom One-Time Password modes using action and filter hooks, and more - see the [developers documentation](https://github.com/froger-me/otp-authenticator).
* **Integration-friendly:** specific integration with Ultimate Member and WooCommerce is included by default ; developers can easily plug into OTP Authenticator with a multitude of functions, filter hooks and action hooks - see the [developers documentation](https://github.com/froger-me/otp-authenticator) - contributions to integrations are welcome.
* **Unlimited features:** there are no premium version feature restrictions shenanigans - OTP Authenticator is fully-featured right out of the box.

___

## Adding an Authentication Gateway

** COMING SOON - WORK IN PROGRESS **

Developers can extend the plugin and add their own Authentication Gateway by using a few filter and action hooks as well as a class inheriting `Otpa_Abstract_Gateway`.  
Below is a simple example of implementation of a `Custom` Authentication Gateway sending OTPs to the Activity logs (forced even if not enabled).  

### Implementing filter and actions hooks and including a custom Authentication Gateway class

** COMING SOON - WORK IN PROGRESS **

In this example, we are first creating a simple plugin to implement the action and filter hooks, and include the Authentication Gateway class.

```php
<?php
/*
Plugin Name: Example of Authentication Gateway for OTP Authenticator
Version: 1.0
Text Domain: my-domain
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! defined( 'OTPA_CUSTOM_GATEWAY_PLUGIN_PATH' ) ) {
    define( 'OTPA_CUSTOM_GATEWAY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'OTPA_CUSTOM_GATEWAY_PLUGIN_URL' ) ) {
    define( 'OTPA_CUSTOM_GATEWAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// COMING SOON

```

### Implementing a custom Authentication Gateway class - example
The custom Authentication Gateway's logic is then implemented in the file `class-otpa-custom-gateway.php` included by the plugin.

```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Otpa_Custom_Gateway extends Otpa_Abstract_Gateway {

    /*******************************************************************
     * Public methods
     *******************************************************************/

    // COMING SOON 

    /*******************************************************************
     * Protected methods
     *******************************************************************/

    // COMING SOON
}
```
___

## Hooks - actions & filters

** COMING SOON - WORK IN PROGRESS **

OTP Authenticator gives developers the possibilty to customise its behavior with a series of custom actions and filters. 

### Actions

** COMING SOON - WORK IN PROGRESS **

```
do_action( 'otpa_api_' . $action );
do_action( 'otpa_page_' . $action );
```

Actions index:
* [otpa_init](#user-content-otpa_init)
* [otpa_loaded](#user-content-otpa_loaded)
* [otpa_ready](#user-content-otpa_ready)
* [otpa_invalid_settings](#user-content-otpa_invalid_settings)
* [otpa_wp_error](#user-content-otpa_wp_error)
* [otpa_api_](#user-content-otpa_api_)
* [otpa_page_](#user-content-otpa_page_)
* [otpa_otp_form_scripts](#user-content-otpa_otp_form_scripts)
* [otpa_otp_code_requested](#user-content-otpa_otp_code_requested)
* [otpa_otp_code_saved](#user-content-otpa_otp_code_saved)
* [otpa_otp_code_verified](#user-content-otpa_otp_code_verified)
* [otpa_otp_blocked](#user-content-otpa_otp_blocked)
* [otpa_before_set_otp_form](#user-content-otpa_before_set_otp_form)
* [otpa_before_set_otp_input](#user-content-otpa_before_set_otp_input)
* [otpa_after_set_otp_input](#user-content-otpa_after_set_otp_input)
* [otpa_before_set_otp_submit_button](#user-content-otpa_before_set_otp_submit_button)
* [otpa_after_set_otp_submit_button](#user-content-otpa_after_set_otp_submit_button)
* [otpa_before_set_otp_footer_message](#user-content-otpa_before_set_otp_footer_message)
* [otpa_after_set_otp_footer_message](#user-content-otpa_after_set_otp_footer_message)
* [otpa_after_set_otp_form](#user-content-otpa_after_set_otp_form)
* [otpa_after_otp_identifier_field](#user-content-otpa_after_otp_identifier_field)
* [otpa_before_otp_form](#user-content-otpa_before_otp_form)
* [otpa_before_otp_widget](#user-content-otpa_before_otp_widget)
* [otpa_after_otp_widget](#user-content-otpa_after_otp_widget)
* [otpa_before_otp_submit_button](#user-content-otpa_before_otp_submit_button)
* [otpa_after_otp_submit_button](#user-content-otpa_after_otp_submit_button)
* [otpa_before_otp_footer_message](#user-content-otpa_before_otp_footer_message)
* [otpa_after_otp_footer_message](#user-content-otpa_after_otp_footer_message)
* [otpa_after_otp_form](#user-content-otpa_after_otp_form)
* [otpa_style_settings_page](#user-content-otpa_style_settings_page)
* [otpa_style_settings_tab](#user-content-otpa_style_settings_tab)
* [otpa_settings_page](#user-content-otpa_settings_page)
* [otpa_logs_settings_page](#user-content-otpa_logs_settings_page)
* [otpa_logs_settings_tab](#user-content-otpa_logs_settings_tab)
* [otpa_gateway_settings_page](#user-content-otpa_gateway_settings_page)
* [otpa_gateway_settings_tab](#user-content-otpa_gateway_settings_tab)
* [otpa_before_tabs_settings](#user-content-otpa_before_tabs_settings)
* [otpa_after_tabs_settings](#user-content-otpa_after_tabs_settings)
* [otpa_before_style_tab_settings](#user-content-otpa_before_style_tab_settings)
* [otpa_after_style_tab_settings](#user-content-otpa_after_style_tab_settings)
* [otpa_before_logs_tab_settings](#user-content-otpa_before_logs_tab_settings)
* [otpa_after_logs_tab_settings](#user-content-otpa_after_logs_tab_settings)
* [otpa_before_gateway_tab_settings](#user-content-otpa_before_gateway_tab_settings)
* [otpa_after_gateway_tab_settings](#user-content-otpa_after_gateway_tab_settings)
* [otpa_before_main_tab_settings](#user-content-otpa_before_main_tab_settings)
* [otpa_after_main_tab_settings](#user-content-otpa_after_main_tab_settings)
* [otpa_before_settings](#user-content-otpa_before_settings)
* [otpa_before_main_settings](#user-content-otpa_before_main_settings)
* [otpa_before_main_settings_inner](#user-content-otpa_before_main_settings_inner)
* [otpa_after_main_settings_inner](#user-content-otpa_after_main_settings_inner)
* [otpa_after_main_settings](#user-content-otpa_after_main_settings)
* [otpa_after_settings](#user-content-otpa_after_settings)
* [otpa_before_style_settings](#user-content-otpa_before_style_settings)
* [otpa_before_style_settings_inner](#user-content-otpa_before_style_settings_inner)
* [otpa_after_style_settings_inner](#user-content-otpa_after_style_settings_inner)
* [otpa_after_style_settings](#user-content-otpa_after_style_settings)
* [otpa_before_logs_settings](#user-content-otpa_before_logs_settings)
* [otpa_before_logs_settings_inner](#user-content-otpa_before_logs_settings_inner)
* [otpa_after_logs_settings_inner](#user-content-otpa_after_logs_settings_inner)
* [otpa_after_logs_settings](#user-content-otpa_after_logs_settings)
* [otpa_before_gateway_settings](#user-content-otpa_before_gateway_settings)
* [otpa_before_gateway_settings_inner](#user-content-otpa_before_gateway_settings_inner)
* [otpa_after_gateway_settings_inner](#user-content-otpa_after_gateway_settings_inner)
* [otpa_after_gateway_settings](#user-content-otpa_after_gateway_settings)
* [otpa_identifier_updated](#user-content-otpa_identifier_updated)
* [otpa_integration](#user-content-otpa_integration)
* [otpa_integration_run](#user-content-otpa_integration_run)

___

#### otpa_init

```php
do_action( 'otpa_init' );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_loaded

```php
do_action( 'otpa_loaded', $otpa_objects );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_ready

```php
do_action( 'otpa_ready', $otpa_objects );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_invalid_settings

```php
do_action( 'otpa_invalid_settings', $otpa_objects );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_wp_error

```php
do_action( 'otpa_wp_error', $wp_error );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_api_

```php
do_action( 'otpa_api_' . $action );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_page_

```php
do_action( 'otpa_page_' . $action );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_otp_form_scripts

```php
do_action( 'otpa_otp_form_scripts' );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_otp_code_requested

```php
do_action( 'otpa_otp_code_requested', $user_id );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_otp_code_saved

```php
do_action( 'otpa_otp_code_saved', $user_id );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_otp_code_verified

```php
do_action( 'otpa_otp_code_verified', $user_id );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_otp_blocked

```php
do_action( 'otpa_otp_blocked', $user_id, $block_expiry );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_set_otp_form

```php
do_action( 'otpa_before_set_otp_form', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_set_otp_input

```php
do_action( 'otpa_before_set_otp_input', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_set_otp_input

```php
do_action( 'otpa_after_set_otp_input', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_set_otp_submit_button

```php
do_action( 'otpa_before_set_otp_submit_button', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_set_otp_submit_button

```php
do_action( 'otpa_after_set_otp_submit_button', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_set_otp_footer_message

```php
do_action( 'otpa_before_set_otp_footer_message', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_set_otp_footer_message

```php
do_action( 'otpa_after_set_otp_footer_message', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_set_otp_form

```php
do_action( 'otpa_after_set_otp_form', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_otp_identifier_field

```php
do_action( 'otpa_after_otp_identifier_field', $user_id, $identifier );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_otp_form

```php
do_action( 'otpa_before_otp_form', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_otp_widget

```php
do_action( 'otpa_before_otp_widget', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_otp_widget

```php
do_action( 'otpa_after_otp_widget', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_otp_submit_button

```php
do_action( 'otpa_before_otp_submit_button', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_otp_submit_button

```php
do_action( 'otpa_after_otp_submit_button', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_otp_footer_message

```php
do_action( 'otpa_before_otp_footer_message', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_otp_footer_message

```php
do_action( 'otpa_after_otp_footer_message', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_otp_form

```php
do_action( 'otpa_after_otp_form', $otp_form_type );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_style_settings_page

```php
do_action( 'otpa_style_settings_page', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_style_settings_tab

```php
do_action( 'otpa_style_settings_tab', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_settings_page

```php
do_action( 'otpa_settings_page', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_logs_settings_page

```php
do_action( 'otpa_logs_settings_page', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_logs_settings_tab

```php
do_action( 'otpa_logs_settings_tab', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_gateway_settings_page

```php
do_action( 'otpa_gateway_settings_page', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_gateway_settings_tab

```php
do_action( 'otpa_gateway_settings_tab', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_tabs_settings

```php
do_action( 'otpa_before_tabs_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_tabs_settings

```php
do_action( 'otpa_after_tabs_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_style_tab_settings

```php
do_action( 'otpa_before_style_tab_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_style_tab_settings

```php
do_action( 'otpa_after_style_tab_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_logs_tab_settings

```php
do_action( 'otpa_before_logs_tab_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_logs_tab_settings

```php
do_action( 'otpa_after_logs_tab_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_gateway_tab_settings

```php
do_action( 'otpa_before_gateway_tab_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_gateway_tab_settings

```php
do_action( 'otpa_after_gateway_tab_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_main_tab_settings

```php
do_action( 'otpa_before_main_tab_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_main_tab_settings

```php
do_action( 'otpa_after_main_tab_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_settings

```php
do_action( 'otpa_before_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_main_settings

```php
do_action( 'otpa_before_main_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_main_settings_inner

```php
do_action( 'otpa_before_main_settings_inner' );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_main_settings_inner

```php
do_action( 'otpa_after_main_settings_inner' );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_main_settings

```php
do_action( 'otpa_after_main_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_settings

```php
do_action( 'otpa_after_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_style_settings

```php
do_action( 'otpa_before_style_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_style_settings_inner

```php
do_action( 'otpa_before_style_settings_inner', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_style_settings_inner

```php
do_action( 'otpa_after_style_settings_inner', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_style_settings

```php
do_action( 'otpa_after_style_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_logs_settings

```php
do_action( 'otpa_before_logs_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_logs_settings_inner

```php
do_action( 'otpa_before_logs_settings_inner', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_logs_settings_inner

```php
do_action( 'otpa_after_logs_settings_inner', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_logs_settings

```php
do_action( 'otpa_after_logs_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_gateway_settings

```php
do_action( 'otpa_before_gateway_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_before_gateway_settings_inner

```php
do_action( 'otpa_before_gateway_settings_inner', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_gateway_settings_inner

```php
do_action( 'otpa_after_gateway_settings_inner', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_after_gateway_settings

```php
do_action( 'otpa_after_gateway_settings', $active_tab );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_identifier_updated

```php
do_action( 'otpa_identifier_updated', $user_id, $identifier, $old_identifier );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_integration

```php
do_action( 'otpa_integration', $integration, $slug );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

#### otpa_integration_run

```php
do_action( 'otpa_integration_run', $integration );
```

**Description**  
Fired after sometime.  

**Parameters**  
$var1
> (type) Description.  

$var2
> (type) Description.   

___

### Filters

** COMING SOON - WORK IN PROGRESS **

```
apply_filters( 'otpa_template_style-settings-page', $path );
apply_filters( 'otpa_template_style-settings-tab', $path );
apply_filters( 'otpa_sanitize_style_settings', $settings );
apply_filters( 'otpa_template_main-settings-page', $path );
apply_filters( 'otpa_template_log-row', $path );
apply_filters( 'otpa_template_logs-settings-page', $path );
apply_filters( 'otpa_template_logs-settings-tab', $path );
apply_filters( 'otpa_settings_fields_' . $this->get_gateway_id(), settings_fields );
apply_filters( $gateway_id . '_sanitize_settings', $settings );
apply_filters( $gateway_id . '_settings', $settings );
apply_filters( $gateway_id . '_option', $value, $key );
apply_filters( 'otpa_template_gateway-settings-page', $path );
apply_filters( 'otpa_template_gateway-settings-tab', $path );
```

Filters index:

* [otpa_api_otp_handlers](#user-content-otpa_api_otp_handlers)
* [otpa_wp_error_message](#user-content-otpa_wp_error_message)
* [otpa_api_endpoints](#user-content-otpa_api_endpoints)
* [otpa_page_endpoints](#user-content-otpa_page_endpoints)
* [otpa_otp_api_valid_callback](#user-content-otpa_otp_api_valid_callback)
* [otpa_otp_api_callback](#user-content-otpa_otp_api_callback)
* [otpa_otp_api_callback_args](#user-content-otpa_otp_api_callback_args)
* [otpa_api_error_data](#user-content-otpa_api_error_data)
* [otpa_otp_form_vars](#user-content-otpa_otp_form_vars)
* [otpa_otp_identifier_field_label](#user-content-otpa_otp_identifier_field_label)
* [otpa_set_otp_identifier_form_vars](#user-content-otpa_set_otp_identifier_form_vars)
* [otpa_debug](#user-content-otpa_debug)
* [otpa_otp_form_scripts_params](#user-content-otpa_otp_form_scripts_params)
* [otpa_otp_form_inline_style](#user-content-otpa_otp_form_inline_style)
* [otpa_profile_info_title](#user-content-otpa_profile_info_title)
* [otpa_profile_js_parameters](#user-content-otpa_profile_js_parameters)
* [otpa_style_settings](#user-content-otpa_style_settings)
* [otpa_style_option](#user-content-otpa_style_option)
* [otpa_template_style](#user-content-otpa_template_style)
* [otpa_template_style](#user-content-otpa_template_style)
* [otpa_sanitize_style_settings](#user-content-otpa_sanitize_style_settings)
* [otpa_style_settings_fields](#user-content-otpa_style_settings_fields)
* [otpa_settings](#user-content-otpa_settings)
* [otpa_option](#user-content-otpa_option)
* [otpa_default_gateway_id](#user-content-otpa_default_gateway_id)
* [otpa_settings_valid](#user-content-otpa_settings_valid)
* [otpa_settings_js_parameters](#user-content-otpa_settings_js_parameters)
* [otpa_template_main](#user-content-otpa_template_main)
* [otpa_sanitize_settings](#user-content-otpa_sanitize_settings)
* [otpa_settings_fields](#user-content-otpa_settings_fields)
* [otpa_passwordless_auth_link_text](#user-content-otpa_passwordless_auth_link_text)
* [otpa_template_log](#user-content-otpa_template_log)
* [otpa_template_logs](#user-content-otpa_template_logs)
* [otpa_template_logs](#user-content-otpa_template_logs)
* [otpa_api_2fa_handlers](#user-content-otpa_api_2fa_handlers)
* [otpa_2fa_api_valid_callback](#user-content-otpa_2fa_api_valid_callback)
* [otpa_2fa_api_callback](#user-content-otpa_2fa_api_callback)
* [otpa_2fa_api_callback_args](#user-content-otpa_2fa_api_callback_args)
* [otpa_user_2fa_switch_scripts_params](#user-content-otpa_user_2fa_switch_scripts_params)
* [otpa_2fa_button_switch_markup](#user-content-otpa_2fa_button_switch_markup)
* [otpa_settings_fields_](#user-content-otpa_settings_fields_)
* [gateway_id](#user-content-gateway_id)
* [gateway_id](#user-content-gateway_id)
* [gateway_id](#user-content-gateway_id)
* [otpa_template_gateway](#user-content-otpa_template_gateway)
* [otpa_template_gateway](#user-content-otpa_template_gateway)
* [otpa_otp_widget_identifier_placeholder](#user-content-otpa_otp_widget_identifier_placeholder)
* [otpa_otp_widget_code_placeholder](#user-content-otpa_otp_widget_code_placeholder)
* [otpa_2fa_deny_rest]( #user-content-otpa_2fa_deny_rest)
* [otpa_account_validation_deny_rest]( #user-content-otpa_account_validation_deny_rest)


___

#### otpa_api_otp_handlers

```php
apply_filters( 'otpa_api_otp_handlers', $authorized_handlers );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_wp_error_message

```php
apply_filters( 'otpa_wp_error_message', $message, $code, $data );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_api_endpoints

```php
apply_filters( 'otpa_api_endpoints', $authorised_api_endpoints );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_page_endpoints

```php
apply_filters( 'otpa_page_endpoints', $authorised_page_endpoints );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_otp_api_valid_callback

```php
apply_filters( 'otpa_otp_api_valid_callback', $valid, $handler, $otp_type );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_otp_api_callback

```php
apply_filters( 'otpa_otp_api_callback', $callback, $handler, $otp_type );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_otp_api_callback_args

```php
apply_filters( 'otpa_otp_api_callback_args', $payload, $handler, $otp_type );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_api_error_data

```php
apply_filters( 'otpa_api_error_data', $data, $code );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_otp_form_vars

```php
apply_filters( 'otpa_otp_form_vars', $vars );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_otp_identifier_field_label

```php
apply_filters( 'otpa_otp_identifier_field_label', $field_label ); // Default 'OTP Identifier'

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_set_otp_identifier_form_vars

```php
apply_filters( 'otpa_set_otp_identifier_form_vars', $vars );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_debug

```php
apply_filters( 'otpa_debug', $debug ); // Default (bool) ( constant( 'WP_DEBUG' ) ) 

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_otp_form_scripts_params

```php
apply_filters( 'otpa_otp_form_scripts_params', $params );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_otp_form_inline_style

```php
apply_filters( 'otpa_otp_form_inline_style', $minified_styles, $styles );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_profile_info_title

```php
apply_filters( 'otpa_profile_info_title', $title ); // Default __( 'OTP Authenticator', 'otpa' )

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_profile_js_parameters

```php
apply_filters( 'otpa_profile_js_parameters', $params );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_style_settings

```php
apply_filters( 'otpa_style_settings', style-settings );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_style_option

```php
apply_filters( 'otpa_style_option', $value, $key );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_template_style

```php
apply_filters( 'otpa_template_style-settings-page', $path );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_template_style

```php
apply_filters( 'otpa_template_style-settings-tab', $path );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_sanitize_style_settings

```php
apply_filters( 'otpa_sanitize_style_settings', $settings );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_style_settings_fields

```php
apply_filters( 'otpa_style_settings_fields', $settings_fields );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_settings

```php
apply_filters( 'otpa_settings', $settings );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_option

```php
apply_filters( 'otpa_option', $value, $key );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_default_gateway_id

```php
apply_filters( 'otpa_default_gateway_id', $default_gateway_id );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_settings_valid

```php
apply_filters( 'otpa_settings_valid', $valid, $settings );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_settings_js_parameters

```php
apply_filters( 'otpa_settings_js_parameters', $params );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_template_main

```php
apply_filters( 'otpa_template_main-settings-page', $path );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_sanitize_settings

```php
apply_filters( 'otpa_sanitize_settings', $settings );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_settings_fields

```php
apply_filters( 'otpa_settings_fields', $settings_fields );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_passwordless_auth_link_text

```php
apply_filters( 'otpa_passwordless_auth_link_text', $link_text ); //Default __( 'Passwordless Authentication', 'otpa' )

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_template_log

```php
apply_filters( 'otpa_template_log-row', $path );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_template_logs

```php
apply_filters( 'otpa_template_logs-settings-page', $path );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_template_logs

```php
apply_filters( 'otpa_template_logs-settings-tab', $path );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_api_2fa_handlers

```php
apply_filters( 'otpa_api_2fa_handlers', $authorized_api_handlers );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_2fa_api_valid_callback

```php
apply_filters( 'otpa_2fa_api_valid_callback', $valid, $handler );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_2fa_api_callback

```php
apply_filters( 'otpa_2fa_api_callback', $callback, $handler );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_2fa_api_callback_args

```php
apply_filters( 'otpa_2fa_api_callback_args', $payload, $handler );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_user_2fa_switch_scripts_params

```php
apply_filters( 'otpa_user_2fa_switch_scripts_params', $params );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_2fa_button_switch_markup

```php
apply_filters( 'otpa_2fa_button_switch_markup', $output, $label, $on_text, $off_text, $class, $nonce );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_settings_fields_

```php
apply_filters( 'otpa_settings_fields_' . $this->get_gateway_id(), settings_fields );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### gateway_id

```php
apply_filters( $gateway_id . '_sanitize_settings', $settings );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### gateway_id

```php
apply_filters( $gateway_id . '_settings', $settings );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### gateway_id

```php
apply_filters( $gateway_id . '_option', $value, $key );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_template_gateway

```php
apply_filters( 'otpa_template_gateway-settings-page', $path );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_template_gateway

```php
apply_filters( 'otpa_template_gateway-settings-tab', $path );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_otp_widget_identifier_placeholder

```php
apply_filters( 'otpa_otp_widget_identifier_placeholder', $identifier_placeholder ); // Default: __( 'Identifier', 'otpa' )

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_otp_widget_code_placeholder

```php
apply_filters( 'otpa_otp_widget_code_placeholder', $code_placeholder ); // Default: __( 'Code', 'otpa' )

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_2fa_deny_rest

```php
apply_filters( 'otpa_2fa_deny_rest', $deny, $wp_http_response, $wp_rest_server, $wp_rest_request );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___

#### otpa_account_validation_deny_rest

```php
apply_filters( 'otpa_account_validation_deny_rest', $deny, $wp_http_response, $wp_rest_server, $wp_rest_request );

```
**Description**  
Filter var1.  

**Parameters**  
$var
> (type) Description.  

$var
> (type) Description.  
 
___
