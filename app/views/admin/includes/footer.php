		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
		<script src="<?php echo Url::baseUrl('assets/js/bootstrap.bundle.min.js'); ?>"></script>
		<script src="<?php echo Url::baseUrl('assets/js/jquery.dataTables.min.js'); ?>"></script>
	    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
		<script src="<?php echo Url::baseUrl('assets/js/classie.js'); ?>"></script>
		<script src="<?php echo Url::baseUrl('assets/js/style.js'); ?>"></script>
		<script src="<?php echo Url::baseUrl('assets/js/function.js'); ?>"></script>
		<script src="<?php echo Url::baseUrl('assets/js/dashboard.js'); ?>"></script>
		
		<?php if(Url::uri_segment(2) == 'users-list'): ?>
			
		<script src="<?php echo Url::baseUrl(); ?>/assets/js/user.js"></script>

		<?php elseif(Url::uri_segment(2) == 'turnaround-report'): ?>

		<script src="<?php echo Url::baseUrl('assets/js/chart.min.js'); ?>"></script>
		<script src="<?php echo Url::baseUrl('assets/js/report.js'); ?>"></script>

		<?php elseif(Url::uri_segment(3) == 'view'): ?>

		<script src="<?php echo Url::baseUrl(); ?>/assets/js/request-log.js"></script>

		<?php elseif(Url::uri_segment(2) == 'requisition-form'): ?>

		<script src="<?php echo Url::baseUrl(); ?>/assets/js/requisition.js"></script>

		<?php elseif(Url::uri_segment(2) == 'upload'): ?>

		<script src="<?php echo Url::baseUrl(); ?>/assets/js/upload.js"></script>

		<?php elseif(Url::uri_segment(2) == 'request-logs'): ?>

		<script src="<?php echo Url::baseUrl(); ?>/assets/js/update-inventory.js"></script>

		<?php endif; ?>

	    <script type="text/javascript">

	    <?php if(Url::uri_segment(2) == 'dashboard'): ?>
	    	
    		$('#stock_list').dataTable( {
				"ordering" : false,
				"columns": [
					null,
					null,
					null,
					null,
					null,
					null,
					{ 'searchable': false }
				],
				"bLengthChange" : false,
				"oLanguage": {
    				"sEmptyTable":     "No Record Found."
				}
			});

		<?php elseif(Url::uri_segment(2) == 'request-logs'): ?>
			$('#reqlog_list').dataTable( {
				"ordering" : false,
				"columns": [
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					{ 'searchable': false }
				],
				"bLengthChange" : false,
				"oLanguage": {
    				"sEmptyTable":     "No Record Found."
				}
			});
			<?php if(Session::userdata('role') == 1): ?>
				<?php if(Session::flash('success')): ?>
				toastr.success('<?php echo Session::flash('success'); ?>', 'Success', {timeOut: 8000});
				<?php endif; ?>
			<?php else: ?>
				<?php if(Session::flash('success')): ?>
				toastr.success('<?php echo Session::flash('success'); ?>', 'Success', {timeOut: 8000});
				<?php endif; ?>
			<?php endif; ?>
			
		<?php elseif(Url::uri_segment(2) == 'users-list'): ?>

			$('#stock_list').dataTable( {
				"ordering" : false,
				"columns": [
					null,
					null,
					null,
					null,
					null,
					{ 'searchable': false }
				],
				"bLengthChange" : false,
				"bPaginate": false,
				"oLanguage": {
    				"sEmptyTable":     "No Record Found."
				}
			});

		<?php elseif(Url::uri_segment(2) == 'detailed-report'): ?>

			$('#table_id').dataTable( {
				"ordering" : false,
				"bFilter": false,
				"bLengthChange" : false,
				"dom": "lfrti",
				"oLanguage": {
    				"sEmptyTable":     "No Record Found."
				}
			});
	    <?php endif; ?>
		</script>

	</body>
</html>