$(document).ready(function(){

	//For add item to table
	 $('#accordion').on('click', '.itemContainer .dashbtn', function() {
	 	var itemid = $(this).parent().closest('.itemContainer').find('.itemName').attr('itemid');
	  	var code = $(this).parent().closest('.itemContainer').find('.itemName').attr('iname');
	  	// var desc = $(this).parent().closest('.itemContainer').find('.itemDesc').html();
	  	var quantity = $(this).parent().find('input[name=qty_req]').val();

	  	$('.cont .table tbody').append('<tr><td><input type="text" name="item_ids[]" value="' + itemid + '" hidden /><input type="text" name="code[]" value="' + code + '" readonly/></td><td><input type="text" name="qty[]" value="' + quantity + '" readonly /></td><td><button class="del"><i class="far fa-trash-alt"></i></button></td></tr>');

	  });
	 
	 //Removed item in table 
	 $('.cont .table').on('click', '.del', function() {
	  	$(this).closest('tr').remove();
	 });

	 $('.request-submit').on('click',function() {
		$('#yes__NO__REQ').modal({ backdrop: 'static' }, 'show');
		$('#yes__NO__REQ_header').html('Send Request Items');
		$('#yes__NO__REQ_Footer').html('<button data-dismiss="modal" class="btn dashbtn" onclick="_modal_close()">No</button><button data-dismiss="modal" class="btn thmBtn" onclick="_send_request_item()">Yes</button>');
		return false;
	});

});



function gotoDashboard() {
    window.location.href = _domain() + "/dashboard";
}

function _send_request_item() {
	var uri = unique();
	var formData = new FormData($('#request_item')[0]);
	var reqCode = $('input#request_code').val();

	$.ajax({
		   method: "POST",
		   url: "stocks/item-request/" + uri,
		   contentType: false,
		   cache: false,
		   processData: false,
		   data: formData,
		   success: function(data) {
		    
		    $('.result-container').html("<div class='alert alert-success'>"+reqCode+" is now reserved and has been sent to your Manager for approval. Unapproved request(s) within 24 hours will be voided.</div>");
		    
		   },
		   complete: function() {

				setTimeout(location.reload.bind(location), 3000);

			},
		   error: function() {

		    $('.result-container').html("<div class='alert alert-danger'>Something went wrong. Please try again.</div>");

		   }
  	});
	
}
