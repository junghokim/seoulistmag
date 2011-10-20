
var tagLimit   = <?php echo floor($publish_entry_tag_limit); ?>;
var parseType  = '<?php echo $delimiter; ?>'; //comma, semicolon, colon, space...oy!!

function tagFieldCaptureDelete(e)
{
	var newTagsAllowed = '<?php echo $this->preference('allow_tag_creation_publish'); ?>';
	
	if (!e && window.event)
	{
		var e = window.event;
	}
	
	if (!e) return;

	var keyValue = (!e.which || e.which == 0) ? e.keyCode : e.which;

	// Only the delete key is allowed, then.

	if (newTagsAllowed == 'n')
	{
		return (keyValue == 46 || keyValue == 8) ? true : false;
	}
}

function checkTextareaLimit()
{
	//
	// Allow Deleting without doing Check
	//

	if (!e && window.event)
	{
		var e = window.event;
	}
	
	if (!e) return;

	var keyValue = (!e.which || e.which == 0) ? e.keyCode : e.which;

	if (keyValue == 46 || keyValue == 8)
	{
		return true;
	}

	//
	// Basic Checks and Cleanup
	//

	if (tagLimit == 0) return false;

	if ( ! document.getElementById('tag_f')) return false;

	var tagText = document.getElementById('tag_f').value;

	if (solspaceTextTagLimit(tagText) == false)
	{
		document.getElementById('tag_f').value = tagText.substring(0, document.getElementById('tag_f').value.length - 1);
		return false;
	}
}

function solspaceTextTagLimit(tagText)
{
	if (tagText == '' || tagLimit == 0) return false;

	tagText = tagText.replace(/^\s+/g, '').replace(/\s+$/g, '').replace(/(\r\n|\r)/g, "\n");

	//
	// Find out current number of Tags
	// Line breaks are always valid Tag breakers, for some reason...
	//

	switch(parseType)
	{
		case 'comma'     : tagText = tagText.replace(/^,+/g, '').replace(/^:+/g, '');
						   var numberTags = tagText.split(/,|\n/g).length;
		break;
		case 'semicolon' : tagText = tagText.replace(/^;+/g, '').replace(/:+$/g, '');
						   var numberTags = tagText.split(/;|\n/g).length;
	    break;
		case 'colon'     : tagText = tagText.replace(/^:+/g, '').replace(/:+$/g, '');
						   var numberTags = tagText.split(/:|\n/g).length;
		break;
		case 'space'     : var ourChar = " ";
						   var numberTags = tagText.split(/ |\t|\n/g).length;
		break;
		default          : var numberTags = tagText.split("\n").length;
		break;
	}

	// Split and Count

	if (numberTags > tagLimit)
	{
		var maximumTagWarning = new String("<?php echo htmlspecialchars(ee()->lang->line('tag_preference_maximum_tags_allowed'));?>");
		alert(maximumTagWarning.replace('%n%', tagLimit));
		return false;
	}
	else
	{
		return true;
	}
}

jQuery.fn.extend({
	// Tag Browser functionality
	tag_browser: function (event, field, link, id) {
		event.preventDefault();

		// Reset dead_end on delete or backspace
		if (event.keyCode == 46 || event.keyCode == 8) dead_end = false;

		// Don't search until at least two letters are entered
		if (field.value.length < 2 && field.value != '*') return false;

		var msm_tag_search	= (jQuery(":checkbox[name*=msm_tag_search]").attr('checked') == true) ? 'y' : 'n';

		if (old_msm_check != msm_tag_search)
		{
			dead_end = false;
			old_msm_check = msm_tag_search;
		}

		// Set the action
		action = 'browse';

		// Capture various keyCodes
		switch (event.keyCode) {
			case 39: // Right
			case 13: // Enter/Return
				if (field.value != '')
				{
					solspace_add_tag(field, event, 'new');
				}
				break;
			default:
				if (!dead_end)
				{
					var existing_str = solspace_existing_tags();
					jQuery('#tag_browse_results').css('display','block').load(link, { entry_id: id, msm_tag_search: msm_tag_search, str: field.value, XID: '{XID_SECURE_HASH}', existing: existing_str }, rebind);
				}
		}
	},

	// Suggest Tags functionality
	suggest_tags: function(link) {
		var string 			= solspace_compile_string();
		var existing_str 	= solspace_existing_tags();
		var msm_tag_search	= (jQuery(":checkbox[name*=msm_tag_search]").attr('checked') == true) ? 'y' : 'n';
		action 				= 'suggest';

		jQuery('#tag_browse_f').focus();

		jQuery('#tag_browse_results').css('display','block').load(link, { XID: '{XID_SECURE_HASH}', msm_tag_search: msm_tag_search, str: string, existing: existing_str }, rebind);
	},

	// Ajax query notification
	notify: function(target, themes_url, message) {
		var code = '<div class="notify">'+ message +'</div>';
		jQuery(target).empty().append(code);
	}
});


// Convert custom fields into delimited string for the Ajax query
function solspace_compile_string()
{
	var str = [];

	jQuery('textarea[name^=field_id]').each( function(i)
	{
		jQuery.trim(this.value);
		if (this.value != '') str.push(parse(this.value));
	});

	jQuery('input[name^=field_id]').each( function(i)
	{
		jQuery.trim(this.value);
		if (this.value != '') str.push(parse(this.value));
	});

	return str.join('||');
}

// Compile existing tags into delimited string for the Ajax query
function solspace_existing_tags()
{
	var temp = [];

	jQuery.each(include_tags, function(i, tag) {
		temp.push(tag);
	});

	return temp.join('||');
};

// Update tags array
function update_tags()
{
	var str = [];

	jQuery.each(include_tags, function(i, tag) {
		// Add quotes around phrase tags if the delimeter is a space
		if (delim == " " && tag.indexOf(' ') != -1) {
			tag = '"'+ tag +'"';
		};

		str.push(tag);
	});

	jQuery('#tag_f').val(str.join(delim));
}

// Add a new tag to the tags array
function solspace_add_tag(el, event, kind)
{
	event.preventDefault();

	if (tagLimit != 0 && include_tags.length >= tagLimit)
	{
		var maximumTagWarning = new String("<?php echo htmlspecialchars(ee()->lang->line('tag_preference_maximum_tags_allowed'));?>");
		alert(maximumTagWarning.replace('%n%', tagLimit));
		return false;
	}

	if (kind == 'new')
	{
		include_tags.push(el.value);

		update_tags();

		el.value = '';
		dead_end = false;
		jQuery('#tag_browse_results').css('display','none');
	}
	else
	{
		include_tags.push(el.firstChild.nodeValue);

		update_tags();

		jQuery(el.parentNode).remove();

		if (jQuery('#tag_browse_results ul li').length == 0) {
			jQuery('#tag_browse_results').css('display','none');
			jQuery('#tag_browse_f').val('').focus();
		}
	}
}

// Clean up words for suggest tags functionality
function parse(val)
{
	var txt = val;

	txt = txt.replace(/(<([^>]+)>)/ig,''); //strip HTML
	txt = txt.replace(/(\{([^\}]+)\})/ig,''); //strip EE tags
	txt = txt.replace(/([^\u3400-\u4db5\u4e00-\u9fa5\uf900-\ufa6a\u3041-\u3094\u30a1-\u30fa\w\d\s]+)/ig, ''); // strip punctuation

	jQuery.trim(txt);

	txt = txt.match(/^ *$/) ? '' : txt.split(/\s+/g).join('||');

	return txt;
}

// Rebind the function to the newly added tag links
function rebind(text, status, data)
{
	if (action == 'browse')
	{
		if (text.indexOf('span class="message"') != -1) dead_end = true;
	}

	// Bind click event to anchors
	jQuery('#tag_browse_results ul li a').click(function(event)
	{
		solspace_add_tag(this, event);
	});
};


// Global variables

var tag_focus	  = false;
var dead_end	  = false;
var old_msm_check = false;
var action		  = '';

jQuery(document).ready(function($)
{
	// Disable autocomplete for tag browse field. Covers up our tag browse results
	jQuery('#tag_browse_f').attr("autocomplete", "off");

	// Alter existing_tags array if the tags field has changed
	jQuery('#tag_f').change(function(event)
	{
		event.stopPropagation();
		var str = this.value;

		// Deal with the hell that is the space delimiter
		if (delim == ' ') {
			// Let good browsers do their thing efficiently
			if ('a~b'.split(/(~)/).length == 3) {
				var temp = str.split(/\"([^\"]*)\"|\s/ig);

				// Strip out blank values from the array
				temp = jQuery.grep(temp, function(n,i) {
					return n != '';
				});
			} else { // Walk regex-weak browsers through the string
				var temp 	= [];
				var quote 	= false;
				var buffer	= '';

				for (var i=0; i < str.length; i++) {
					var c = str.charAt(i);
					switch (c) {
						case ' ':
							if (! quote) {
								temp.push(buffer);
								buffer = '';
							} else {
								buffer += c;
							}
							break;
						case '"':
							(quote) ? quote = false : quote = true;
							break;
						default:
							buffer += c;
					}
				}

				// Flush out buffer for last tag
				temp.push(buffer);
			}
		} else { // Every other delimiter is a breeze
			var temp = str.split(delim);
		}

		include_tags = temp;
	});

	// Remove searching message when Ajax query is finished
	jQuery('#tag_browse_results').ajaxStop(function()
	{
		jQuery('#tag_browse_results .notify').remove();
	});

	// CP harvest channel fields pulldown
	jQuery('#channel_id_field').change(function(event)
	{
		jQuery('select','#field_block').each(function(i)
		{
			this.value = '';
		});

		jQuery('.field_group','#field_block').each(function(i) {
			jQuery(this).hide();
		});

		jQuery('#field_groups_'+this.value).show();
	});

	// Switch status of tag browse field based on focus/blur. Used in form submission
	jQuery('#tag_browse_f').focus(function()
	{
		tag_focus = true;
	});

	jQuery('#tag_browse_f').blur(function()
	{
		tag_focus = false;
	});

	// Stop auto entry submission if tag browse field is in focus
	jQuery('#entryform').submit(function(event)
	{
		if (tag_focus) return false;
	});

	// Get rid of ugly outline around clicked links in browsers
	jQuery('a').focus(function()
	{
		this.blur();
	});

});