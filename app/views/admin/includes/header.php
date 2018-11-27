<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>MSW — Admin Inventory System</title>
		<meta http-equiv="x-dns-prefetch-control" content="on">
		<link rel="dns-prefetch" href="//use.fontawesome.com">
		<link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
		<link rel="dns-prefetch" href="//cdn.datatables.net">
		<link rel="dns-prefetch" href="//www.google.com">
		<link rel="dns-prefetch" href="//ajax.googleapis.com">
		<link href="<?php echo Url::baseUrl(); ?>/assets/css/bootstrap.min.css" rel="stylesheet">
		<link href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet">
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.3.1/css/all.css" integrity="sha384-mzrmE5qonljUremFsqc01SB46JvROS7bZs3IO2EmfFsd15uHvIt+Y8vEf7N7fWAU" crossorigin="anonymous">
		<link href="<?php echo Url::baseUrl(); ?>/assets/css/style.css" rel="stylesheet">
		<link href="<?php echo Url::baseUrl(); ?>/assets/css/loader.css" rel="stylesheet">
		<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet">
	    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.1.0/css/all.css" integrity="sha384-lKuwvrZot6UHsBSfcMvOkWwlCMgc0TaWr+30HWe3a4ltaBwTZhyTEggF5tJv8tbt" crossorigin="anonymous">
		<script src='https://www.google.com/recaptcha/api.js'></script>
	</head>
	<?php if(Url::uri_segment(2) == "login" || Url::uri_segment(2) == "forgot-password" || Url::uri_segment(2)== "reset-password"){ ?> 
		<body class="lgn">
	<?php }else if(Url::uri_segment(2) == "requisition-form"){ ?> 
		<body class="dashboard user admin">
	<?php }else{ ?>
		<body class="dashboard">
	<?php } ?>
	<input type="hidden" id="segment-hidden" value="<?php echo Session::userdata('role'); ?>">