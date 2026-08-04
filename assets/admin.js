( function() {
	var override = document.getElementById( 'sgtm-override' );
	var fields = document.getElementById( 'sgtm-fields' );
	if ( ! override || ! fields ) {
		return;
	}
	function sync() {
		fields.disabled = ! override.checked;
	}
	override.addEventListener( 'change', sync );
	sync();
} )();