// ********************************************************************************* //
var Tagger = Tagger ? Tagger : new Object();
//********************************************************************************* //

jQuery(document).ready(function(){
	
	var TaggerBox = jQuery('#TaggerBox');
	Tagger.FieldID = TaggerBox.attr('rel');
	TaggerBox.find('.InstantInsert, .TagSearch').keypress(Tagger.InstantSearch);
	TaggerBox.find('.MostUsedTags .tag').click(function(event){ Tagger.SaveTag(jQuery(event.target).html()); return false; });
	
	TaggerBox.find('.TagSearch').autocomplete(Tagger.AJAX_URL + '&tagger_ajax=yes&ajax_method=tag_search', {minChars: 1, resultsClass: 'ac_results tags_autocomplete'	});
	TaggerBox.find('.TagSearch').result(function(event, data, formatted){ jQuery(this).val('');  Tagger.SaveTag(formatted); });
	
	
	TaggerBox.find('.AssignedTags').sortable();
	TaggerBox.find('.AssignedTags .tag a').live('click', Tagger.DelTag);
});

//********************************************************************************* //

Tagger.InstantSearch = function(event){
	if (event.which == 13)	{
		Tagger.SaveTag(event.target.value);
		jQuery(this).val('');
		return false;
	}
};

//********************************************************************************* //

Tagger.MostUsedTagger = function(){
	Tagger.SaveTag( jQuery(this).html() );
	return false;
}

//********************************************************************************* //

Tagger.SaveTag = function(tag){
	var Elem = jQuery('#TaggerBox .AssignedTags');	
	Elem.find('.NoTagsAssigned').hide();
	
	var dupe = false;
	jQuery('input', Elem).each(function(){ if (jQuery(this).val() == tag) dupe = true; });
	
	if (dupe == false)
	{
		Inner = jQuery('<div/>').html(tag + '<input name="field_id_' + Tagger.FieldID + '[tags][]" value="'+tag+'" type="hidden"/> <a href="#"></a>');
		jQuery('<div/>').addClass('tag').html(Inner).insertBefore(Elem.find('.clear'));
	}
	return;
};

//********************************************************************************* //

Tagger.DelTag = function()
{
	jQuery(this).parent().parent().fadeOut('slow', function(){ jQuery(this).remove(); });
	return false;
};

//********************************************************************************* //