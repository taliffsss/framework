<div class="wrapper">

	<?php echo $navbar; ?>

	<div class="content">
		<div class="row">

			<div class="col-sm-9 cont">
				<h4 class="title">File Upload</h4>

				<div class="footnote">
					<?php echo Session::flash('import_msg'); ?>
					<?php echo Session::flash('import_dup'); ?>
				</div>

				<form method="post" enctype="multipart/form-data" class="dragDrop">

					<input class="form-control" type="file" accept=".csv" name="csvfile" id="csvfile" required>
					<p class="note">Drag your csv file here or click in this area.</p>
					<span>Note: When uploading a file, please make sure the it has "category" / "unit" / "item"/ "stock" / "employee" / "sub_department" in the file name.</span>

					<input type="submit" value="Upload" name="upload" id="upload">

				</form>

			</div>
			<?php echo $ticker; ?>
		</div>
	</div>
</div>