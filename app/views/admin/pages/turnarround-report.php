	<div class="wrapper">
  		
  		<?php echo $navbar; ?>
  		
		<div class="content">
			<div class="row">
				<div class="col-sm-9 cont">

					<h4 class="title">Turnaround Time</h4>

					<div class="table_filter">
						<div class="lbl">
							<span>Date From</span>
							<input type="date">
						</div>
						<div class="lbl">
							<span>Date To</span>
							<input type="date">
						</div>
					</div>

					<div class="row reports-div">
						<div class="col-sm-5">
							<table class="reports-table">
								<thead>
									<tr>
										<td>No of Days</td>
										<td>No of Occurrences</td>
										<td>%</td>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>1</td>
										<td>56</td>
										<td>24%</td>
									</tr>
									<tr>
										<td>2</td>
										<td>87</td>
										<td>37%</td>
									</tr>
									<tr>
										<td>3</td>
										<td>72</td>
										<td>31%</td>
									</tr>
									<tr>
										<td>4</td>
										<td>72</td>
										<td>31%</td>
									</tr>
									<tr>
										<td>5</td>
										<td>72</td>
										<td>31%</td>
									</tr>
									<tr>
										<td>6</td>
										<td>72</td>
										<td>31%</td>
									</tr>
									<tr>
										<td>7</td>
										<td>72</td>
										<td>31%</td>
									</tr>
									<tr>
										<td>8</td>
										<td>72</td>
										<td>31%</td>
									</tr>
									<tr>
										<td>9</td>
										<td>72</td>
										<td>31%</td>
									</tr>
									<tr>
										<td>10</td>
										<td>72</td>
										<td>31%</td>
									</tr>
									<tr>
										<td>>10</td>
										<td>72</td>
										<td>31%</td>
									</tr>
									<tr>
										<td>Cancelled</td>
										<td>72</td>
										<td>31%</td>
									</tr>
								</tbody>
								<tfoot>
									<tr>
										<td>Total</td>
										<td>234</td>
										<td>100%</td>
									</tr>
								</tfoot>
							</table>
							<div style="text-align: center; margin: 20px 0;">
								<button class="btn thmBtn" type="button">
								  Run Report
								</button>
								<button class="btn thmBtn download" type="button">
								  Download
								</button>
							</div>
							
						</div>
						<div class="col-sm-7">
							<canvas id="myChart" width="400" height="400"></canvas>
						</div>
					</div>
				</div>
				<?php echo $ticker; ?>
			</div>
		</div>
  	</div>