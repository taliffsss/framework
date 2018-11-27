		<div class="wrapper main">
				<input type="checkbox" class="slide" id="slide" />
				<label for="slide" class="label_slide">
					<i class="fas fa-angle-right"></i>
				</label>

				<section class="s_register">
					<div class="content">
						<h3>User Registration</h3>
						<p class="msg-reg"></p>
						<form method="POST" action="" id="regForm" name="regForm">
							<input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo Session::userdata('csrf_name'); ?>">
							<div class="form">
								<div class="input_div input_check">
								<span class="input">
									<input class="input__field" type="text" id="employee_id" name="empid" onkeypress='return event.charCode >= 48 && event.charCode <= 57' autofocus>
									<label class="input__label" for="employee_id">
									<span class="input__label-content">Employee ID</span>
									</label>
								</span>
								<div class="text-danger err-msg-empid"></div>
							</div>
							<div class="text-danger err-msg-regForm"></div>
							<div class="c_reg">
								
							</div>
						</form>
					</div>
				</section>

				<section class="s_login">
					<div class="logo">
						<img src="<?php echo Url::baseUrl(); ?>/assets/img/logo-white.png">
					</div>

					<?php echo Session::flash('success'); ?>

					<div class="form">
						<form method="POST" action="" id="loginform" onsubmit=" return loginEvent()">
							<div id="p-msg"></div>
							<input type="hidden" name="csrf_token" value="<?php echo Session::userdata('csrf_name'); ?>">
							<div class="input_div">
								<span class="input">
									<input class="input__field" type="text" id="username" name="username" value="<?php echo Input::set_value('username'); ?>">
									<label class="input__label" for="username">
									<span class="input__label-content">Username</span>
									</label>
								</span>
							</div>

							<div class="input_div">
								<span class="input">
									<input class="input__field" type="password" id="password" name="password">
									<button class="showPassword" onclick="viewPassword('password'); return false"><i class="fas fa-eye"></i></button>
									<label class="input__label" for="password" data-eye>
									<span class="input__label-content">Password</span>
									</label>
								</span>
								<span class="sub_input"><a href="#" onclick="forgot_pass_link()">Forget Password</a></span>
							</div>
							<div class="input_div">
								<div class="g-recaptcha" data-sitekey="6Lei1l8UAAAAAIIfrwYyohsPInLjj_HiuCQsSN1A"></div>
							</div>
							<div class="input_div input__submit-container">
								<input class="input__submit" type="submit" value="Login" name="submit">
							</div>
						</form>
					</div>
				</section>
			</div>