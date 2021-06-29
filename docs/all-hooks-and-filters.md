# Actions
### `cnvrsn_trckng_active_integrations`
**Parameters:** none

This fires inside our static IntegrationManager after initially loading all integrations and checking which are active. This allows us to set up integration interactions (like Google Ads switching Google Analytics to using `gtag` when they're both enabled).

### `cnvrsn_trckng_{$INTEGRATION_ID}_render_settings_first` and `cnvrsn_trckng_{$INTEGRATION_ID}_render_settings_last`
**Parameters:** none

These settings-related actions fire inside an integration's settings card and allow you to insert your own settings fields. `first` fires before the Event checkboxen, and `last` fires after them. Note that we're using the WordPress Settings API, so you'll need to [add_settings_field()](https://developer.wordpress.org/reference/functions/add_settings_field/) here and not just try to output HTML.

### `cnvrsn_trckng_dispatch_event_{$EVENT}`
**Parameter:** `$data` (array)

If you need to do something for an event outside the context of a specific integration (for instance, to just run a function on `purchase` without wiring up a whole custom integration), this is a generic event that passes along the relevant data that's already been pulled.

*Note that events only fire in the first place if there is at least one active integration that has the event in question enabled*. See the [active events filter](#cnvrsn_trckng_active_events) if you need a way around this.

# Filters

## ⚙️ Settings

### `cnvrsn_trckng_default_settings`
**Parameter:** `$defaults` (array)

Maybe you want to override the default settings for some reason? Currently there aren't really default settings to speak of but flexibility is power....

### `cnvrsn_trckng_sanitize_settings`
**Parameters:** `$new_settings` (array), `$submitted_settings` (array)

If you've [added any settings](#cnvrsn_trckng_integration_id_render_settings_first-and-cnvrsn_trckng_integration_id_render_settings_last) you'll probably want to sanitize and sanity-check them here. `$new_settings` is what will be saved to the DB after our cursory processing, and `$submitted_settings` is, well, the submitted settings from the form.

***

## 🎫 Events

### `cnvrsn_trckng_supported_events`
**Parameter:** `$default_events` (array of slugs => labels)

This allows you to [add custom events](adding-a-custom-event.md#1-define-the-event) globally for use by any integation, or conditionally remove events completely if that's your bag.

### `cnvrsn_trckng_{$INTEGRATION_ID}_supported_events`
**Parameter:** `$events` (array of event slugs)

This allows you to [add custom events](adding-a-custom-event.md#2-add-the-event-to-an-integration) to an integration, or conditionally remove events that might be checked in the settings but undesirable in some specific situation.

Regardless of what you add in this single filter, only *globally supported events* (☝️) will actually end up surviving.

### `cnvrsn_trckng_active_events`
**Parameter:** `$enabled_events` (array of slugs => 1)

Typically, active events follow the plugin's settings, and only events that are enabled inside at least one active integration will fire. If you're trying to force an event that isn't attached to an existing integration to fire, you can slide it in here.

***

## 🗂️ Event Data

### `cnvrsn_trckng_deferred_{$INTEGRATION_ID}_{$EVENT}_data`
**Parameter:** `$event_data` (mixed)

The `defer_event()` function allows an integration to capture events between page loads (like `add_to_cart`), and will store an event and its data in the WooCommerce session until the next `wp_footer` comes along for output.

Here you can fiddle with the data before it's stored (and subsequently pulled out for use). Notably, you can return a [falsy value](https://www.php.net/manual/en/language.types.boolean.php#language.types.boolean.casting) to cancel the deferring entirely.

### `cnvrsn_trckng_google-analytics_{$EVENT}_data`
**Parameters:** `$data` (array), `$tracker` (string, `gtag` or `ga`), `$method` (string, probably `event`)

Modify event data before it gets dispatched to the specific function that sends it to GA. This is set up early so as to be the most generic, which is useful for you but tricky to provide specific examples of from here.... (Data object references: [gtag](https://developers.google.com/analytics/devguides/collection/gtagjs/enhanced-ecommerce), [analytics.js](https://developers.google.com/analytics/devguides/collection/analyticsjs/enhanced-ecommerce))

### `cnvrsn_trckng_google-analytics_item_data`
**Parameters:** `$data` (array), `$product` (array from `Events\get_product_data()`), `$item` (WC_Product or WC_Order_Item_Product)

Modify item data before it gets sent to GA. Add `variant` keys, or otherwise mess around. (Product data references: [gtag](https://developers.google.com/analytics/devguides/collection/gtagjs/enhanced-ecommerce#product-data), [analytics.js](https://developers.google.com/analytics/devguides/collection/analyticsjs/enhanced-ecommerce#product-data))

### `cnvrsn_trckng_product_category_line`
**Parameters:** `$category_line` (string), `$product` (WC_Product object), `$categories` (array of category names)

When fetching data about a product, it's useful to have the category. But since a product can have more than one category, how do we flatten them into a single string? By default we're imploding with slashes, but maybe you want to do something different? This should be everything you need to sort yourself out.

### `cnvrsn_trckng_category_name_for_shop_page`
**Parameters:** `$label` (string), `$category` (object from `get_queried_object()`)

When fetching data about the category for the `category_view` event, we're listening for `woocommerce_after_shop_loop` to get any embedded or less-literal category views, so it's possible we don't have a clear category label (for instance, on the Shop page itself). When we don't have an explicit term, this fallback fires and you can modify it to something more to your liking.

***

## 🛤️ Tracking Codes

### `cnvrsn_trckng_google-analytics_use_gtag`
**Parameters:** `$use_gtag` (boolean)

Force the Google Analytics integration to use `gtag` instead of the default `analytics.js`.

### `cnvrsn_trckng_inline_scripts_{$INTEGRATION_ID}`
**Parameter:** `$output` (string, the output of the integration's `enqueue_script()` call)

Want to modify or completely mute an integration's base setup? Go for it.