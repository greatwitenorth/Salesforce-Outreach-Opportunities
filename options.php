<div class="wrap">

<h2>Outreach Positions</h2>
<form method="post" action="options.php">
<?php settings_fields( 'outreach_options' ); ?>
<?php do_settings_sections( 'outreach_options' ); ?>
<p class="submit">
<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
</p>
</form>
</div>