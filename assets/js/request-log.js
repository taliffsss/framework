"use strict";
/**
 * Replenish Update Event
 */
let myaction;
let readonly = document.querySelector('.btn-status').value;

function _rejected_yes_No(code,id) {

	const myid = document.querySelector('#'+code).value;
	const itemName = document.querySelector('#td-'+id).innerHTML;

	if(myid != '') {
		document.querySelector('#request-logs_header').innerHTML = 'Reject Item';
		const val = document.querySelector('#btn-'+code).value;
		document.querySelector('#request-logs_body').innerHTML = 'Are you sure you want to reject <b>' + itemName+'</b>';
		$('#request-logs_Footer').html('<button data-dismiss="modal" class="btn thmBtn" onclick="_rejected(\''+code+'\',\''+myid+'\','+id+')">Yes</button><button type="button" class="btn btn-default" data-dismiss="modal">No</button>')
		$('#request_logs_yN').modal('show');
	} else {
		document.querySelector('#'+code).style.borderColor = "red";
		document.querySelector('#'+code).focus();
	}
}

function _approved_all(code) {
	document.querySelector('#formAction').action = 'http://localhost:8080/man/request-logs/approved-all/'+code
	document.querySelector('#modal_header').innerHTML = 'Approved All';
	if(segment == 1) {
		document.querySelector('#modal_body').innerHTML = 'Are you sure you want to issue the requested items?'
	} else {
		document.querySelector('#modal_body').innerHTML = '<b>'+code+'</b> is now approved and will be sent to ADMIN for item issuance'
	}
	
	$('#modal_Footer').html('<input type="submit" class="btn thmBtn" value="Yes"><button type="button" class="btn btn-default" data-dismiss="modal">No</button>')
	$('#modal_yN').modal('show');
}

function _rejected_all(code) {
	document.querySelector('#formAction').action = 'http://localhost:8080/man/request-logs/reject-all/'+code
	document.querySelector('#rejected_header').innerHTML = 'Reject All';
	$('#rejected_Footer').html('<input type="submit" class="btn thmBtn" value="Yes"><button type="button" class="btn btn-default" data-dismiss="modal">No</button>')
	$('#rejected_yN').modal('show');
}

function _reject_all(code) {
	myaction = document.querySelector('#reject-reason').value;
	const formAction = document.querySelector('#formAction').action;
	if(formAction != 'http://localhost:8080/man/request-logs/reject-all/'+code) {
		return true;
	} else {
		if(myaction != '') {
		return true;
		} else {
			document.querySelector('#reject-reason').style.borderColor = "red";
			return false;
		}
	}
	
}

/**
 * Once status completed Print me
 */
function _completed_print_me() {
	var printContents = document.getElementById('printMe').innerHTML;
	var w = window.open();
	w.document.write(printContents);
	w.print();
	w.close();
}

function _rejected(code,myid,id) {

	const uri = unique();
	myaction = document.querySelector('#code-'+id).value;

	$.ajax({
		url: 'http://localhost:8080/man/request-logs/rejected/' + uri,
		type: "POST",
		data: {code:code,myid:myid,transcode:myaction},
		beforeSend:function(){
			document.querySelector('#btn-'+code).value = 'loading';
		},
		success: function(data) {
			document.querySelector('#btn-'+code).value = 'Rejected';
			document.querySelector('#btn-'+code).style.color = "red";
			document.querySelector('#'+code).value = '';
			document.querySelector('#'+code).style.borderColor = "#ced4da";
			document.querySelector('#'+code).value = myid;
			document.querySelector('#trans-val-'+code).value = 0;
			document.querySelector('#btn-'+code).disabled = true;
			document.querySelector('#'+code).disabled = true;
			document.querySelector('#item-'+id).disabled = true;
			document.querySelector('#code-'+id).disabled = true;
		},
		error: function() {
			document.querySelector('#btn-'+code).value = 'Error';
		}
	});
}

function _readonly() {
	if(readonly == 'Disapproved') {
		document.querySelector("#btnreject").disabled = true;
		document.querySelector("#btnapproved").disabled = true;
	}
}
_readonly();