(function ($) {
    "use strict";

    String.prototype.basename = function( ) {
        var base = new String(this).substring(this.lastIndexOf('/') + 1);
        return base;
    };

    // Clear selected error log
    $(function () {
        $('#awfn-error-logs').on('click', '.awfn-clear-log', function (e) {

            var nonce = options.secure,
                ajax_url = options.ajax_url,
                awfn_debug = options.awfn_debug,
                file = $(e.target).data('file'),
                fileName,
                log_div;

            fileName = file.basename();

            if ( confirm( 'Truncate ' + fileName + '?' ) ) {

                log_div = document.getElementById( fileName );

                $.ajax({
                    url: ajax_url,
                    type: 'post',
                    data: {
                        action: 'awfn_clear_log',
                        file: file,
                        secure: nonce
                    },
                    success : function( resp ) {
                        log_div.innerHTML = '<p class="success">&lt;Log Cleared&gt;</p>';
                        if ( awfn_debug ) {
                            console.log( resp.data );
                        }
                    },
                    error : function( resp ) {
                        if ( awfn_debug ) {
                            console.log( resp.responseText );
                        }
                    }
                })
            }

        });

        $('#awfn-clear-all').click( function (e) {

            var nonce = options.secure,
                ajax_url = options.ajax_url,
                awfn_debug = options.awfn_debug,
                file = $(e.target).data('file'),
                fileName,
                log_div,
                all_log_divs;

            if ( confirm( 'Truncate All Files?' ) ) {

                all_log_divs = document.getElementsByClassName( 'awfn-log-display' );

                $.ajax({
                    url: ajax_url,
                    type: 'post',
                    data: {
                        action: 'awfn_clear_all_logs',
                        file: file,
                        secure: nonce
                    },
                    success : function( resp ) {
                        $.each(all_log_divs, function( i, val ) {
                            if ( awfn_debug ) {
                                console.log( this );
                            }
                            this.innerHTML = '<p class="success">&lt;Log Cleared&gt;</p>';
                        } );

                        if ( awfn_debug ) {
                            console.log( resp.data );
                        }
                    },
                    error : function( resp ) {
                        if ( awfn_debug ) {
                            console.log( resp.responseText );
                        }
                    }
                })
            }

        });
    });
}(jQuery));