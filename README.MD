# OTP Authenticator - One-Time Password Authentication for WordPress

* [OTP Authenticator - One-Time Password Authentication for WordPress](#otp-authenticator---one-time-password-authentication-for-wordpress)
    * [General Description](#general-description)
        * [Overview](#overview)
    * [Adding an Authentication Gateway](#adding-an-authentication-gateway)
        * [Implementing filter and actions hooks and including a custom Authentication Gateway class](#implementing-filter-and-actions-hooks-and-including-a-custom-authentication-gateway-class)
        * [Implementing a custom Authentication Gateway class - example](#implementing-a-custom-authentication-gateway-class---example)
    * [Hooks - actions \& filters](#hooks---actions--filters)
        * [Actions](#actions)
            * [otpa\_init](#otpa_init)
            * [otpa\_loaded](#otpa_loaded)
            * [otpa\_ready](#otpa_ready)
            * [otpa\_invalid\_settings](#otpa_invalid_settings)
            * [otpa\_wp\_error](#otpa_wp_error)
            * [otpa\_api\_](#otpa_api_)
            * [otpa\_page\_](#otpa_page_)
            * [otpa\_otp\_form\_scripts](#otpa_otp_form_scripts)
            * [otpa\_otp\_code\_requested](#otpa_otp_code_requested)
            * [otpa\_otp\_code\_saved](#otpa_otp_code_saved)
            * [otpa\_otp\_code\_verified](#otpa_otp_code_verified)
            * [otpa\_otp\_blocked](#otpa_otp_blocked)
            * [otpa\_before\_set\_otp\_form](#otpa_before_set_otp_form)
            * [otpa\_before\_set\_otp\_input](#otpa_before_set_otp_input)
            * [otpa\_after\_set\_otp\_input](#otpa_after_set_otp_input)
            * [otpa\_before\_set\_otp\_submit\_button](#otpa_before_set_otp_submit_button)
            * [otpa\_after\_set\_otp\_submit\_button](#otpa_after_set_otp_submit_button)
            * [otpa\_before\_set\_otp\_footer\_message](#otpa_before_set_otp_footer_message)
            * [otpa\_after\_set\_otp\_footer\_message](#otpa_after_set_otp_footer_message)
            * [otpa\_after\_set\_otp\_form](#otpa_after_set_otp_form)
            * [otpa\_after\_otp\_identifier\_field](#otpa_after_otp_identifier_field)
            * [otpa\_before\_otp\_form](#otpa_before_otp_form)
            * [otpa\_before\_otp\_widget](#otpa_before_otp_widget)
            * [otpa\_after\_otp\_widget](#otpa_after_otp_widget)
            * [otpa\_before\_otp\_submit\_button](#otpa_before_otp_submit_button)
            * [otpa\_after\_otp\_submit\_button](#otpa_after_otp_submit_button)
            * [otpa\_before\_otp\_footer\_message](#otpa_before_otp_footer_message)
            * [otpa\_after\_otp\_footer\_message](#otpa_after_otp_footer_message)
            * [otpa\_after\_otp\_form](#otpa_after_otp_form)
            * [otpa\_style\_settings\_page](#otpa_style_settings_page)
            * [otpa\_style\_settings\_tab](#otpa_style_settings_tab)
            * [otpa\_settings\_page](#otpa_settings_page)
            * [otpa\_logs\_settings\_page](#otpa_logs_settings_page)
            * [otpa\_logs\_settings\_tab](#otpa_logs_settings_tab)
            * [otpa\_gateway\_settings\_page](#otpa_gateway_settings_page)
            * [otpa\_gateway\_settings\_tab](#otpa_gateway_settings_tab)
            * [otpa\_before\_tabs\_settings](#otpa_before_tabs_settings)
            * [otpa\_after\_tabs\_settings](#otpa_after_tabs_settings)
            * [otpa\_before\_style\_tab\_settings](#otpa_before_style_tab_settings)
            * [otpa\_after\_style\_tab\_settings](#otpa_after_style_tab_settings)
            * [otpa\_before\_logs\_tab\_settings](#otpa_before_logs_tab_settings)
            * [otpa\_after\_logs\_tab\_settings](#otpa_after_logs_tab_settings)
            * [otpa\_before\_gateway\_tab\_settings](#otpa_before_gateway_tab_settings)
            * [otpa\_after\_gateway\_tab\_settings](#otpa_after_gateway_tab_settings)
            * [otpa\_before\_main\_tab\_settings](#otpa_before_main_tab_settings)
            * [otpa\_after\_main\_tab\_settings](#otpa_after_main_tab_settings)
            * [otpa\_before\_settings](#otpa_before_settings)
            * [otpa\_before\_main\_settings](#otpa_before_main_settings)
            * [otpa\_before\_main\_settings\_inner](#otpa_before_main_settings_inner)
            * [otpa\_after\_main\_settings\_inner](#otpa_after_main_settings_inner)
            * [otpa\_after\_main\_settings](#otpa_after_main_settings)
            * [otpa\_after\_settings](#otpa_after_settings)
            * [otpa\_before\_style\_settings](#otpa_before_style_settings)
            * [otpa\_before\_style\_settings\_inner](#otpa_before_style_settings_inner)
            * [otpa\_after\_style\_settings\_inner](#otpa_after_style_settings_inner)
            * [otpa\_after\_style\_settings](#otpa_after_style_settings)
            * [otpa\_before\_logs\_settings](#otpa_before_logs_settings)
            * [otpa\_before\_logs\_settings\_inner](#otpa_before_logs_settings_inner)
            * [otpa\_after\_logs\_settings\_inner](#otpa_after_logs_settings_inner)
            * [otpa\_after\_logs\_settings](#otpa_after_logs_settings)
            * [otpa\_before\_gateway\_settings](#otpa_before_gateway_settings)
            * [otpa\_before\_gateway\_settings\_inner](#otpa_before_gateway_settings_inner)
            * [otpa\_after\_gateway\_settings\_inner](#otpa_after_gateway_settings_inner)
            * [otpa\_after\_gateway\_settings](#otpa_after_gateway_settings)
            * [otpa\_identifier\_updated](#otpa_identifier_updated)
            * [otpa\_integration](#otpa_integration)
            * [otpa\_integration\_run](#otpa_integration_run)
        * [Filters](#filters)
            * [otpa\_api\_otp\_handlers](#otpa_api_otp_handlers)
            * [otpa\_wp\_error\_message](#otpa_wp_error_message)
            * [otpa\_api\_endpoints](#otpa_api_endpoints)
            * [otpa\_page\_endpoints](#otpa_page_endpoints)
            * [otpa\_otp\_api\_valid\_callback](#otpa_otp_api_valid_callback)
            * [otpa\_otp\_api\_callback](#otpa_otp_api_callback)
            * [otpa\_otp\_api\_callback\_args](#otpa_otp_api_callback_args)
            * [otpa\_api\_error\_data](#otpa_api_error_data)
            * [otpa\_otp\_form\_vars](#otpa_otp_form_vars)
            * [otpa\_otp\_identifier\_field\_label](#otpa_otp_identifier_field_label)
            * [otpa\_set\_otp\_identifier\_form\_vars](#otpa_set_otp_identifier_form_vars)
            * [otpa\_debug](#otpa_debug)
            * [otpa\_otp\_form\_scripts\_params](#otpa_otp_form_scripts_params)
            * [otpa\_otp\_form\_inline\_style](#otpa_otp_form_inline_style)
            * [otpa\_profile\_info\_title](#otpa_profile_info_title)
            * [otpa\_profile\_js\_parameters](#otpa_profile_js_parameters)
            * [otpa\_style\_settings](#otpa_style_settings)
            * [otpa\_style\_option](#otpa_style_option)
            * [otpa\_template\_style](#otpa_template_style)
            * [otpa\_template\_style](#otpa_template_style-1)
            * [otpa\_sanitize\_style\_settings](#otpa_sanitize_style_settings)
            * [otpa\_style\_settings\_fields](#otpa_style_settings_fields)
            * [otpa\_settings](#otpa_settings)
            * [otpa\_option](#otpa_option)
            * [otpa\_default\_gateway\_id](#otpa_default_gateway_id)
            * [otpa\_settings\_valid](#otpa_settings_valid)
            * [otpa\_settings\_js\_parameters](#otpa_settings_js_parameters)
            * [otpa\_template\_main](#otpa_template_main)
            * [otpa\_sanitize\_settings](#otpa_sanitize_settings)
            * [otpa\_settings\_fields](#otpa_settings_fields)
            * [otpa\_passwordless\_auth\_link\_text](#otpa_passwordless_auth_link_text)
            * [otpa\_template\_log](#otpa_template_log)
            * [otpa\_template\_logs](#otpa_template_logs)
            * [otpa\_template\_logs](#otpa_template_logs-1)
            * [otpa\_api\_2fa\_handlers](#otpa_api_2fa_handlers)
            * [otpa\_2fa\_api\_valid\_callback](#otpa_2fa_api_valid_callback)
            * [otpa\_2fa\_api\_callback](#otpa_2fa_api_callback)
            * [otpa\_2fa\_api\_callback\_args](#otpa_2fa_api_callback_args)
            * [otpa\_user\_2fa\_switch\_scripts\_params](#otpa_user_2fa_switch_scripts_params)
            * [otpa\_2fa\_button\_switch\_markup](#otpa_2fa_button_switch_markup)
            * [otpa\_settings\_fields\_](#otpa_settings_fields_)
            * [gateway\_id](#gateway_id)
            * [gateway\_id](#gateway_id-1)
            * [gateway\_id](#gateway_id-2)
            * [otpa\_template\_gateway](#otpa_template_gateway)
            * [otpa\_template\_gateway](#otpa_template_gateway-1)
            * [otpa\_otp\_widget\_identifier\_placeholder](#otpa_otp_widget_identifier_placeholder)
            * [otpa\_otp\_widget\_code\_placeholder](#otpa_otp_widget_code_placeholder)
            * [otpa\_2fa\_deny\_rest](#otpa_2fa_deny_rest)
            * [otpa\_account\_validation\_deny\_rest](#otpa_account_validation_deny_rest)

## General Description

Add Two-Factor Authentication, Passwordless Authentication and Account Validation to your WordPress website.

### Overview

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

OTP Authenticator gives developers the possibilty to customise its behavior with a series of custom actions and filters. 

### Actions

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
