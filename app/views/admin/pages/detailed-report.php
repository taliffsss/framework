	<div class="wrapper">
  		
  		<?php echo $navbar; ?>
  		
		<div class="content">
			<div class="row">
				<div class="col-sm-9 cont">

					<h4 class="title">Inventory Detailed Report</h4>

					<form method="POST" action="" id="gen_detailed_report">
						<div class="table_filter">
							<div class="lbl">
								<span>Category</span>
								<?php echo $category; ?>
							</div>
							<div class="lbl">
								<span>Date From</span>
								<input type="date" value="<?php echo date("Y-m-d"); ?>" name="date_from" id="date_from">
							</div>
							<div class="lbl">
								<span>Date To</span>
								<input type="date" value="<?php echo date("Y-m-d"); ?>" name="date_to" id="date_to">
							</div>
						</div>

						<table id="table_id" class="table">
						    <thead>
						        <tr>
						            <th>Request ID</th>
						            <th>Tranasaction</th>
						            <th>Date of Purchase/Issuance</th>
						            <th>Category</th>
						            <th>Item</th>
						            <th>Quantity</th>
						            <th>Requesting Individual</th>
						            <th>Assignee/Outlet Name</th>
						        </tr>
						    </thead>
						    <tbody>
						        <?php echo $detailed_row; ?>
						    </tbody>
						</table>
						<div class="row  justify-content-center detailed_btn">
							<div class="col-md-4" style="text-align: center; margin-top: 20px;">
								<input type="submit" name="run_detailed" id="run_detailed" class="btn thmBtn" value=" Run Report">       
								<button class="btn thmBtn download" type="button" id="download_detailed">
								  Download
								</button>
							</div>
						</div>
					</form>
				</div>
	 			<?php echo $ticker; ?>
			</div>
		</div>
  	</div>