(function( $ ) {

    // Toggoling the settings
    $( '.slider' ).on( 'click', function() {
        var id = $( this ).attr( 'data-id' );
        var target = $( '#setting-'+id );
        target.stop().toggle('fast');
    });

    // Default Settings
    $( '.toogle-seller:checked' ).each( function( index, value ) {
        var id =  $( value ).attr( 'data-id' );
        var target = $( '#setting-'+id );

        $( target ).css( 'display', 'block' );
    } );

    $('.event').on( 'change', function() {
        var target = $( this ).next('.event-label-box');
        target.addClass( 'event-label-space' );
        target.stop().toggle();

    } );

    $( '.event:checked' ).each( function( index, value ) {
        $( value ).next( '.event-label-box' ).addClass( 'event-label-space' );
        $( value ).next( '.event-label-box' ).css( 'display', 'block' );
    } );

    // Change Tooltip Text
    $( '.toogle-seller' ).on( 'change', function() {
        var tooltipText = $( this ).parents( '.switch' ).find( '.integration-tooltip' );
        var text = $( tooltipText ).text().trim();
        var newText = '';

        if ( text == 'Activate' ) {
            newText = 'Deactivate'
        } else if( text == 'Deactivate' ) {
           newText = 'Activate';
        }

        $( tooltipText ).text( newText );
    } );

})( jQuery );
