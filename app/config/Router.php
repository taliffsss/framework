<?php
//login route
Routes::add('login','admin/Login::index');

//forgot password route
Routes::add('forgot-password','admin/Forgot::index');

//Dashboard route
Routes::add('dashboard','admin/Dashboard::index');

//Request log route
Routes::add('request-logs','admin/Request_logs::index');
Routes::add('request-logs/view/(:any)','admin/Request_logs::view');
Routes::add('request-logs/rejected/(:any)','admin/Request_logs::rejected');
Routes::add('request-logs/approved-all/(:any)','admin/Request_logs::approved_all');
Routes::add('request-logs/reject-all/(:any)','admin/Request_logs::reject_all');

//Request route
Routes::add('requisition-form','admin/Request::index');
Routes::add('stocks/item-request/(:any)','admin/Request::submit_request');

//Logout route
Routes::add('logout','admin/Login::signout');

//Userlist route
Routes::add('users-list','admin/User_list::index');

//Upload route
Routes::add('upload','admin/Csv::index');

//Reports route
Routes::add('detailed-report','admin/Report::index');
Routes::add('turnaround-report','admin/Report::turnaround');
Routes::add('summary-report','admin/Report::summary');

//check empid route
Routes::add('signup','admin/Registration::register');
Routes::add('employee/id/(:any)','admin/Registration::check_empid');
Routes::add('employee/username/(:any)','admin/Registration::check_username');
Routes::add('activation/token/(:any)','admin/Registration::activation_link');

//Password retrieval
Routes::add('password-recovery/verify/(:any)','admin/Password_retrieval::check_email');
Routes::add('password-recovery/error/(:any)','admin/Password_retrieval::password_retrieval');
Routes::add('password-recovery/reset/(:any)','admin/Password_retrieval::admin_reset');

//Category route
Routes::add('stocks/addcat/(:any)','admin/Category::add');
Routes::add('stocks/unit/(:num)','admin/Stock::opt_unit');
Routes::add('stocks/additem/(:any)','admin/Stock::add_item');

//Unit Route
Routes::add('unit/new/(:any)','admin/Unit::add');
?>