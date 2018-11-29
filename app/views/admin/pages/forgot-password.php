		<div class="wrapper main">
			<section class="s_login s_password">
				<div class="logo">
					<img src="<?php echo Url::baseUrl(); ?>/assets/img/logo-white.png">
				</div>
				<h3>Password Retrieval</h3>
				
				<div class="forgot-msg"></div>

				<div class="backBtn" style="margin-bottom: -5%;">
				    <a href="#" onclick="window.history.go(-1); return false;">Back to Login</a>
				</div>
				
				<div class="form">
					<form method="POST" action="" id="forgot__pass" name="forgot__pass">
						<input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo Session::userdata('csrf_name'); ?>">
						<div class="input_div">
							<span class="input">
								<input class="input__field" type="email" id="email" name="email"/>
								<label class="input__label" for="email">
									<span class="input__label-content">Email Address</span>
								</label>
							</span>
						</div>
						<div class="c_forgot">
							<div class="input_div">
								<span class="input input--filled">
									<input class="input__field" type="text" id="sec__ques" name="question" readonly/>
									<label class="input__label" for="sec__ques">
										<span class="input__label-content">Security Question</span>
									</label>
								</span>
							</div>

							<div class="input_div">
								<span class="input">
									<input class="input__field" type="password" id="security_answer" name="answer" autocomplete="off"/>
									<button class="showPassword" onclick="viewPassword('security_answer'); return false"><i class="fas fa-eye"></i></button>
									<label class="input__label" for="security_answer">
									<span class="input__label-content">Security Answer</span>
									</label>
								</span>
							</div>

							<div class="input_div input__submit-container">
								<input class="input__submit" type="submit" id="disabled-btn" value="Reset Password">
							</div>
						</div>
					</form>
				</div>
			</section>
		</div>