	<div class="wrapper">

		<?php echo $navbar; ?>

		<div class="content">
			<div class="row">
				<?php echo $transaction; ?>
				<?php echo $ticker; ?>
			</div>
		</div>
	</div>
	<?php echo (isset($print)) ? $print : ''; ?>