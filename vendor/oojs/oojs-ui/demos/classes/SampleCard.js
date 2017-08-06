Demo.SampleCard = function DemoSampleCard( name, config ) {
	OO.ui.CardLayout.call( this, name, config );
	this.$element.text( this.label );
};
OO.inheritClass( Demo.SampleCard, OO.ui.CardLayout );
