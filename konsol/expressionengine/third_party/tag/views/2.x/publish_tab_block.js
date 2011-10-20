
(function(global){

	global.AddOnBuilder = global.AddOnBuilder || {};
	var AddOnBuilder = global.AddOnBuilder;

	global.Solspace = global.Solspace || {};
	global.Solspace.prototype = AddOnBuilder;
	var Solspace = global.Solspace;
	
})(window);

Solspace.tag = Solspace.tag || (function(global, $) 
{
	var utils = {};
	var version = '3.0.0';
	
	var tag_limit		= <?php echo floor($publish_entry_tag_limit); ?>;
	//comma, semicolon, colon, space...oy!!
	var tag_separator	= '<?php echo ($separator == "\n") ? "\\n" : $separator;?>'; 
	var current_tags	= [<?php foreach($existing_tags as $data) : 
						   ?>"<?php echo addslashes($data['tag_name']);?>", <?php endforeach;?>];
	
	utils.tag_separator = tag_separator;
	
	utils.version = function()
	{
		return version;
	};
	
	// Ajax query notification
	utils.notify = function(target, themes_url, message)
	{
		var code = '<div class="notify">'+ message +'</div>';
		$(target).empty().append(code);
	};
	
	// Suggest Tags functionality
	utils.suggest_tags = function(link, event)
	{
		event.preventDefault();
		
		$('#tag__solspace_tag_submit').focus();

		$('#tag__solspace_tag_browse_results').
			css('display','block').
				load(
					link,	
					{	
						XID: '<?=$XID_SECURE_HASH?>', 
						str: Solspace.tag.compile_string(), 
						existing: $("#tag__solspace_tag_submit").val()
					}, 
					Solspace.tag.rebind
				);
	};
	
	// Rebind the function to the newly added tag links
	utils.rebind = function(text, status, data)
	{
		// Bind click event to anchors
		$('#tag__solspace_tag_browse_results ul li a').click(function(event)
		{
			Solspace.tag.add(this, event);
			$(this).remove();
		});
	};
	
	// Clean up words for suggest tags functionality
	utils.cleanup = function(val)
	{
		var txt = val;
	
		txt = txt.replace(/(<([^>]+)>)/ig,''); //strip HTML
		txt = txt.replace(/(\{([^\}]+)\})/ig,''); //strip EE tags
		txt = txt.replace(/([^\u3400-\u4db5\u4e00-\u9fa5\uf900-\ufa6a\u3041-\u3094\u30a1-\u30fa\w\d\s]+)/ig, ''); // strip punctuation
	
		$.trim(txt);
	
		return txt.match(/^ *$/) ? '' : txt.split(/\s+/g).join('||');
	};
	
	// Convert custom fields into delimited string for the Ajax query
	utils.compile_string = function()
	{
		var str = [];
	
		$('textarea[name^=field_id]').each( function(i)
		{
			$.trim(this.value);
			if (this.value != '') str.push(Solspace.tag.cleanup(this.value));
		});
	
		$('input[name^=field_id]').each( function(i)
		{
			$.trim(this.value);
			if (this.value != '') str.push(Solspace.tag.cleanup(this.value));
		});
	
		return str.join('||');
	};
	
	utils.check_limit = function(event)
	{
		// Allow Deleting without doing Check
	
		var keyValue = (!event.which || event.which == 0) ? event.keyCode : event.which;
	
		if (keyValue == 46 || keyValue == 8 || keyValue == 27 || keyValue == 13)
		{
			return true;
		}
	
		// Basic Checks and Cleanup
	
		if (tag_limit == 0) return false;
		
		currentTagsValue = $("#tag__solspace_tag_submit").val().replace(new RegExp("(.*)(" + tag_separator + ")\s*$"), '$1');  // Remove last separator

		// Number of Tags?
		tagsArray = currentTagsValue.split(tag_separator);
		
		if (tagsArray.length > tag_limit)
		{
			var maximumTagWarning = "<?php echo htmlspecialchars(ee()->lang->line('tag_preference_maximum_tags_allowed'));?>";
			alert(maximumTagWarning.replace('%n%', tag_limit));
			
			$("#tag__solspace_tag_submit").val(currentTagsValue.substr(0, currentTagsValue.length - 1));
			
			return false;
		}
	};
	
	// Add a new tag to the tags array
	utils.add = function(el, event, kind)
	{
		event.preventDefault();
		
		removeSeparator = new RegExp("(.*)(" + tag_separator + ")\s*$");
		
		currentTagsValue = $("#tag__solspace_tag_submit").val().replace(removeSeparator, '$1');  // Remove last separator
		
		if ( currentTagsValue != '')
		{
			currentTagsValue += tag_separator;
		}
		
		$("#tag__solspace_tag_submit").val(currentTagsValue + $(el).html() + tag_separator);
	};
	
	return utils;
})(window, jQuery);


jQuery(function($)
{
	$("#menu_tag a").html("<?php echo $tag_name;?>");
	$('#id_tag__solspace_tag_submit').hide(); // Hide the WriteMode image for our Tag Field
	$('label[for=tag__solspace_tag_suggest]').hide();
	
	$('#tag__solspace_tag_submit').keyup(function(event)
	{
		Solspace.tag.check_limit(event)
	});
	
	$("#tag__solspace_tag_submit").autocomplete("<?php echo $base_uri;?>&method=tag_autocomplete&ajax=solspace_tag_module",
	{
		multiple: true,
		mustMatch: false,
		autoFill: false,
		cacheLength: 0,
		selectFirst: false,
		delay: 300,
		multipleSeparator: Solspace.tag.tag_separator,
		extraParams: { current_tags: function() { return $("#tag__solspace_tag_submit").val(); } },
		formatResult : function(data, position) {
			return (Solspace.tag.tag_separator === ' ' && /\s/.test(data)) ? '"' + data + '"' : data; 
		}
	});
	
	$('#tag__solspace_tag_suggest').click(function(event)
	{
		Solspace.tag.suggest_tags("<?php echo $base_uri;?>&method=tag_suggest&ajax=solspace_tag_module", event)
	});
});




