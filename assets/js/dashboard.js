let unit_msg = 'Unit name already exist.';

$(document).ready(function(){
	$('#btn-replenish').on('click', function() {
		$('#yes__NO').modal({ backdrop: 'static' }, 'show');
		$('#yes__NO_header').html('<b>Update Purchase Entry</b>');
		$('#yes__NO_body').html('Are you sure you want to update?');
		$('#replenish').css('z-index','100');
		$('#yes__NO_Footer').html('<button data-dismiss="modal" class="btn dashbtn" onclick="_replenish_close()">No</button><button data-dismiss="modal" class="btn thmBtn" onclick="_update_replenish()">Yes</button>')

	});

	$('#btn-edit_item').on('click', function() {
		$('#yes__NO').modal({ backdrop: 'static' }, 'show');
		$('#yes__NO_header').html('<b>Edit Item</b>');
		$('#yes__NO_body').html('Are you sure you want to update?');
		$('#item_Form_Edit').css('z-index','100');
		$('#yes__NO_Footer').html('<button data-dismiss="modal" class="btn dashbtn" onclick="_replenish_close()">No</button><button data-dismiss="modal" class="btn thmBtn" onclick="_update_item()">Yes</button>')

	});

	$("#item_standard").on('blur',function(){
	 	var val = $(this).val();

	 	if(val != ''){
	 		_standard_check(val);	
	 	}else{
	 		$('.err-msg-stnd').html("");
	 	}
	 	
	});

	$("#it_name").on('blur',function(){

	 	var val = $(this).val();

	 	if(val != ""){
	 		if(val.length > 3) {
 				$('.err-msg-name').html("");
		 	} else {
		 		$('.err-msg-name').html("Item name should be 4 to 20 characters.");
		 		$('.item-submit').attr('disabled','disabled');
		 	}
	 	}else{
	 		$('.err-msg-name').html("");
	 	}

	});

	$(".reorder_pt_check").on('blur',function(){
	 	var val =  parseInt($(this).val());
	 	var standard =  parseInt($('#item_standard').val());

	 	if(val != ''){
	 		_reorder_check(standard, val);	
	 	}else{
	 		$('.err-msg-rpt').html("");
	 	}
	 	
	});

	$('.modal').on('hidden.bs.modal', function (e) {
	  $(this)
	    .find("input[type='text']")
	       .val('')
	       .end()
	    .find("input[type='number']")
	       .val('')
	       .end();
	});

	$("#uName").on('blur',function(){

	 	var val = $(this).val();

	 	_unit_verify(val);
	});
});

/**
 * Check if category is alraedy available
 */
function _unit_verify(val) {

	var uri = unique();

	$.ajax({
		url: "stocks/unit-name/" + uri,
		method: "POST",
		data: {
			uname: val
		},
		dataType: "json",
		success: function(data) {
			if(data.msg == unit_msg) {
				$('.err-msg-name').html("Unit name already exist.");
				$('.unit-btn').attr('disabled','disabled');
			} else {
				$('.err-msg-name').html('');
				$(".unit-btn").removeAttr('disabled');
			}
		},
		error: function() {
			$('err-msg-cat').html('');
		}
	});
}

function _replenish_close() {
	$('#replenish').css('z-index','1050');
	$('#item_Form_Edit').css('z-index','1050');
}

/**
 * Replenish Update Event
 */
function _update_replenish() {
	var formData = $('#purchaseEntry').serialize();
	var uri = unique();
	var pquan = document.forms["purchase__Entry"]["purchaseQuantity"].value;

	if(pquan == '') {
		toastr.error('Purchase Quantity is empty', 'Error', {
			timeOut: 8000
		});
		$('#catModal').modal('hide');
		$('#replenish').css('z-index','1050');
	} else {
		$.ajax({
			url: "stocks/replinish-update/" + uri,
			type: "POST",
			data: formData,
			beforeSend:function(){
				$('#replenish').css('z-index','1050');
				
			},
			success: function(data) {
				$('#replenish').modal('hide');
				$('#pur_quantity').val('');
				$('#en_note').val('');
			},
			complete: function() {
				toastr.success('Item successfully updated', 'Success', {
					timeOut: 8000
				});
			},
			error: function() {
				toastr.error('Oops, error!', 'Error', {
					timeOut: 8000
				});
			}
		});
	}
}

/**
 * Replenish button
 */
function _replinish(id) {

	var uri = unique();
	
	$.ajax({
 		url: "stocks/replinish/" + uri,
 		type: "POST",
 		data: {
 			code: id
 		},
 		dataType: "json",
 		success: function(data) {
 			if(data.msg == 'failed') {
 				alert(data.msg)
 			} else {
 				$('#it_code').val(data.itemCode);
 				$('#catName').val(data.catName);
 				$('#item__Name').val(data.itemName);
 				$('#it_standard').val(data.standard);
 				$('#it_reorder').val(data.reorder);
 				$('#curr_count').val(data.c_count);
 				$('#unitName').val(data.uName);
 				$('#itemID').val(data.itemid);
 			}
 			
 		},
		complete: function() {
			$('#replenish').modal('show');
		},
 		error: function() {
 			toastr.error('Oops, error!', 'Error', {
				timeOut: 8000
			});
 		}
 	});
}

/**
 * Edit item button
 */
function _item_details(id) {

	var uri = unique();
	
	$.ajax({
 		url: "stocks/item-details/" + uri,
 		type: "POST",
 		data: {
 			item_id: id
 		},
 		dataType: "json",
 		success: function(data) {
 			if(data.msg == 'failed') {
 				alert(data.msg)
 			} else {
 				$('#cat_name').val(data.cName);
 				$('#u_name').val(data.uName);
 				$('#it_name').val(data.iName);
 				$('#item_standard').val(data.standard);
 				$('#reorder').val(data.reorder);
 				$('#i_description').val(data.desc);
 				$('#item_id').val(data.itemid);
 			}
 			
 		},
		complete: function() {
			$('#item_Form_Edit').modal('show');
		},
 		error: function() {
 			
 		}
 	});
}

/**
 * Update Item details Event
 */
function _update_item() {
	var formData = $('#editItem').serialize();
	var uri = unique();
		
		$.ajax({
			url: "stocks/item-edit/" + uri,
			type: "POST",
			data: formData,
			beforeSend:function(){
				$('#item_Form_Edit').css('z-index','1050');
				
			},
			success: function() {
				$('#item_Form_Edit').modal('hide');
			},
			complete: function() {
				toastr.success('Item successfully updated', 'Success', {
					timeOut: 8000
				});
			},
			error: function() {
				toastr.error('Oops, error!', 'Error', {
					timeOut: 8000
				});
			}
		});
}

/*
* Validation for reorder point
*/
function _reorder_check(standard, reorder){
		if(reorder >= standard) {
 			$('.err-msg-rpt').html("Reorder point should be less than Standard Quantity.");
	 		$('.item-submit').attr('disabled','disabled');
	 	} else {
	 		$('.err-msg-rpt').html("");
 			$('.item-submit').removeAttr('disabled');
	 	}
}

/*
* Validation for standard quantity
*/
function _standard_check(standard){
	if(standard > 0) {
		$('.err-msg-stnd').html("");
 	} else {
 		$('.err-msg-stnd').html("Standard Quantity should be above 0");
 		$('.item-submit').attr('disabled','disabled');
 	}
}