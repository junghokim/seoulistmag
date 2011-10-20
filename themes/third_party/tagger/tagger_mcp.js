// ********************************************************************************* //
var Tagger = Tagger ? Tagger : new Object();
//********************************************************************************* //

$(document).ready(function() {
	
	$('.TaggerGroupSelect').multiSelect(null, Tagger.TaggerGroupSelect);
	$('.DeleteIcon').live('click', Tagger.DelConfirm);

});

//********************************************************************************* //

Tagger.DelConfirm = function(event){
	var answer = confirm('Are you sure you want to delete this? All associated data will also be removed');
	
	if (answer){
		window.location = jQuery(event.target).attr('href');
	}
	else {
		return false;
	}
}

//********************************************************************************* //

Tagger.TaggerGroupSelect = function(Elem){
	
	var Parent = $(Elem).closest('td');
	var tagid =  Parent.closest('tr').attr('rel');
	
	// Create LoadingElement	
	if (Parent.find('.gIcon').length < 1)
		$('<a class="gIcon LoadingIcon" href="#" style="display: inline; margin: 0 0 0 10px;"></a>').appendTo(Parent);
	else
		Parent.find('.gIcon').removeClass('SuccessIcon').addClass('LoadingIcon');
	
	// Grab list of group id's and shoot AJAX
	var Params = {ajax_method: 'add_to_group', XID: Tagger.XID, groups:new Array(), tag_id: tagid};
	
	$(Parent).find('input[type=checkbox]:checked').not('.selectAll').each(function(){
		Params.groups.push($(this).val());
	});
	
	$.post(Tagger.AJAX_URL, Params, function(resData){
		Parent.find('.gIcon').removeClass('LoadingIcon').addClass('SuccessIcon');
	});
};

//********************************************************************************* //