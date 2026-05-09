/* global LM, jQuery */
( function ( $ ) {
    'use strict';

    $( document ).on( 'click', '.lm-vote', function () {
        const $btn     = $( this );
        const $wrap    = $btn.closest( '.lm-ratings' );
        const postId   = $wrap.data( 'post-id' );
        const value    = $btn.data( 'value' );

        if ( $btn.prop( 'disabled' ) ) {
            return;
        }

        $btn.prop( 'disabled', true );

        $.post( LM.ajax_url, {
            action:  'lm_vote',
            nonce:   LM.nonce,
            post_id: postId,
            value:   value,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                $wrap.find( '.lm-count-up'   ).text( res.data.up );
                $wrap.find( '.lm-count-down' ).text( res.data.down );
                $wrap.find( '.lm-score'      ).text( res.data.score );
                $wrap.find( '.lm-vote'       ).prop( 'disabled', true ).addClass( 'disabled' );
                $wrap.append( '<span class="lm-voted-note"> (' + ( LM.i18n.already_voted || 'Abgestimmt' ) + ')</span>' );
            } else {
                alert( res.data.message || LM.i18n.vote_error );
                $btn.prop( 'disabled', false );
            }
        } )
        .fail( function () {
            alert( LM.i18n.vote_error );
            $btn.prop( 'disabled', false );
        } );
    } );

    // -------------------------------------------------------------------------
    // Vorschlagsformular per AJAX absenden
    // -------------------------------------------------------------------------

    $( '#lm-submit-form' ).on( 'submit', function ( e ) {
        e.preventDefault();

        const $form    = $( this );
        const $notice  = $( '#lm-submit-notice' );
        const $btn     = $form.find( 'button[type=submit]' );
        const formData = $form.serializeArray();
        formData.push( { name: 'action', value: 'lm_submit_link' } );

        $btn.prop( 'disabled', true );
        $notice.hide();

        $.post( LM.ajax_url, $.param( formData ) )
        .done( function ( res ) {
            if ( res.success ) {
                $notice
                    .removeClass( 'lm-notice-error' )
                    .addClass( 'lm-notice-success' )
                    .text( res.data.message )
                    .show();
                $form[0].reset();
            } else {
                $notice
                    .removeClass( 'lm-notice-success' )
                    .addClass( 'lm-notice-error' )
                    .text( res.data.message )
                    .show();
                $btn.prop( 'disabled', false );
            }
        } )
        .fail( function () {
            $notice
                .addClass( 'lm-notice-error' )
                .text( 'Ein Fehler ist aufgetreten. Bitte versuche es später.' )
                .show();
            $btn.prop( 'disabled', false );
        } );
    } );

} )( jQuery );
