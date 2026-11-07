const {registerBlockType}      = wp.blocks;
const {createElement}          = wp.element;
const {__}                     = wp.i18n;
const {InspectorControls}      = wp.blockEditor;
const {serverSideRender}       = wp;
const {TextControl, ToggleControl, PanelBody} = wp.components;

registerBlockType( 'otpa/otpa-2fa-switch', {
	title: __( 'OTPA 2FA Switch', 'otpa' ),
	category:  __( 'widgets' ),
	attributes:  {
		label: {
			default: '',
		},
		labelInline: {
			default: false,
		},
		turnOnText: {
			default: __( 'Two-Factor Authentication is OFF - Click to Enable', 'otpa' ),
		},
		turnOffText: {
			default: __( 'Two-Factor Authentication is ON - Click to Disable', 'otpa' ),
		},
		className: {
			default: '',
		},
	},

	edit(props) {
		const attributes    = props.attributes;
		const setAttributes = props.setAttributes;

		function changeTurnOnText(turnOnText) {
			setAttributes({turnOnText});
		}

		function changeTurnOffText(turnOffText) {
			setAttributes({turnOffText});
		}

		function changeLabel(label) {
			setAttributes({label});
		}

		function changeLabelInline(labelInline) {
			setAttributes({labelInline});
		}

		return createElement('div', {key: 'otpa-block-container'}, [
			createElement(serverSideRender, {
				key: 'otpa-renderer-key',
				block: 'otpa/otpa-2fa-switch',
				attributes: attributes,
			}),
			createElement(InspectorControls, {key: 'otpa-block-inspector-controls'}, [
				createElement(PanelBody, {key: 'otpa-block-panel-body'}, [
					createElement(TextControl, {
						key: 'otpa-text-control-label-text',
						value: attributes.label,
						label: __( 'Label', 'otpa' ),
						onChange: changeLabel,
						type: 'text',
						help: __( 'Label text to display above the button by default.', 'otpa' )
					}),
					createElement(ToggleControl, {
						key: 'otpa-toggle-control-label-inline',
						checked: attributes.labelInline,
						label: __( 'Display label inline', 'otpa' ),
						onChange: changeLabelInline,
						help: __( 'Display the label and button on the same line.', 'otpa' )
					}),
					createElement(TextControl, {
						key: 'otpa-text-control-turn-on-text',
						value: attributes.turnOnText,
						label: __( 'OTPA 2FA OFF text', 'otpa' ),
						onChange: changeTurnOnText,
						type: 'text',
						help: __( 'Button text when Two-Factor Authentication is inactive.', 'otpa' )
					}),
					createElement(TextControl, {
						key: 'otpa-text-control-turn-off-text',
						value: attributes.turnOffText,
						label: __( 'OTPA 2FA ON text', 'otpa' ),
						onChange: changeTurnOffText,
						type: 'text',
						help: __( 'Button text when Two-Factor Authentication is active.', 'otpa' )
					}),
				])
			])
		]);
	},
	save(){
		return null;
	}
});
