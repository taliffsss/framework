		<?php if(Url::uri_segment(2) == 'request-logs'): ?>

				<div class="modal fade" id="request_logs_yN">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="request-logs_header"></h5>
							</div>
							<div class="modal-body" id="request-logs_body">
							</div>
							<div class="modal-footer" id="request-logs_Footer">
							</div>
						</div>
					</div>
				</div>

		<?php else: ?>

				<div class="modal fade" id="yes__NO">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="yes__NO_header"></h5>
							</div>
							<div class="modal-body" id="yes__NO_body">
							</div>
							<div class="modal-footer" id="yes__NO_Footer">
							</div>
						</div>
					</div>
				</div>

				<div class="modal fade" id="yes__NO__REQ">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="yes__NO__REQ_header"></h5>
							</div>
							<div class="modal-body" id="yes__NO__REQ_body">
								Are you sure you want to request these items?
							</div>
							<div class="modal-footer" id="yes__NO__REQ_Footer">
							</div>
						</div>
					</div>
				</div>
		<?php endif; ?>
				<!-- Loader -->
				<div class="modal fade" id="loadermsg" tabindex="-1" role="dialog" aria-hidden="true">
					<div class="modal-dialog modal-sm" style="margin-top:18%;width:20%;" role="document">
						<center><div class="myloader"></div></center>
					</div>
				</div>
				<!-- End Loader -->

				<?php if(Url::uri_segment(2) == 'forgot-password'): ?>

				<!-- Modal -->
				<div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true" data-backdrop="true">
					<div class="modal-dialog modal-dialog-centered" role="document">
						<div class="modal-content">
							<div class="modal-body">
								<button type="button" class="close" data-dismiss="modal" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
								<div class="modal_message success-msg"></div>
									<button class="grayBtn" id="btn-val">OK</button> 
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- END Modal -->

				<?php elseif(Url::uri_segment(2) == 'request-logs'): ?>

					<div class="modal fade" id="ask__update">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h5 class="modal-title" id="ask__update_header"></h5>
								</div>
								<div class="modal-body" id="ask__update_body">
									Are you sure you want to update inventory? Items will be deducted.
								</div>
								<div class="modal-footer" id="ask__update_Footer">
								</div>
							</div>
						</div>
					</div>

				<?php elseif(Url::uri_segment(2) == 'users-list'): ?>

					<div class="modal fade" id="pass__reset">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h5 class="modal-title" id="pass__reset_header"></h5>
								</div>
								<div class="modal-body" id="pass__reset_body">
									Are you sure you want to add?
								</div>
								<div class="modal-footer" id="pass__reset_Footer">
								</div>
							</div>
						</div>
					</div>

				<?php elseif(Url::uri_segment(2) == 'dashboard'): ?>

				<div class="modal fade" id="replenish" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-body">
								<h4 class="modal-title" id="exampleModalLabel">Purchase Entry</h5>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
								<div class="form">
									<form method="POST" action="" id="purchaseEntry" name="purchase__Entry">
										<input type="hidden" name="itemID" id="itemID" readonly>
										<div class="input_div input_check">
											<span class="input input--filled">
												<input class="input__field readonly" type="text" id="it_code" readonly/>
												<label class="input__label" for="it_code">
													<span class="input__label-content">Item Code</span>
												</label>
											</span>
										</div>
										<div class="input_div input_check">
											<span class="input input--filled">
												<input class="input__field readonly" type="text" id="catName" readonly/>
												<label class="input__label" for="catName">
													<span class="input__label-content">Category Name</span>
												</label>
											</span>
										</div>
										<div class="input_div input_check">
											<span class="input input--filled">
												<input class="input__field readonly" type="text" id="unitName"readonly/>
												<label class="input__label" for="unitName">
													<span class="input__label-content">Unit Name</span>
												</label>
											</span>
										</div>
										<div class="input_div input_check">
											<span class="input input--filled">
												<input class="input__field readonly" type="text" id="item__Name" readonly/>
												<label class="input__label" for="item__Name">
													<span class="input__label-content">Item Name</span>
												</label>
											</span>
										</div>
										<div class="input_div input_check">
											<span class="input input--filled">
												<input class="input__field readonly" type="text" id="it_standard" readonly/>
												<label class="input__label" for="it_standard">
													<span class="input__label-content">Item Standard Quantity</span>
												</label>
											</span>
										</div>
										<div class="input_div input_check">
											<span class="input input--filled">
												<input class="input__field readonly" type="text" id="it_reorder" readonly/>
												<label class="input__label" for="it_reorder">
													<span class="input__label-content">Item Reorder Point</span>
												</label>
											</span>
										</div>
										<div class="input_div input_check">
											<span class="input input--filled">
												<input class="input__field readonly" type="text" id="curr_count" name="itemCurrentStocks" readonly/>
												<label class="input__label" for="curr_count">
													<span class="input__label-content">Current Count</span>
												</label>
											</span>
										</div>
										<div class="input_div input_check">
											<span class="input">
												<input class="input__field" type="text" id="pur_quantity" name="purchaseQuantity" />
												<label class="input__label" for="pur_quantity">
													<span class="input__label-content">Purchased Quantity</span>
												</label>
											</span>
										</div>
										<div class="input_div input_check">
											<span class="input">
												<input class="input__field" type="text" id="en_note" name="entryNote" maxlength="200" />
												<label class="input__label" for="en_note">
													<span class="input__label-content">Entry Note</span>
												</label>
											</span>
											<div class="text-danger err-msg-en_note"></div>
										</div>
									</form>
								</div>
								<input type="submit" name="submit" class="btn btn_default purchase-btn" id="btn-replenish" value="Replenish">
							</div>
						</div>
					</div>
				</div>

				<!-- Modal Category -->
				<div class="modal fade" id="category_Form_Add" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
				  <div class="modal-dialog" role="document">
				    <div class="modal-content">
				      <div class="modal-body">
				      	 <h4 class="modal-title" id="exampleModalLabel">Add Category</h5>
				        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
				          <span aria-hidden="true">&times;</span>
				        </button>
				        <form method="POST" action="" id="addCategory">
				        	<div id="msg-cat"></div>
				        	<div class="input_div input_check">
				              <span class="input">
				                <input class="input__field" type="text" id="cname" name="cname" required/>
				                <label class="input__label" for="cname">
				                  <span class="input__label-content">Category Name</span>
				                </label>
				              </span>
				            </div>
				            <div class="text-danger err-msg-name"></div>
				            <div class="input_div input_check">
				              <span class="input">
				                <input class="input__field" type="text" id="cdesc" name="cdesc"/>
				                <label class="input__label" for="cdesc">
				                  <span class="input__label-content">Category Description</span>
				                </label>
				              </span>
				            </div>
				            <div class="text-danger err-msg-desc"></div>
				        </form>
				        <input type="submit" name="submit" class="btn btn_default cat-submit" value="Add">       
				      </div>
				    </div>
				  </div>
				</div>
				<!-- Modal Unit -->
				<div class="modal fade" id="unit_Form_Add" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
				  <div class="modal-dialog" role="document">
				    <div class="modal-content">
				      <div class="modal-body">
				      	 <h4 class="modal-title" id="unit__ADD">Add Unit</h5>
				        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
				          <span aria-hidden="true">&times;</span>
				        </button>
				        <div class="form">
					        <form method="POST" name="unitForms" action="" id="unitFormAdd">
					        	<div class="input_div input_check">
					              <span class="input">
					                <input class="input__field" type="text" id="uName" name="uName"/>
					                <label class="input__label" for="uName">
					                  <span class="input__label-content">Unit Name</span>
					                </label>
					              </span>
					              <div class="text-danger err-msg-name"></div>
					            </div>
					            <div class="input_div input_check">
					              <span class="input">
					                <input class="input__field" type="text" id="unit_desc" name="unit_desc" maxlength="200" />
					                <label class="input__label" for="unit_desc">
					                  <span class="input__label-content">Unit Description</span>
					                </label>
					              </span>
					              <div class="text-danger err-msg-unit-desc"></div>
					            </div>
					        </form>
				        </div>
				        <input type="submit" name="submit" class="btn btn_default unit-btn" value="Add">
				      </div>
				    </div>
				  </div>
				</div>
				<!-- Modal Item -->
				<div class="modal fade" id="item_Form_Add" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
				  <div class="modal-dialog" role="document">
				    <div class="modal-content">
				      <div class="modal-body">
				      	 <h4 class="modal-title" id="exampleModalLabel">Add Item</h5>
				        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
				          <span aria-hidden="true">&times;</span>
				        </button>
				        <form method="POST" action="" id="addStock" name="addStock">
				        	<div class="input_div">
				              <?php echo $category; ?>
				            </div>
				            <div class="input_div">
				              <select name='unit_name' id='unit_name' class='input__select item_unit'>
								<option value='' selected>Select Unit</option>
							  </select>
				            </div>
				        	<div class="input_div input_check">
				              <span class="input">
				                <input class="input__field item_check" type="text" id="iname" name="iname" maxlength="50" required/>
				                <label class="input__label" for="iname">
				                  <span class="input__label-content">Item Name</span>
				                </label>
				              </span>
				              <div class="text-danger err-msg-iname"></div>
				            </div>
				            <div class="text-danger err-msg-name"></div>
				            <div class="input_div input_check">
				              <span class="input">
				                <input class="input__field" type="number" id="s__qty" name="s__qty" required/>
				                <label class="input__label" for="s__qty">
				                  <span class="input__label-content">Item Standard Quantity</span>
				                </label>
				              </span>
				            </div>
				            <div class="text-danger err-msg-stnd"></div>
				            <div class="input_div input_check">
				              <span class="input">
				                <input class="input__field" type="number" id="r__pt" name="r__pt" required/>
				                <label class="input__label" for="r__pt">
				                  <span class="input__label-content">Item Reorder Point</span>
				                </label>
				              </span>
				            </div>
				            <div class="text-danger err-msg-rpt"></div>
				            <div class="input_div input_check">
				              <span class="input">
				                <input class="input__field description_check" type="text" id="idesc" name="idesc" maxlength="200"/>
				                <label class="input__label" for="idesc">
				                  <span class="input__label-content">Item Description</span>
				                </label>
				              </span>
				            </div>
				            <div class="text-danger err-msg-desc"></div>
				        </form>
				        <input type="submit" name="submit" class="btn btn_default item-submit" value="Add">
				      </div>
				    </div>
				  </div>
				</div>

				<div class="modal fade" id="item_Form_Edit" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
				  <div class="modal-dialog" role="document">
				    <div class="modal-content">
				      <div class="modal-body">
				      	 <h4 class="modal-title" id="exampleModalLabel">Edit Item</h5>
				        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
				          <span aria-hidden="true">&times;</span>
				        </button>
					        <form method="POST" action="" id="editItem" name="editItem">
					        	<input type="hidden" name="item_id" id="item_id" readonly>
					        	<div class="input_div">
					              <span class="input input--filled">
					                <input class="input__field readonly" type="text" id="cat_name" name="cat_name" readonly/>
					                <label class="input__label" for="cat_name">
					                  <span class="input__label-content">Category Name</span>
					                </label>
					              </span>
					            <div class="input_div">
					              <span class="input input--filled">
					                <input class="input__field readonly" type="text" id="u_name" name="u_name" readonly/>
					                <label class="input__label" for="u_name">
					                  <span class="input__label-content">Unit Name</span>
					                </label>
					              </span>
					            </div>
					        	<div class="input_div input_check">
					              <span class="input input--filled">
					                <input class="input__field item_check" type="text" id="it_name" name="it_name"/>
					                <label class="input__label" for="it_name">
					                  <span class="input__label-content">Item Name</span>
					                </label>
					              </span>
					            </div>
					            <div class="text-danger err-msg-name"></div>
					            <div class="input_div input_check">
					              <span class="input input--filled">
					                <input class="input__field" type="number" id="item_standard" name="item_standard" required/>
					                <label class="input__label" for="item_standard">
					                  <span class="input__label-content">Item Standard Quantity</span>
					                </label>
					              </span>
					            </div>
					            <div class="text-danger err-msg-stnd"></div>
					            <div class="input_div input_check">
					              <span class="input input--filled">
					                <input class="input__field reorder_pt_check" type="text" id="reorder" name="reorder" required/>
					                <label class="input__label" for="reorder">
					                  <span class="input__label-content">Item Reorder Point</span>
					                </label>
					              </span>
					            </div>
					            <div class="text-danger err-msg-rpt"></div>
					            <div class="input_div input_check">
					              <span class="input input--filled">
					                <input class="input__field description_check" type="text" id="i_description" name="i_description"/>
					                <label class="input__label" for="i_description">
					                  <span class="input__label-content">Item Description</span>
					                </label>
					              </span>
					            </div>
								<div class="text-danger err-msg-desc"></div>
					        </form>
				        	
				      </div>
				      <input type="submit" name="submit" class="btn btn_default item-submit" id="btn-edit_item" value="Edit">
				    </div>
				  </div>
				</div>

				<?php endif; ?>