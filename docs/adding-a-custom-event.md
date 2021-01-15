This process is maybe a bit verbose in places, but overall pretty straightforward. For this example, we'll add a custom event to the Google Ads integration (taking advantage of its extra settings per event automatically). Let's go:

### 1. Define the event
Events exist at the topmost level as a map of slugs and labels, outside the context of any specific integration (and therefore able to be added to any of them).

The slug is important, acting as the key for later references, so you must match what you enter here in later blocks of code. The label is purely for friendly display on the settings screen.
```PHP
add_filter( 'cnvrsn_trckng_supported_events', function( $default_events ) {
	// $default_events['slug'] = __( 'Label', 'woocommerce-cnvrsn-trckng' );
	$default_events['landing_page_conversion'] = __( 'Landing Page Conversion', 'woocommerce-cnvrsn-trckng' );

	return $default_events;
} );
```
### 2. Add the event to an integration
Now that the event exists, you merely have to associate the key with any integrations you'd like. The filter is `cnvrsn_trckng_{$integration_id}_supported_events`, so in our example we check the top of `includes/integrations/google-ads.php` and are unsurprised to see `$this->id = 'google-ads';` netting us our final filter:

```PHP
add_filter( 'cnvrsn_trckng_google-ads_supported_events', function( $events ) {
	$events[] = 'landing_page_conversion';

	return $events;
} );
```
What's cool about this is now the Google Ads Integration treats your new event like any other event it knows about and will automatically ask for and store appropriate event labels in the settings without requiring any extra code or steps.

Maybe you want your event to apply to multiple integrations, in which case just opt to not use an anonymous function:
```PHP
function some_fancy_namespace_custom_event_adder( $events ) {
	$events[] = 'landing_page_conversion';

	return $events;
}

add_filter( 'cnvrsn_trckng_google-ads_supported_events', 'some_fancy_namespace_custom_event_adder' );
add_filter( 'cnvrsn_trckng_custom_supported_events', 'some_fancy_namespace_custom_event_adder' );
```
### 3. Dispatch the event when appropriate
It's helpful to call the plugin's `dispatch_event` function since it does the checking for you of whether the integration is enabled and keeps everything flowing the same way, but you could opt to skip this and call the next function we define directly based on some business logic if you wish.
```PHP
// wrapper for the plugin's dispatch_event function
function example_landing_page_conversion_dispatch( $order_id ) {
	/**
	 * CnvrsnTrckng\Events\dispatch_event( $event, $data, $callback );
	 * @param string $event    The key we've been using over and over since setting it up in step 1
	 * @param array  $data     Any gathered data you want to pass along
	 * @param string $callback The ultimate function for executing the new event
	 */
	CnvrsnTrckng\Events\dispatch_event(
		'landing_page_conversion',
		CnvrsnTrckng\Events\get_purchase_data( $order_id ),
		'example_landing_page_conversion'
	);
}

// and then we'll just trigger it through a direct call of the above dispatch function in your code
// or by attaching it to some action (custom or otherwise)
add_action( 'woocommerce_thankyou', 'example_landing_page_conversion_dispatch' );
```
### 4. Do the damn thing
We did it! Now we just need something to happen when the event is triggered. If you used the `dispatch_event` method above, your callback will get passed the relevant `Integration` class so you can access plugin settings easily or other built-in code-generating-or-what-have-you methods.
```PHP
// this is the callback we defined just above
function example_landing_page_conversion( $order_data, $integration ) {
	$settings = $integration->get_plugin_settings();
	if ( empty( $settings['account_id'] ) || empty( $settings['labels']['landing_page_conversion'] ) ) { return; }

	$code = $integration->build_event(
		'conversion',
		array(
			'send_to'        => $settings['account_id'] . '/' . $settings['labels']['landing_page_conversion'],
			'transaction_id' => $order_data['order_number'],
			'value'          => $order_data['order_total'],
			'currency'       => $order_data['currency'],
		)
	);

	CnvrsnTrckng\Events\add_to_footer( $code );
}
```
For this contrived example, we're just recreating the purchase event with some separate Ads event label, so that's just the Ads `purchase()` code with our label swapped in by referencing that slug one final time.... But you could do anything you needed to, building out whatever makes sense for your use case here, along with whatever standard or modified collection of data you passed along.