Demo.SampleTabPanel = function DemoSampleTabPanel( name, config ) {
	OO.ui.TabPanelLayout.call( this, name, config );
	this.$element.text( this.label );
};
OO.inheritClass( Demo.SampleTabPanel, OO.ui.TabPanelLayout );
