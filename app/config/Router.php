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

//Request route
Routes::add('requisition-form','admin/Request::index');

//Logout route
Routes::add('logout','admin/Login::signout');

//Userlist route
Routes::add('users-list','admin/User_list::index');

//Upload route
Routes::add('upload','admin/Csv::index');

?>