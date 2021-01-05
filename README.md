WooCommerce Cnvrsn Trckng
====================

Forked from the very excellent [WooCommerce Conversion Tracking](https://github.com/tareq1988/woocommerce-conversion-tracking) plugin by [Tareq Hasan](http://tareq.weDevs.com).

This version started out by just removing a bunch of stuff (denoted by also removing most of the vowels in the name), followed by some opinionated changes and pretty massive restructuring. Essentially nothing is left over now from the original except some of the core ideas. It should be lighter and more efficient, much more extensible, and now includes all of [Google Analytics' enhanced ecommerce](https://developers.google.com/analytics/devguides/collection/analyticsjs/enhanced-ecommerce) events and funnels.

While everything should work nicely and very easily out of the box for anyone, the plugin was developed for picky and opinionated coders. Notably, everything works without jQuery and performance concerns overrule dogmatic marketing practices.

# Integrations

### Google Analytics

This has been the priority, and so is the most featureful integration at the moment. Automatically supported events are:
* Category View
* Product View
* Add to Cart
* Remove from Cart
* Start Checkout
* Successful Purchase

Despite Google's docs, the older `analytics.js` is better than using `gtag` for the case of only running GA (see: [here](https://github.com/googleanalytics/autotrack/issues/202#issuecomment-333744194) and [here](https://github.com/GoogleChrome/lighthouse/issues/10783)) so we've made the opinionated choice to run with that, but you can force `gtag` with: `add_filter( 'cnvrsn_trckng_google-analytics_use_gtag', '__return_true' );` if you really need to.

### Google Ads

Ads really wants you to use `gtag`, so in the event you enable both Analytics and Ads, the plugin switches to only using `gtag` and combines the setup code into one block with no extraneous HTTP requests.

I am bad at Ads, so I've only enabled the `purchase` event, preferring to get the rest from an Analytics connection where I am more comfortable with how things work. Purchase works well, though, and [adding a custom event](https://github.com/ryansalerno/woocommerce-cnvrsn-trckng/wiki/Adding-a-custom-event) is straightforward.

If someone smarter than me would like to point me in some right directions, this could be extended pretty easily.

### Custom

You can [read: *should be able to*] add your own fully custom Integration with hooks, but this is different from that. In the settings, there's a section for generic custom integrations that uses replacement tags to let you add simple scripts and tracking pixels here and there without writing code. Supported events are:

* Category View
* Product View
* Add to Cart
* Remove from Cart
* Start Checkout
* Successful Purchase
* User Registration

Allowing you to easily add something like: `<img height="1" width="1" alt="" src="//whatevr.io/track/pxl/?v={order_total}&orderid={order_number}&d=
{order_discount}" />` or even `<script async defer src="https://some.app/1234/impression/{product_id}"></script>` when relevant.

For each enabled event, you're given a textarea and some relevant dynamic replacement tags. For instance, the `Successful Purchase` event gives you the textarea and offers the following text immediately below for your pasting convenience:

> Dynamic replacement tags: `{currency}`, `{order_number}`, `{order_total}`, `{order_subtotal}`, `{order_discount}`, `{order_shipping}`, `{order_tax}`, `{payment_method}`, `{used_coupons}`, `{customer_id}`, `{customer_email}`, `{customer_first_name}`, `{customer_last_name}`

Note though, that this is necessarily kind of a security hole.... We can't effectively sanitize or escape this input because it's probably some third-party JS by design. And since we don't know what the contents or destination format of the tags are, they're also not specifically escaped or url encoded or anything. This will work nicely for simple cases, and care has been taken to not offer arrays or other complex data types to be replaced erroneously, but consider this a "with great power" situation.

# Roadmap

* Re-add Facebook Events Integration
* Test and document adding your own Integration with filters
* Google Analytics v4 changes? (sigh)
* Arbitrary Webhook Integration
* ...something you suggest in the [Issues](../../issues)?
