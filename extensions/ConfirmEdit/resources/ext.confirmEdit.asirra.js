/*======================================================================*\
|| #################################################################### ||
|| # Asirra module for ConfirmEdit by Bachsau                         # ||
|| # ---------------------------------------------------------------- # ||
|| # This code is released into public domain, in the hope that it    # ||
|| # will be useful, but without any warranty.                        # ||
|| # ------------ YOU CAN DO WITH IT WHATEVER YOU LIKE! ------------- # ||
|| #################################################################### ||
\*======================================================================*/

jQuery( function( $ ) {
	// Selectors for create account, login, and page edit forms.
	var asirraform = $( 'form#userlogin2, #userloginForm form, form#editform' );
	var submitButtonClicked = document.createElement("input");
	var passThroughFormSubmit = false;

	function PrepareSubmit() {
		submitButtonClicked.type = "hidden";
		var inputFields = asirraform.find( "input" );
		for (var i=0; i<inputFields.length; i++) {
			if (inputFields[i].type === "submit") {
				inputFields[i].onclick = function(event) {
					submitButtonClicked.name = this.name;
					submitButtonClicked.value = this.value;
				}
			}
		}

		asirraform.submit( function() {
			return MySubmitForm();
		} );
	}

	function MySubmitForm() {
		if (passThroughFormSubmit) {
			return true;
		}
		Asirra_CheckIfHuman(HumanCheckComplete);
		return false;
	}

	function HumanCheckComplete(isHuman) {
		if (!isHuman) {
			window.alert( mediaWiki.msg( 'asirra-failed' ) );
		} else {
			asirraform.append(submitButtonClicked);
			passThroughFormSubmit = true;
			asirraform.submit();
		}
	}

	PrepareSubmit();

} );
