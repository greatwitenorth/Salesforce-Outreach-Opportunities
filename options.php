<div class="wrap">
	<style>
		.form-table{ background: #ececec; }
		h3{ margin-top: 40px;}
		.location{ margin-bottom: 20px;}
	</style>
<a href="<?php echo $_SERVER['PHP_SELF']; ?>?page=sfdc&sf-refresh=true">Refresh data from salesforce</a>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=sfdc">
	<div style='float:left; width: 55%; margin-right: 5%;'>
		<h2>Outreach Positions</h2>
			<?php settings_fields( 'outreach_options' ); ?>
			<?php do_settings_sections( 'outreach_options' ); ?>
			<p class="submit">
			<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
			</p>
	</div>
	<div style='float:left; width: 40%;margin-top: 35px;'>
		<?php settings_fields( 'outreach_dates' ); ?>
		<?php do_settings_sections( 'outreach_dates' ); ?>
		<p class="submit">
		<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
		</p>
		
		<?php settings_fields( 'inactive_outreaches' ); ?>
		<?php do_settings_sections( 'inactive_outreaches' ); ?>
		<p class="submit">
		<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
		</p>
	</div>	
</form>
</div>