( function( blocks, element ) {
    var el = element.createElement;

    blocks.registerBlockType( 'bmi-calculator/bmi-block', {
        edit: function() {
            return el(
                'div',
                {
                    style: {
                        padding: '40px 20px',
                        background: '#f0f0f0',
                        border: '2px dashed #ccc',
                        borderRadius: '8px',
                        textAlign: 'center',
                        color: '#555',
                    },
                },
                el( 'span', { className: 'dashicons dashicons-heart', style: { fontSize: '36px', marginBottom: '10px', display: 'block' } } ),
                el( 'strong', {}, 'BMI Calculator' ),
                el( 'p', { style: { marginTop: '8px', fontSize: '13px' } }, 'The BMI Calculator will appear on the front end.' )
            );
        },
        save: function() {
            return null;
        },
    } );
} )( window.wp.blocks, window.wp.element );
