/* Early Black Friday promo: copy coupon + snooze/dismiss */
( function () {
    if ( typeof aluc_promo === 'undefined' ) {
        return;
    }

    function copyText( text ) {
        if ( navigator.clipboard && window.isSecureContext ) {
            return navigator.clipboard.writeText( text );
        }
        var ta = document.createElement( 'textarea' );
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild( ta );
        ta.select();
        try { document.execCommand( 'copy' ); } catch ( e ) {}
        document.body.removeChild( ta );
        return Promise.resolve();
    }

    document.addEventListener( 'click', function ( e ) {
        var coupon = e.target.closest( '.aluc-promo-coupon' );
        if ( coupon ) {
            var label = coupon.querySelector( '.aluc-promo-coupon-copy' );
            var original = label.textContent;
            copyText( coupon.getAttribute( 'data-code' ) ).then( function () {
                label.textContent = aluc_promo.copied;
                setTimeout( function () { label.textContent = original; }, 1800 );
            } );
            return;
        }

        var btn = e.target.closest( '.aluc-promo-notice [data-type]' );
        if ( ! btn ) {
            return;
        }

        var notice = btn.closest( '.aluc-promo-notice' );
        notice.style.transition = 'opacity .2s';
        notice.style.opacity = '0';
        setTimeout( function () { notice.remove(); }, 200 );

        var body = new URLSearchParams();
        body.append( 'action', 'aluc_promo_dismiss' );
        body.append( 'nonce', aluc_promo.nonce );
        body.append( 'type', btn.getAttribute( 'data-type' ) );

        fetch( aluc_promo.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        } );
    } );
} )();
