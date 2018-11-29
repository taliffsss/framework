	<div class="wrapper">
  		
  		<?php echo $navbar; ?>
  		
		<div class="content">
			<div class="row">
				<div class="col-sm-9 cont">

					<h4 class="title">Inventory Summary Report</h4>

					<div class="table_filter">
						<div class="lbl">
							<span>Category</span>
							<select>
				                <option value="" disabled selected hidden>Security Question</option>
				                <option value="" >Sample Question 1</option>
				                <option value="" >Sample Question 2</option>
				            </select>
						</div>
						<div class="lbl">
							<span>Date From</span>
							<input type="date">
						</div>
						<div class="lbl">
							<span>Date To</span>
							<input type="date">
						</div>
					</div>

					<table id="table_id" class="table">
					    <thead>
					        <tr>
					            <th>Category Name</th>
					            <th>Item Name</th>
					            <th>Unit Name</th>
					            <th>Start Code</th>
					            <th>Purchased</th>
					            <th>Issued</th>
					            <th>End Count</th>
					            <th>Physical Count</th>
					            <th>Variance</th>
					            <th>Justification</th>
					            <th></th>
					        </tr>
					    </thead>
					    <tbody>
					        <tr>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td>
					            	<button class="btn dashbtn">Draft</button>
					            	<button class="btn dashbtn">Save</button>
					            </td>
					        </tr>
					        <tr>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td>
					            	<button class="btn dashbtn">Draft</button>
					            	<button class="btn dashbtn">Save</button>
					            </td>
					        </tr>
					        <tr>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td>
					            	<button class="btn dashbtn">Draft</button>
					            	<button class="btn dashbtn">Save</button>
					            </td>
					        </tr>
					        <tr>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td></td>
					            <td>
					            	<button class="btn dashbtn">Draft</button>
					            	<button class="btn dashbtn">Save</button>
					            </td>
					        </tr>

					    </tbody>
					</table>

				</div>
	 			<?php echo $ticker; ?>
			</div>
		</div>
  	</div>