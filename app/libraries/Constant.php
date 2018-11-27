<?php

namespace Mark\libraries;

class Constant {

	//Modules
	const M_STOCKS = 'Stocks';
	const M_REQUEST = 'Request';
	const M_REQUEST_LOG = 'Request Log';

	//Action
	const A_UPLOAD = 'Upload';
	const A_CREATE = 'Create';
	const A_READ = 'Read';
	const A_UPDATE = 'Update';
	const A_DELETE = 'Delete';
	const A_APPROVED = 'Approved All';

	//PARAM
	const PARAM_ID = 'id';
	const PARAM_UNAME = 'username';
	const PARAM_VERIFY = 'verify';
	const PARAM_ADD = 'new';
	const PARAM_TOKEN = 'token';
	const PARAM_RESET = 'reset';
	const PARAM_RESET_FORCE = 'uid';
	const PARAM_ERROR = 'error';
	const PARAM_CATEGORY = 'catname';
	const PARAM_UNIT = 'unit';
	const PARAM_ITEM = 'itemname';
	const PARAM_INSERT_CATEGORY = 'addcat';
	const PARAM_INSERT_ITEM = 'additem';
	const PARAM_REPLINISH = 'replinish';
	const PARAM_REPLINISH_UPDATE = 'replinish-update';
	const PARAM_ITEM_DETAILS = 'item-details';
	const PARAM_ITEM_EDIT = 'item-edit';
	const PARAM_ITEM_REQ = 'item-request';
	const PARAM_VIEW = 'view';
	const PARAM_DEDUCT = 'deduct';
	const PARAM_UNIT_VERIFY = 'unit-name';

	//REMARKS
	const FAILED = 'failed';
	const SUCCESSFULL = 'successfull';

	//FORM MESSAGE
	const SUCCESS_REGISTER = "Account Verification has been sent to your email";
	const SUCCESS_RESET = "Password Reset Verification has been sent to your email.";
	const VERIFIED = "Account verified!";
	const SUCCESS_RESET_ADMIN = "Password Reset Verification has been sent.";
	const RESET_FAILED = "Maximum attempt reached. Contact Kristel Catral of Back Office ADMIN for assistance.";
	const LOCKED = "Your account is temporary locked.";
	const SECURITY_ANSWER = "Invalid security answer.";
	const INACTIVE = "Your account is not yet activated.";
	const PASSWORD_RETRIEVAL = "Password Retrieval Verification";
	const ALREADY_REGISTER = "User already registered!";
	const ERROR_MSG = "Oops, Error Occured!";
	const USERNAME = "Username already exist.";
	const UNIT_EXIST = "Unit name already exist.";
	const EMAIL_CONFIRM = "Email verified!";
	const INVALID_CREDENTIAL = "<div class='alert alert-danger' role='alert'>Username / Password is incorrect</div>";
	const NOT_REGISTERED = "Email address is not yet registered.";
	const CSRF_EXPIRED = "CSRF token expired";
	const RECAPTCHA = "<div class='alert alert-danger' role='alert'>The reCAPTCHA field is telling me that you are a robot</div>";
	const CATEGORY = "Category name already exists.";
	const ITEM = "Item name already exist.";
	const UNIT_NAME = "Unit name already exist.";

	//REQUEST ITEM STATUS
	const PENDING = "Approval Pending – Items Reserved."; //Manger Queue
	const REQUEST_EXPIRED_NOT_AVAILABLE = "Request Expired – No stocks Available"; // Manager Queue
	const APPROVED = "Approved – For Preparation."; //Admin 1 - When Manager send to Admin
	const ISSUANCE = "Ready for Issuance"; //Admin 2 - When admin already approved
	const COMPLETED = "Completed"; // Admin 3 - when ADMIN click update inventory 
	const DISAPPROVED = "Disapproved";
	
    //IMPORT MESSAGE
	const SUCC_IMPORT = "<div class='alert alert-success' role='alert'>File is successfully imported.</div>";
	const FAIL_IMPORT = "<div class='alert alert-danger' role='alert'>File is not imported successfully.</div>";
	const EMPTY_IMPORT = "<div class='alert alert-danger' role='alert'>Some data is not save successfully. Please check per row if empty and correct.</div>";
	const CVS_ONLY = "<div class='alert alert-danger' role='alert'>File uploaded is not allowed. Please upload only csv file.</div>";
	const CVS_REQUIRED = "<div class='alert alert-danger' role='alert'>File is required.</div>";
	const CVS_DUPLICATE = "<div class='alert alert-danger' role='alert'>Please check you csv file for duplicate entries.</div>";
	const CVS_DB_ERROR = "<div class='alert alert-danger' role='alert'>Please double check your data. Some column has wrong values.</div>";
	const CHECK_CATEGORY_COLUMN = "<div class='alert alert-danger' role='alert'>Please check your file, there are some categories that is not in the database. Number of category:";

	//POSITION
	const HEAD = "Department Head";
	const MNGR = "Manager";
	
	//ERROR LOG
	const ERROR_LOG = 'log-';

	//FOLDER PATH
	const FOLDER_PATH = '../../assets/';

	//TYPE OF TRANSACTION
	const PURCHASED = 'Purchased';
	const ISSUED = 'Issued';

}

?>