( function( blocks, element, blockEditor, components ) {
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var ColorPicker = components.ColorPicker;
    var ToggleControl = components.ToggleControl;
    var RangeControl = components.RangeControl;
    var SelectControl = components.SelectControl;

    var DIET_PRESETS = {
        'balanced':     { protein: 30, carbs: 40, fats: 30 },
        'keto':         { protein: 20, carbs: 5,  fats: 75 },
        'paleo':        { protein: 30, carbs: 20, fats: 50 },
        'high-protein': { protein: 40, carbs: 30, fats: 30 },
    };

    blocks.registerBlockType( 'bmi-calculator/bmi-block', {
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            return el(
                element.Fragment,
                {},
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: 'Appearance', initialOpen: true },
                        el( 'label', { style: { display: 'block', marginBottom: '8px', fontWeight: 'bold' } }, 'Accent Color' ),
                        el( ColorPicker, {
                            color: attributes.primaryColor,
                            onChangeComplete: function( color ) {
                                setAttributes( { primaryColor: color.hex } );
                            },
                        } )
                    ),
                    el(
                        PanelBody,
                        { title: 'Sections', initialOpen: true },
                        el( ToggleControl, {
                            label: 'Show History Section',
                            checked: attributes.showHistory,
                            onChange: function( val ) {
                                setAttributes( { showHistory: val } );
                            },
                        } ),
                        el( ToggleControl, {
                            label: 'Show Unit Converter',
                            checked: attributes.showConverter,
                            onChange: function( val ) {
                                setAttributes( { showConverter: val } );
                            },
                        } )
                    ),
                    el(
                        PanelBody,
                        { title: 'Macronutrient Ratios', initialOpen: false },
                        el( SelectControl, {
                            label: 'Diet Type',
                            value: attributes.dietType || 'balanced',
                            options: [
                                { label: 'Balanced', value: 'balanced' },
                                { label: 'Keto', value: 'keto' },
                                { label: 'Paleo', value: 'paleo' },
                                { label: 'High Protein', value: 'high-protein' },
                            ],
                            onChange: function( val ) {
                                var preset = DIET_PRESETS[val] || DIET_PRESETS['balanced'];
                                setAttributes( {
                                    dietType: val,
                                    macroProtein: preset.protein,
                                    macroCarbs: preset.carbs,
                                    macroFats: preset.fats,
                                } );
                            },
                        } ),
                        el( RangeControl, {
                            label: 'Protein %',
                            value: attributes.macroProtein,
                            onChange: function( val ) {
                                setAttributes( { macroProtein: val } );
                            },
                            min: 5,
                            max: 60,
                        } ),
                        el( RangeControl, {
                            label: 'Carbs %',
                            value: attributes.macroCarbs,
                            onChange: function( val ) {
                                setAttributes( { macroCarbs: val } );
                            },
                            min: 5,
                            max: 60,
                        } ),
                        el( RangeControl, {
                            label: 'Fats %',
                            value: attributes.macroFats,
                            onChange: function( val ) {
                                setAttributes( { macroFats: val } );
                            },
                            min: 5,
                            max: 60,
                        } ),
                        el( 'p', {
                            style: { fontSize: '12px', color: '#666', fontStyle: 'italic' }
                        }, 'Total: ' + ( attributes.macroProtein + attributes.macroCarbs + attributes.macroFats ) + '% (should equal 100%)' )
                    )
                ),
                el(
                    'div',
                    {
                        style: {
                            padding: '40px 20px',
                            background: '#f0f0f0',
                            border: '2px dashed ' + attributes.primaryColor,
                            borderRadius: '8px',
                            textAlign: 'center',
                            color: '#555',
                        },
                    },
                    el( 'span', { className: 'dashicons dashicons-heart', style: { fontSize: '36px', marginBottom: '10px', display: 'block', color: attributes.primaryColor } } ),
                    el( 'strong', {}, 'BMI Calculator' ),
                    el( 'p', { style: { marginTop: '8px', fontSize: '13px' } }, 'The BMI Calculator will appear on the front end.' ),
                    el( 'p', { style: { marginTop: '4px', fontSize: '11px', color: '#999' } },
                        'Accent: ' + attributes.primaryColor +
                        ' | History: ' + ( attributes.showHistory ? 'On' : 'Off' ) +
                        ' | Converter: ' + ( attributes.showConverter ? 'On' : 'Off' ) +
                        ' | Macros: ' + attributes.macroProtein + '/' + attributes.macroCarbs + '/' + attributes.macroFats
                    )
                )
            );
        },
        save: function() {
            return null;
        },
    } );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components );
