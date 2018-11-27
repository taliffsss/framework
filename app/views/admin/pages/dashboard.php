<div class="wrapper">
  		
  		<?php echo $navbar; ?>
  		
		<div class="content">
			<div class="row">
				<div class="col-sm-9 cont">

					<div class="table_options">
						<div class="table_btn">
							<div class="dropdown">
							  <button class="btn dropdown-toggle thmBtn" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							    Add New
							  </button>
							  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
							    <a href="#" class="dropdown-item" data-toggle="modal" data-target="#category_Form_Add">Category</a>
							    <a href="#" class="dropdown-item" data-toggle="modal" data-target="#unit_Form_Add">Unit</a>
							    <a href="#" class="dropdown-item" data-toggle="modal" data-target="#item_Form_Add">Item</a>
							  </div>
							</div>
						</div>
					</div>

					<table id="stock_list" class="table">
					    <thead>
					        <tr>
					            <th>Category Name</th>
					            <th>Unit Name</th>
					            <th>Item Name</th>
					            <th>Standard Quantity</th>
					            <th>Current Stock</th>
					            <th>Reorder Point</th>
					            <th>Action</th>
					        </tr>
					    </thead>
					    <tbody>
					        <?php echo $stocks; ?>
					    </tbody>
					</table>

				</div>
	 			<?php echo $ticker; ?>
			</div>
		</div>
  	</div>