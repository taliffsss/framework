	<div class="wrapper">
  		
  		<?php echo $navbar; ?>

		<div class="content">
			<div class="row">
				<div class="col-sm-8 cont">
					
					<div id="accordion">
					  <div class="card">

					  	<?php echo $details;?>
					  </div>

					</div>
				</div>
				
		 		<div class="col-sm-4">
		 			<form method="POST" action="" id="request_item">
		 				<div class="cont">
		 					<h4 class="title">Items Selected</h4>
		 					<div class="result-container"></div>
		 					<div class="itemsSelected">
		 						<input type="text" id="request_code" name="request_code" value="<?php echo $req; ?>" hidden/>
		 						<div class="isDetails">Request ID: <?php echo $requestCode; ?></div>
		 						<div class="isTable">
		 							<table class="table" id="request-items-tbl">
		 								<thead>
		 									<tr>
			 									<th class="th_item">Item</th>
			 									<th class="th_qty">Qty/Unit</th>
			 									<th class="th_btn"></th>
		 									</tr>
		 								</thead>
		 								<tbody>
		 								</tbody>
		 							</table>
		 							<div class="submitCont">
		 								<button class="btn dashbtn" type="button" onclick="gotoDashboard()" style="margin-right: 10px;">
										    Cancel
										</button>
								  		<input type="submit" name="submit" class="btn thmBtn request-submit" value="Send Request">       
		 							</div>
		 						</div>
		 					</div>
		 				</div>
		 			</form>
		 			
		 		</div>
	 			
			</div>
		</div>
	</div>