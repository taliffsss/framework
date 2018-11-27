		<div class="wrapper">

	  		<?php echo $navbar; ?>

			<div class="content">
				<div class="row">
					<div class="col-sm-9 cont">
						
						<table id="stock_list" class="table">
						    <thead>
						        <tr>
						            <th>Employee ID</th>
						            <th>Full Name</th>
						            <th>Department</th>
						            <th>Unit Name</th>
						            <th>Position</th>
						            <th>Action</th>
						        </tr>
						    </thead>
						    <tbody>
						        <?php echo $userlist; ?>
						    </tbody>
						</table>

					</div>
					<?php echo $ticker; ?>
				</div>
			</div>
	  	</div>