/**
 * Reset Password
 */
function _pass_reset(id,uname) {
	
	$('#pass__reset').modal('show');
	$('#pass__reset_header').html('<i class="fas fa-key"></i> <b>Reset Password</b>');
	$('#pass__reset_body').html("Are you sure you want to reset <b>"+uname+"'s</b> password?");
	$('#pass__reset_Footer').html('<button data-dismiss="modal" class="btn thmBtn" onclick="_confirm_reset('+id+',\''+uname+'\')">Yes</button><button data-dismiss="modal" class="btn btn-default">No</button>')
}

function _confirm_reset(id,uname) {

	var uri = unique();

	$.ajax({
 		url: "password-recovery/reset/" + uri,
 		type: "POST",
 		data: {
 			uid: id
 		},
 		beforeSend:function(){
			$('._reset__btn_'+uname).html('loading..');
			$('._reset__btn_'+uname).attr('disabled','disabled');
		},
 		success: function(data) {
 			$('._reset__btn_'+uname).removeAttr('disabled');
 			$('._reset__btn_'+uname).html('Reset Password');
 			toastr.success('Reset instruction has been sent', 'Success', {
				timeOut: 8000
			});
 		},
 		error: function() {
 			$('._reset__btn_'+uname).html('Error');
 			$('._reset__btn_'+uname).attr('disabled','disabled');
 			toastr.error('Oops, error!', 'Error', {
				timeOut: 8000
			});
 		}
 	});
}