( function() {
	var override = document.getElementById( 'cosi-tags-override' );
	var fields = document.getElementById( 'cosi-tags-fields' );
	if ( ! override || ! fields ) {
		return;
	}
	function sync() {
		fields.disabled = ! override.checked;
	}
	override.addEventListener( 'change', sync );
	sync();
} )();