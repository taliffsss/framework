function _update_inventory(reqcode) {
	
	$('#ask__update').modal('show');
	$('#ask__update_header').html('<i class="fas fa-exclamation"></i> <b>Update Inventory</b>');
	$('#ask__update_body').html("Are you sure you want to update inventory? Items requested under <b>"+reqcode+"</b> will be deducted.");
	$('#ask__update_Footer').html('<button data-dismiss="modal" class="btn thmBtn" onclick="_confirm_update_inventory(\''+reqcode+'\')">Yes</button><button data-dismiss="modal" class="btn btn-default">No</button>')
}

function _confirm_update_inventory(reqcode) {
	var uri = unique();

	$.ajax({
 		url: "stocks/deduct/" + uri,
 		type: "POST",
 		data: {
 			reqcode: reqcode
 		},
 		beforeSend:function(){
			$('.btn-'+reqcode).html('loading..');
			$('.btn-'+reqcode).attr('disabled','disabled');
		},
 		success: function(data) {
 			$('.btn-'+reqcode).removeAttr('disabled');
 			$('.btn-'+reqcode).html('Update Inventory');
 			toastr.success('Item/s listed on '+reqcode+' deducted from the Inventory', 'Success', {
				timeOut: 8000
			});
			setTimeout(location.reload.bind(location), 3000);
 		},
 		error: function() {
 			$('.btn-'+reqcode).html('Error');
 			$('.btn-'+reqcode).attr('disabled','disabled');
 			toastr.error('Something went wrong.', 'Error', {
				timeOut: 8000
			});
 		}
 	});
}