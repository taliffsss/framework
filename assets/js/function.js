$(document).ready(function(){
	
	/**
	 * Logout link
	 */
	$('#logout').on('click', function(){
		window.location.href = _domain()+"/logout";
	});

	/**
	 * Employee ID validation
	 */
	$("#employee_id").on('blur',function(){

	 	var val = $(this).val();

	 	if(val != '') {
	 		_empid_verify(val);
	 	} else {
	 		$('.err-msg-empid').html("");
	 		$('.err-msg-regForm').html("");
	 		$('.c_reg').html("");
	 	}

	});
	/**
	 * Email validation
	 */
	$("#email").on('blur',function(){

	 	var email = $(this).val();

	 	if(email != '') {
	 		_checked_email(email);
	 	} else {
	 		$('.c_forgot').hide();
	 		$('.err-msg-email').html("");
	 	}

	});

	/**
	 * Change password function
	 */
	$('form#change__pass').submit(function(e) {
		e.preventDefault();
		var formData = $('#change__pass').serialize();
		var url = window.location.pathname.split('/');
		var token = 'CSRF token expired';
		
		$.ajax({
			url: url[4],
			method: "POST",
			data: formData,
			beforeSend:function(){
				$('#disabled-btn').attr('disabled','disabled');
				$('#disabled-btn').val('loading...');
			},
			success: function(data) {
				$('form#change__pass')[0].reset();
				$('.success-msg').text('Password has been reset');
				
			},
			complete: function() {
				$('#exampleModalCenter').modal('show');
				ajax_redirect();
			},
			error: function() {
				$('form#change__pass')[0].reset();
				$('.success-msg').text(token);
			}

		});
		
	});

	/**
	 * Forgot password function
	 */
	$('form#forgot__pass').submit(function(e) {
		e.preventDefault();
		var formData = $('#forgot__pass').serialize();
		var answer = document.forms["forgot__pass"]["answer"].value;
		var uri = unique();
		var failed = "Maximum attempt reached. Contact Kristel Catral of Back Office ADMIN for assistance.";
		var token = 'CSRF token expired';
		var invalid = "Invalid security answer.";
		var success = "Password Reset Verification has been sent to your email.";

		if(answer != '') {
			$.ajax({
				url: _domain()+"/password-recovery/error/" + uri,
				method: "POST",
				data: formData,
				beforeSend:function(){
					$('#disabled-btn').attr('disabled','disabled');
					$('#disabled-btn').val('loading...');
				},
				success: function(data) {
					alert(data);
					if(data == success) {
						$('.err-msg-email').html("<div class='alert alert-success' role='alert'>"+data+"</div>");
						$('form#forgot__pass')[0].reset();
						$('.c_forgot').hide();
						ajax_redirect()
					} else if(data == failed) {
						$('.err-msg-email').html("<div class='alert alert-danger' role='alert'>"+data+"</div>");
						$('.c_forgot').hide();
					} else if(data == token) {
						$('.err-msg-email').html("<div class='alert alert-danger' role='alert'>"+data+"</div>");
						$('#disabled-btn').removeAttr('disabled');
						$('#disabled-btn').val('Reset Password');
					} else if(data == invalid) {
						$('.err-msg-email').html("<div class='alert alert-danger' role='alert'>"+data+"</div>");
						$('#security_answer').val('');
						$('#disabled-btn').removeAttr('disabled');
						$('#disabled-btn').val('Reset Password');
					}
					
				},
				error: function() {
					$('#msg__forgot').text(invalid);
				}
			});
		} else {
			$('.err-msg-email').html("<div class='alert alert-danger' role='alert'>Security Answer is required.</div>");
		}
	});

	/**
	 * Registration function
	 */
	$('form#regForm').submit(function(e) {
		e.preventDefault();
		var formData = $('#regForm').serialize();
		var error = 'Oops, Error Occured!';
		var token = 'CSRF token expired';
		var success = 'Account Verification has been sent to your email';
		var username = document.forms["regForm"]["username"].value,
			password = document.forms["regForm"]["password"].value,
			cpass = document.forms["regForm"]["cpass"].value,
			question = document.forms["regForm"]["question"].value,
			answer = document.forms["regForm"]["answer"].value;

		if(username != '' && password != '' && cpass != '' && question != '' && answer != '') {

			$.ajax({
				type: "POST",
				url: "signup",
				data: formData,
				beforeSend:function(){
					$('.disabled-btn-reg').attr('disabled','disabled');
					$('.disabled-btn-reg').val('loading...');
				},
				success: function(data) {
				    
					if(data == success) {
						$('.msg-reg').text(success);
						$('.c_reg').hide();
						$('.msg-reg').css('color', 'white');
						$('form#regForm')[0].reset();
						$('.err-msg-regForm').html('');
						ajax_redirect()
					} else if(data == error) {
						$('.msg-reg').text(error);
						$('.msg-reg').css('color', 'red');
						$('.disabled-btn-reg').removeAttr('disabled');
					} else if(data == token) {
						$('.msg-reg').text(token);
						$('.msg-reg').css('color', 'red');
					}
				},
				error: function() {
					$('#msg-reg').text(error);
					$('#msg-reg').css('color', 'red');
				}

			});
		} else {
			$('.err-msg-regForm').html('All fields are required!');
		}
	});

	$('.cat-submit').on('click',function() {
		$('#yes__NO').modal({ backdrop: 'static' }, 'show');
		$('#yes__NO_header').html('Add Category');
		$('#yes__NO_body').html('Are you sure you want to add <b>'+$('#cname').val()+'</b>?');
		$('#category_Form_Add').css('z-index','100');
		$('#yes__NO_Footer').html('<button data-dismiss="modal" class="btn thmBtn" onclick="_cat_add()">Yes</button><button data-dismiss="modal" class="btn btn-default" onclick="_modal_close()">No</button>')
	});

	$('.unit-btn').on('click',function() {
		$('#yes__NO').modal({ backdrop: 'static' }, 'show');
		$('#yes__NO_header').html('<b>Add Unit</b>');
		$('#yes__NO_body').html('Are you sure you want to add <b>'+$('#uName').val()+'</b>?');
		$('#unit_Form_Add').css('z-index','100');
		$('#yes__NO_Footer').html('<button data-dismiss="modal" class="btn thmBtn" onclick="_unit_add()">Yes</button><button data-dismiss="modal" class="btn btn-default" onclick="_modal_close()">No</button>')
	});

	$('.item-submit').on('click',function() {
		var iname = $('#iname').val();
		$('#yes__NO').modal('show');
		$('#yes__NO_header').html('Add Item');
		$('#yes__NO_body').html('Are you sure you want to add <b>'+iname+'</b>?');
		$('#item_Form_Add').css('z-index','100');
		$('#yes__NO_Footer').html('<button data-dismiss="modal" class="btn thmBtn" onclick="_item_add()">Yes</button><button data-dismiss="modal" class="btn btn-default" onclick="_modal_close()">No</button>')
	});

	/**
	 * Close modal
	 */
	$('#btn-val').click(function(){
		$('#exampleModalCenter').modal('hide');
	});

	/**
	 * note check val length
	 */
	$('#en_note').on('blur',function(evt){
		var len = $(this).val().length;

		if(len == '') {
			$('.err-msg-en_note').html('');
		} else {
			if(len < 5) {
				$('.purchase-btn').attr('disabled','disabled');
				$('.err-msg-en_note').html('Minimum letters is 5 maximum is 200');
			} else {
				$('.purchase-btn').removeAttr('disabled');
				$('.err-msg-en_note').html('');
			}
		}

	});


	/**
	 * Quantity check int only
	 */
	$('#pur_quantity').on('keypress',function(evt){
		var quan = $(this).val() == "" ? 49 : 48;
		var code = (evt.which) ? evt.which : event.keyCode
		if(code > 31 && (code < quan || code > 57))
			return false;
		return true;

	});

	/******
	* Category Form Validations
	*******/
	$("#cname").on('blur',function(){

	 	var val = $(this).val();

	 	_cat_verify(val);

	});

	/******
	* Stock Form Validations
	*******/

	$("#iname").on('blur',function(){

	 	var val = $(this).val();

	 	if(val != ""){
	 		if(val.length > 3) {
	 			$('.err-msg-name').html("");
	 			_item_verify(val);
		 	} else {
		 		$('.err-msg-name').html("Item name should be 4 to 20 characters.");
		 		$('input:submit').attr("disabled", 'disabled'); 
		 	}	
	 	}else{
	 		$('.err-msg-name').html("");
	 	}
	 	

	});

	$("#s__qty").on('blur',function(){
	 	var val = $(this).val();

	 	if(val != ''){
	 		_standard_check(val);	
	 	}else{
	 		$('.err-msg-stnd').html("");
	 	}
	 	
	});

	$("#r__pt").on('blur',function(){
	 	var val = parseInt($(this).val());
	 	var standard = parseInt($('#s__qty').val());
		
		if(val != ''){
			_reorder_check(standard, val);	
		}else{
			$('.err-msg-rpt').html("");
		}
		
	});

	/**
	* Populate Unit Name option
	**/
	$('.category_name').change(function(){
		var category_id = this.value;
		
		$.ajax({
			url: "stocks/unit/"+category_id,
			method: "POST",
			data: { 
				category_id: category_id
			},
			success: function(result){
				$('.item_unit').html(result);
			}
		});

	});

});

let segment = document.querySelector('#segment-hidden').value;

let current_url = window.location.href;

/**
 * Check if confirm password and password are equal
 */
function _matching_pass() {
	var cpass = $("#confirm_password").val();
	var pass = $("#new_password").val(),
		uname = $('#new_username').val();
	let url_path = new URL(current_url).pathname;
	let c = url_path.split("/");
	var pattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{6,})/;

	if(pass != '') {
		if(cpass.length < 5) {
			$('.err-msg-cpass').html("Password should be a combination of 6-10 symbols, uppercase, lowercase, alphanumeric character.");
			$('.disabled-btn-reg').attr('disabled','disabled');
		    
		}else {
			if(pattern.test(cpass)) {
            
                if(cpass != pass) {
        			$('.err-msg-cpass').html("Password not match!");
        			$('.disabled-btn-reg').attr('disabled','disabled');

        		}else{
    				$('.err-msg-cpass').html("");

    				if('reset-password' != c[2]) {
    					if(uname.length > 5) {
	    					$('.disabled-btn-reg').removeAttr('disabled');
	    				} else {
	    					$('.disabled-btn-reg').attr('disabled','disabled');
	    				}
    				} else {
    					$('.disabled-btn-reg').removeAttr('disabled');
    				}
        		}

			} else {
			    
				$('.err-msg-cpass').html("Password should be a combination of 6-10 symbols, uppercase, lowercase, alphanumeric character.");
			    $('.disabled-btn-reg').attr('disabled','disabled');
			    
			}
		}
	} else {
		$('.err-msg-cpass').html("Empty password!");
		$('.disabled-btn-reg').attr('disabled','disabled');
	}
}
/**
 * Check if username are available
 */
function _username_verify(val) {

	var uri = unique();

	$.ajax({
		url: "employee/username/" + uri,
		method: "POST",
		data: {
			uname: val
		},
		dataType: "json",
		success: function(data) {
			if(data.msg == 'Username already exist.') {
				$('.err-msg-uname').html("Username already exists.");
				$('.disabled-btn-reg').attr('disabled','disabled');
			} else {
				$('.err-msg-uname').html('');
				$('.disabled-btn-reg').removeAttr('disabled');
			}
		},
		error: function() {
			$('.err-msg-uname').html('');
		}
	});
}

/**
 * Password Validation mixed alpha numeric, lower upper case letter and number
 */
function _password_Check(){

	var pass = $("#new_password").val(),
		cpass = $("#confirm_password").val();

	var pattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{6,})/;
	
	if(pass != '') {
		if(pass.length > 5) {
			if(pattern.test(pass)) {
				$('.err-msg-pass').html("");
				if(cpass != '') {
					_matching_pass()
				} else {
					$('.disabled-btn-reg').removeAttr('disabled');
				}

			} else {
				$('.err-msg-pass').html("Password should be a combination of 6-10 symbols, uppercase, lowercase, alphanumeric character");
				$('.disabled-btn-reg').attr('disabled','disabled');
			}
		} else { 
			$('.err-msg-pass').html("Password should be a combination of 6-10 symbols, uppercase, lowercase, alphanumeric character ");
			$('.disabled-btn-reg').attr('disabled','disabled');
		}
	} else {
		$('.err-msg-pass').html("");
		$('.disabled-btn-reg').removeAttr('disabled');
	}
}

/**
 * Check if employee id are existed
 */
function _empid_verify(val) {

	var uri = unique();

	$.ajax({
 		url: "employee/id/" + uri,
 		type: "POST",
 		data: {
 			empid: val
 		},
 		success: function(data) {
 			if(data == 'User already registered!') {
 				$('.err-msg-empid').html(data);
 				$('.c_reg').html("");
 			} else {
 				$('.c_reg').html(data);
 				$('.err-msg-empid').html("");
 			}
 			
 		},
 		error: function() {
 			$('.err-msg-empid').html("Employee ID does not exist.");
 			$('.c_reg').html("");
 		}
 	});
}

function _username_Check() {

 	var pattern = /^[a-z0-9A-Z]+$/,
 		val = $('#new_username').val(),
 		pass = $("#new_password").val(),
 		cpass = $("#confirm_password").val();

 	if(val != '') {
 		if(val.length > 5) {
	 		if(pattern.test(val)) {
	 			_username_verify(val);
	 			if(pass.length <= 10  && pass.length >= 6) {
	 				_matching_pass()
	 			}
	 		}
	 	} else {
	 		$('.err-msg-uname').html("Username should be 6-12 alphanumeric characters.");
	 		if(pass != '' || cpass != '') {
	 			if(pass.length <= 10  && pass.length >= 6) {
	 				_matching_pass()
	 			}
	 		} else {
	 			$('.disabled-btn-reg').attr('disabled','disabled');
	 		}
	 	}
	 } else {
	 	$('.err-msg-uname').html("");
	 	$('.disabled-btn-reg').removeAttr('disabled');
	 }
}

/**
 * Check if category is alraedy available
 */
function _cat_verify(val) {

	var uri = unique();

	if(val == '') {
		toastr.error('Empty fields', 'Error', {
			timeOut: 8000
		});
	} else {

		$.ajax({
			url: "stocks/catname/" + uri,
			method: "POST",
			data: {
				cname: val
			},
			dataType: "json",
			success: function(data) {
				if(data.msg == 'Category name already exists.') {
					$('.err-msg-name').html("Category name already exists.");
					$('#cat-submit').attr('disabled','disabled');
				} else {
					$('.err-msg-name').html('');
					$("input[type=submit]").removeAttr('disabled');
				}
			},
			error: function() {
				$('err-msg-name').html('Something went wrong.');
			}
		});
	}

}

/**
 * Check if category is alraedy available
 */
function _item_verify(val) {

	var uri = unique();

	if(val == '') {
		toastr.error('Empty fields', 'Error', {
			timeOut: 8000
		});
	} else {
		$.ajax({
			url: "stocks/itemname/" + uri,
			method: "POST",
			data: {
				iname: val
			},
			dataType: "json",
			success: function(data) {
				if(data.msg == 'Item name already exist.') {
					$('.err-msg-name').html("Item name already exist.");
					$('#item-submit').attr('disabled','disabled');
				} else {
					$('.err-msg-name').html('');
					$("input[type=submit]").removeAttr('disabled');
				}
			},
			error: function() {
				$('err-msg-cat').html('');
			}
		});
	}
}

//For category submit
$('.modal-footer button').on('click', function(event) {
  var $button = $(event.target);
  var formData = $('form#addCategory').serialize();
  
  if($button[0].id == 'confirm-button'){
  		var uri = unique();
  		
		$.ajax({
			url: "stocks/addcat/" + uri,
			method: "POST",
			data: formData,
			success: function(data) {
				$('#category').css('z-index','1050');
				$('#msg-cat').addClass('alert alert-success');
				$('#msg-cat').text('Category has been added.');
			},
			error: function() {
				$('#msg-cat').addClass('alert alert-danger');
				$('#msg-cat').text('Failed to add.');
			}

		});
  } else {
  	$('#category').css('z-index','1050');
  }
});

/**
 * Login form field validation
 */
function loginEvent() {
	var uname = document.getElementById("username").value;
	var pass = document.getElementById("password").value;

	if(uname == '' || pass == '') {
		document.getElementById("p-msg").innerHTML = ' <div class="alert alert-danger" role="alert">All fields are required</div>';
		return false;
	} else {
		return true;
	}
}

/**
 * Unit Add Event
 */
function _unit_add() {
	var formData = $('#unitFormAdd').serialize();
	var uri = unique();
	var unit = $('#uName').val(),
		desc = $('#unit_desc').val();

	if(unit.length == '') {
		$('#unit_Form_Add').css('z-index','1050');
		toastr.error('Empty fields', 'Error', {
			timeOut: 8000
		});
	} else {
		$.ajax({
			url: "unit/new/" + uri,
			method: "POST",
			data: formData,
			beforeSend:function(){
				$('#unit_Form_Add').css('z-index','1050');
				$('#unit_Form_Add').modal('hide');
			},
			success: function() {
				$('form#unitFormAdd')[0].reset();
			},
			complete: function() {
				$('#unit_Form_Add').css('z-index','1050');
				$('#unit_Form_Add').modal('hide');
				toastr.success('<b>'+unit+'</b> unit successfully added.', 'Success', {
					timeOut: 5000
				});
				setTimeout(location.reload.bind(location), 3000);
			},
			error: function() {
				toastr.error('Oops, error!', 'Error', {
					timeOut: 8000
				});
			}
		});
	}
}

function _cat_add() {
	var formData = $('#addCategory').serialize();
	var uri = unique();
	var category = $('#cname').val(),
		desc = $('#cdesc').val();

	if(category.length == '') {
		$('#category_Form_Add').css('z-index','1050');
		toastr.error('Empty fields', 'Error', {
			timeOut: 8000
		});
	} else {
		$.ajax({
			url: "stocks/addcat/" + uri,
			method: "POST",
			data: formData,
			success: function(data) {
				$('form#addCategory')[0].reset();
			},
			complete: function() {
				$('#category_Form_Add').css('z-index','1050');
				$('#category_Form_Add').modal('hide');
				toastr.success('<b>'+category+'</b> has been added to the Inventory.', 'Success', {
					timeOut: 5000
				});
				setTimeout(location.reload.bind(location), 3000);
			},
			error: function() {
				toastr.error('Oops, error!', 'Error', {
					timeOut: 8000
				});
			}
		});
	}
}

function _item_add() {
	var formData = $('#addStock').serialize();
	var uri = unique(),
		category_id = document.forms["addStock"]["category_name"].value,
		item_unit = document.forms["addStock"]["unit_name"].value,
		item_name = document.forms["addStock"]["iname"].value,
		standard = document.forms["addStock"]["s__qty"].value,
		reorder = document.forms["addStock"]["r__pt"].value;

	if(category_id.length == '' || item_unit.length == '' || item_name.length == '' || standard.length == '' || reorder == '') {
		$('#item_Form_Add').css('z-index','1050');
		toastr.error('Empty fields', 'Error', {
			timeOut: 8000
		});
	} else {
		$.ajax({
			url: "stocks/additem/" + uri,
			method: "POST",
			data: formData,
			success: function(data) {
				$('form#addStock')[0].reset();
			},
			complete: function() {
				$('#item_Form_Add').css('z-index','1050');
				$('#item_Form_Add').modal('hide');
				toastr.success('<b>'+item_name+'</b> item successfully added.', 'Success', {
					timeOut: 5000
				});
				setTimeout(location.reload.bind(location), 3000);
			},
			error: function() {
				toastr.error('Oops, error!', 'Error', {
					timeOut: 5000
				});
			}
		});
	}
}

function _modal_close() {
	$('#unit_Form_Add').css('z-index','1050');
	$('#category_Form_Add').css('z-index','1050');
	$('#item_Form_Add').css('z-index','1050');
}

/**
 * Check if confirm password and password are equal
 */
function _checked_email(email) {

	var uri = unique();
	var locked = 'Your account is temporary locked.';
	var verified = 'Email verified!';
	var not = 'Email address is not yet registered.';

	$.ajax({
		url: _domain()+"/password-recovery/verify/" + uri,
		type: "POST",
		data: {
 			email: email
 		},
 		dataType: "json",
 		success: function(data) {
 			if(data.msg == verified) {
 				$('#sec__ques').val(data.question);
 				$('.c_forgot').show();
 				$('.err-msg-email').html("");
 			} else if(data.msg == locked){
 				$('.c_forgot').hide();
 				$('.err-msg-email').html("<div class='alert alert-danger' role='alert'>"+locked+"</div>");
 			} else if(data.msg == not){
 				$('.c_forgot').hide();
 				$('.err-msg-email').html("<div class='alert alert-danger' role='alert'>"+not+"</div>");
 			} else {
 				$('.c_forgot').hide();
 				$('.err-msg-email').html("<div class='alert alert-danger' role='alert'>Email address does not exist.</div>");
 			}
 			
 		},
 		error: function() {
 			$('.c_forgot').hide();
 			$('.err-msg-email').html("<div class='alert alert-danger' role='alert'>Email address does not exist.</div>");
 		}
	});
}

function _error_msg(msg) {
	$('.success-msg').text(msg);
}

/**
 * Forgot Password link
 */
function forgot_pass_link() {
	window.location.href = "forgot-password/";
}

/**
 * Domain
 */
function _domain() {

	//var link = 'http://dev.mswlive.com/admin-inventory';
	var link = 'http://localhost:8080/ais-tal';

	return link;
}

/**
 * ajax redirect
 */
function ajax_redirect() {
	window.setTimeout(function() {
		window.location = "http://localhost:8080/ais-tal"
	}, 5000)
}

/**
 * Generate random string
 */
function unique() {
	function create() {
		return Math.floor((1 + Math.random()) * 0x10000) .toString(16) .substring(1);
	}
	function combine() {
		return Math.random().toString(36).substr(2, 9);
	}
	return combine()+create();
}