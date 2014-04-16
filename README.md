Gravity Forms Social Profiles Validator
=======================================

A WordPress plugin. Requires [Graivty Forms](http://katz.si/gf).

Validate social profile accounts by specifying a CSS Class Name for fields.

### Usage

In a field in Gravity Forms, click the Advanced tab, then enter in the CSS Class Name `validate-twitter` or `validate-facebook`.

Currently, Twitter validates username using regex to match the right pattern.

Facebook accepts handles and full URLs, then checks the username/page ID against https://graph.facebook.com/{handle/id}

Facebook verification requests are cached for a week.

### Filters

* `kws_gf_is_valid_{$key}` - Hook into this filter to validate different social networks defined in `kws_gf_validate_social_accounts`. Returns boolean. `kws_gf_is_valid_twitter` and `kws_gf_is_valid_facebook` are set by the plugin already, but you can also hook in.
* `kws_gf_validate_social_accounts` Array of social networks to check. Lowercase.
* `kws_gf_validate_social_invalid_message` Array of error message strings for the social network. Set key to be the same as the `kws_gf_validate_social_accounts` key. If there's an error message specified in the field, it will be used instead.
