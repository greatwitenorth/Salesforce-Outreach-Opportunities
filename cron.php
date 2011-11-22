<?php
/*
Plugin Name: Outreach Opportunities
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Output how many positions there are available for each outreach. This data is pulled dynamically from salesforce.
Version: 1.0
Author: Nick Verwymeren
Author URI: http://makesomecode.com
License: GPL2
*/

add_action('admin_menu', 'plugin_admin_add_page');
add_action('admin_init', 'plugin_admin_init');
add_shortcode( 'outreaches', 'outreach_shortcode' );
@define('SFDC_PATH', dirname(__FILE__));
require_once('connection.php');

global $sf_db_version;
$sf_db_version = "1.0";
register_activation_hook(__FILE__,'outreach_install');
register_activation_hook(__FILE__,'update_data');
add_action('plugins_loaded', 'sf_db_check');
add_action('admin_footer', 'outreach_admin_footer');

//Save our errors for debugging
add_action('activated_plugin','save_error');
function save_error(){
    update_option('plugin_error',  ob_get_contents());
}

//Set our custom field name and object it belongs to
$searchFieldName 	= "PNG_Outreach__c";
$searchObject 		= "Opportunity";


function sf_db_check() {
    global $sf_db_version;
    if (get_site_option('sf_db_version') != $sf_db_version) {
        outreach_install();
    }
}

function outreach_install(){
	global $wpdb;
	global $sf_db_version;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	$table_name = $wpdb->prefix . "outreaches";
	$sql = "CREATE TABLE " . $table_name . "  (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	  active tinyint(1) DEFAULT NULL,
	  defaultValue tinyint(1) DEFAULT NULL,
	  label varchar(255) DEFAULT NULL,
	  value varchar(255) DEFAULT NULL,
	  location varchar(255) DEFAULT NULL,
	  display tinyint(1) DEFAULT NULL,
	  start_date date DEFAULT NULL,
	  end_date date DEFAULT NULL,
	  PRIMARY KEY  (id)
	) ;";
	dbDelta($sql);
	
	$table_name = $wpdb->prefix . "outreach_positions";
	$sql = "CREATE TABLE " . $table_name . "  (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	  active tinyint(1) DEFAULT NULL,
	  defaultValue tinyint(1) DEFAULT NULL,
	  label varchar(255) DEFAULT NULL,
	  value varchar(255) DEFAULT NULL,
	  PRIMARY KEY  (id)
	) ;";
	dbDelta($sql);

	$table_name = $wpdb->prefix . "outreach_positions_join";
	$sql = "CREATE TABLE " . $table_name . "  (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	  outreach_id int(11) DEFAULT NULL,
	  position_id int(11) DEFAULT NULL,
	  target int(11) DEFAULT NULL,
	  approved int(11) DEFAULT NULL,
	  advertise tinyint(1) DEFAULT NULL,
	  PRIMARY KEY  (id)
	) ;";
	dbDelta($sql);
	add_option("sf_db_version", $sf_db_version);
}

function update_data(){
	global $wpdb;
	$options = get_option('outreach_options');
	$outreaches = outputOpportunities(array("PNG_Outreach__c"), "Opportunity");
	$positions = outputOpportunities(array("Type_of_Volunteer__c"), "Opportunity");
	$outreach_table_name = $wpdb->prefix . "outreaches";
	$position_table_name = $wpdb->prefix . "outreach_positions";
	$join_table_name = $wpdb->prefix . "outreach_positions_join";
	$approvedApplicants = getAllApprovedApplicants();

	foreach ($outreaches as $outreach){
		$query = "SELECT id FROM " . $outreach_table_name . " WHERE label = '" . $outreach->label . "'";
		if($wpdb->query($query) === 0){
			$rows_affected = $wpdb->insert( $outreach_table_name, array( 'active' => $outreach->active, 'defaultValue' => $outreach->defaultValue, 
															'label' => $outreach->label, 'value' => $outreach->value,
															'display' => false));
		}
	}
	
	foreach ($positions as $position){
		$query = "SELECT id FROM " . $position_table_name . " WHERE label = '" . $position->label . "'";
		if($wpdb->query($query) === 0){
			$rows_affected = $wpdb->insert( $position_table_name, array( 'active' => $position->active, 'defaultValue' => $position->defaultValue, 
															'label' => $position->label, 'value' => $position->value));
		}
	}
	
	//Lets get all approved applicant from salesforce and put them in our join table
	foreach ($outreaches as $outreach){
		foreach ($positions as $position){
			$oresults = $wpdb->get_row(
				"
				SELECT * from $outreach_table_name
				WHERE label = '$outreach->label'
				"
			);
			
			$presults = $wpdb->get_row(
				"
				SELECT * from $position_table_name
				WHERE label = '$position->label'
				"
			);
			
			$query = $wpdb->get_row(
				"
				SELECT * FROM $join_table_name 
				WHERE outreach_id = $oresults->id 
				AND position_id = $presults->id
				");
			$approved = getApprovedApplicantsForPosition($outreach->label, $position->label, $approvedApplicants);
			if(!$query){
				$rows_affected = $wpdb->insert( $join_table_name, array( 'advertise' => false, 'approved' => $approved, 
																'target' => 0, 'outreach_id' => $oresults->id,
																'position_id' => $presults->id));
			}
			
			$qapproved = isset($query->approved) ? $query->approved : 0;
			if($approved != $qapproved){
				$rows_affected = $wpdb->update( $join_table_name, array( 'approved' => $approved ),
																  array( 'outreach_id' => $oresults->id,
																  'position_id' => $presults->id));				
			}
		}
	}
}

function update_post_data(){
	global $wpdb;
	$join_table_name = $wpdb->prefix . "outreach_positions_join";
	$outreach_table_name = $wpdb->prefix . "outreaches";
	$position_table_name = $wpdb->prefix . "outreach_positions";
	$outreaches = $_POST['outreach_options'];

	//update_data();

	foreach($outreaches as $okey => $outreach){
		if(isset($outreach['checked'])){
			$checked = true;				
		} else {
			$checked = false;
		}
		$rows_affected = $wpdb->update(
			$outreach_table_name, 
			array( 	'display' => $checked),
			array( 	'label'   => $okey));
		if(isset($outreach['positions'])){
			foreach ($outreach['positions'] as $pkey => $position){
				$oresults = $wpdb->get_row(
					"
					SELECT * from $outreach_table_name
					WHERE label = '$okey'
					"
				);

				$presults = $wpdb->get_row(
					"
					SELECT * from $position_table_name
					WHERE label = '$pkey'
					"
				);
				if(isset($position['checked'])){
					$checked = true;				
				} else {
					$checked = false;
				}
				$rows_affected = $wpdb->update(
					$join_table_name, 
					array( 	'target' => $position['target'], 'advertise' => $checked ),
					array( 	'outreach_id' 	=> $oresults->id,
							'position_id' 	=> $presults->id));
			}
		}
		if(isset($outreach['dates'])){
			$oresults = $wpdb->get_row(
				"
				SELECT * from $outreach_table_name
				WHERE label = '$okey'
				"
			);
			$rows_affected = $wpdb->update(
				$outreach_table_name, 
				array( 	'start_date' => $outreach['dates']['start'],
						'end_date' => $outreach['dates']['end'],
						'location' => $outreach['dates']['location'] ),
				array( 	'label' => $okey ));
		}
	}
}

function plugin_admin_init() {
	register_setting( 'outreach_options', 'outreach_options' );
	register_setting( 'inactive_outreaches', 'inactive_outreaches' );
	register_setting( 'outreach_dates', 'outreach_dates' );
	$pluginfolder = get_bloginfo('url') . '/' . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__));
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-ui-core');
	wp_enqueue_script('jquery-ui-datepicker', $pluginfolder . '/js/jquery.ui.datepicker.min.js', array('jquery', 'jquery-ui-core') );
	wp_enqueue_script('jquery-ui-fader', $pluginfolder . '/js/jquery.effects.fade.min.js', array('jquery', 'jquery-ui-core') );
	wp_enqueue_style('jquery.ui.theme', $pluginfolder . '/js/redmond/jquery-ui-1.8.16.custom.css');
	if(isset($_POST['Submit'])){
		update_post_data();
	}
	if(isset($_GET['sf-refresh'])){
		update_data();
	}
	echo "<style>.greenbg{background:#b6da70;
					padding:1px 5px;
					-moz-border-radius: 5px;
					-webkit-border-radius: 5px;
					border-radius: 5px;}
					.redbg{background:#da7f70;
					padding:1px 5px;
					-moz-border-radius: 5px;
					-webkit-border-radius: 5px;
					border-radius: 5px;}</style>";
	output_all_setting_fields();
	output_outreach_dates_field();
}

function plugin_admin_add_page() {
	add_options_page('Outreach Opportunities', 'Outreach Opportunities', 'manage_options', 'sfdc', 'sfdc_options_page');
}

function output_outreach_dates_field(){
	global $wpdb;
	$outreach_table_name = $wpdb->prefix . "outreaches";
	$outreaches = $wpdb->get_results( 
		"
		SELECT label, start_date, end_date, value, display, location
		FROM $outreach_table_name
		WHERE active = true 
		"
	);
	add_settings_section('outreach_dates', 'Outreach Dates', 'outreach_dates_section_text', 'outreach_dates');				
	foreach($outreaches as $key => $outreach){
		if($outreach->display){
		add_settings_field('start - '.$outreach->value, $outreach->value." - Start",
							'outreach_dates_display_field', 'outreach_dates', 
							'outreach_dates', array('label_for' => 'start - '.$outreach->value,
							'id' => array( 'outreach' => $outreach->value, 'text' => 'start',
							'value' => $outreach->start_date, 'class' => 'datepicker' ) ));
							
		add_settings_field('end - '.$outreach->value, $outreach->value." - End",
							'outreach_dates_display_field', 'outreach_dates', 
							'outreach_dates', array('label_for' => 'end - '.$outreach->value,
							'id' => array( 'outreach' => $outreach->value, 'text' => 'end',
							'value' => $outreach->end_date, 'class' => 'datepicker') ));
							
		add_settings_field('location - '.$outreach->value, "Location",
							'outreach_dates_display_field', 'outreach_dates', 
							'outreach_dates', array('label_for' => 'location - '.$outreach->value,
							'id' => array( 'outreach' => $outreach->value, 'text' => 'location',
							'value' => $outreach->location, 'class' => 'location') ));
		}	
	}
}

function output_all_setting_fields(){
	global $wpdb;
	$outreach_table_name = $wpdb->prefix . "outreaches";
	$position_table_name = $wpdb->prefix . "outreach_positions";
	$join_table_name = $wpdb->prefix . "outreach_positions_join";
	
	$outreaches = $wpdb->get_results( 
		"
		SELECT * 
		FROM $outreach_table_name
		WHERE active = true 
		"
	);
	
	$positions = $wpdb->get_results( 
		"
		SELECT * 
		FROM $position_table_name
		WHERE active = true 
		"
	);
	$inactives = array();
	foreach($outreaches as $key => $outreach){
		$check_value = '';
		if(isset($outreach->display)){
			if($outreach->display == true){
				add_settings_section($outreach->label, $outreach->label, 'outreach_section_text', 'outreach_options');
				$check_value = "checked=yes";
				add_settings_field($outreach->value, '', 'outreach_display_field', 'outreach_options', 
									$outreach->label, array('label_for' => 'display - '.$outreach->value,
									'id' => array( 'outreach' => $outreach->value, 
									'checked' => $check_value, 'text' => 'Display this outreach?' ) ));
				foreach($positions as $position){
					$value = '';
					$check_value = '';
					$target = '';
					$data = $wpdb->get_row( 
						"
						SELECT * FROM $join_table_name a
						INNER JOIN $outreach_table_name b 
						ON b.id = a.outreach_id
						INNER JOIN $position_table_name c
						ON a.position_id = c.id
						WHERE a.outreach_id = $outreach->id
						AND a.position_id = $position->id
						"
					);
					if(isset($data->advertise)){
						if($data->advertise == true){
							$check_value = "checked=yes";
						}
					}
					if(isset($data->target)){
						$target = $data->target;
					}
					add_settings_field( $outreach->value."-".$position->value, $position->value, 'outreach_setting_field', 'outreach_options',
											$outreach->label, array('label_for' => $outreach->value."-".$position->value, 'id' => array(
											'outreach' => $outreach->value, 'position' => $position->value, 
											'checked' => $check_value, 'target' => $target, 'approved' => $data->approved ) ));
				}
			} else {
				$inactives[] = $outreach;
			}
		}	
	}
	if ($inactives){
		add_settings_section('inactive_outreaches', 'Inactive Outreaches', 'inactive_outreach_section_text', 'inactive_outreaches');				
		foreach($inactives as $outreach){
			add_settings_field('inactive'.$outreach->value, $outreach->value, 'outreach_display_field', 'inactive_outreaches', 
								'inactive_outreaches', array('label_for' => 'display - '.$outreach->value,
								'id' => array( 'outreach' => $outreach->value, 'text' => '',
								'checked' => '' ) ));
		}
	}
}

function outreach_setting_field($input) {
	$outreach 	= $input['id']['outreach'];
	$position	= $input['id']['position'];
	$value	 	= $input['id']['target'];
	$checked 	= $input['id']['checked'];
	$approved 	= $input['id']['approved'];
	$color 		= $value > $approved ? 'redbg' : 'greenbg';	
	$color 		= $approved == 0 && $value == 0 ? '' : $color;	
	echo "<input id='advertise - ".$outreach."-".$position."' name='outreach_options[".$outreach."][positions][".$position."][checked]'
			type='checkbox' value='true' $checked />";
	echo "<input id='target - ".$outreach."-".$position."' name='outreach_options[".$outreach."][positions][".$position."][target]' size='5' 
			type='text' value='$value' />";
	echo "Approved: <span class='".$color."'>".$approved."</span>";
}

function outreach_display_field($input) {
	$outreach 	= $input['id']['outreach'];
	$text		= $input['id']['text'];
	$checked	= $input['id']['checked'];
	echo "<input id='display - ".$outreach."' name='outreach_options[".$outreach."][checked]'
			type='checkbox' value='true' $checked/> ".$text;
}

function outreach_section_text() {
	//echo "<input name='Submit' type='submit' value='Save all changes' />";
}

function inactive_outreach_section_text() {
	echo "Check and save to reactivate an outreach below";
}

function outreach_dates_section_text(){	
	echo "";
}

function outreach_dates_display_field($input) {
	$outreach 	= $input['id']['outreach'];
	$text		= $input['id']['text'];
	$value		= $input['id']['value'];
	$class 		= $input['id']['class'];
	
	if($value == "0000-00-00"){$value = '';}
	echo "<input id='".$text." - ".$outreach."' class='".$class."' name='outreach_options[".$outreach."][dates][".$text."]'
			type='text' size='8' value='".$value."' />";
}

function sfdc_options_page(){
	global $wpdb;
	include(SFDC_PATH . '/options.php');
}

function outreach_shortcode($atts){
	global $wpdb;
	$outreach_table_name = $wpdb->prefix . "outreaches";
	$position_table_name = $wpdb->prefix . "outreach_positions";
	$join_table_name = $wpdb->prefix . "outreach_positions_join";
	$close = '';
	$i = 0;
	
	if(isset($_GET['sf-refresh'])){
		update_data();
	}
	extract( shortcode_atts( array(
			'showtable' => 'true',
			'contactid' => '',
			'showphoto' => 'true',
			'opportunity' => '',
			'size' => '200'
	), $atts ) );
	
	echo "<style>.greenbg{background:#b6da70;
					padding:1px 5px;
					-moz-border-radius: 5px;
					-webkit-border-radius: 5px;
					border-radius: 5px;}
				  .redbg{background:#70b8da;
					padding:1px 5px;
					-moz-border-radius: 5px;
					-webkit-border-radius: 5px;
					border-radius: 5px;}
				  .outreachbg{background:#ececec;
					padding:10px;
					margin-top: 20px;
					width: 40%;
					float: left;
					margin-right: 5%;
					-moz-border-radius: 5px;
					-webkit-border-radius: 5px;
					border-radius: 5px;}</style>";
	
	$outreaches = $wpdb->get_results( 
		"
		SELECT * 
		FROM $outreach_table_name
		WHERE active = true 
		AND display = true
		ORDER BY date(start_date)
		"
	);
	
	$positions = $wpdb->get_results( 
		"
		SELECT * 
		FROM $position_table_name a
		WHERE active = true
		ORDER BY label
		"
	);
	
	$years = $wpdb->get_results(
		"
		SELECT DISTINCT YEAR(start_date) 
		AS 'Year' FROM $outreach_table_name
		"
		);

	$years = array_flatten_recursive((array)$years);

	if($atts['showtable'] == 'true'){
		ob_start();
		foreach($years as $year){
			$o = $wpdb->get_results( 
				"
				SELECT * 
				FROM $outreach_table_name
				WHERE active = true 
				AND display = true
				AND year(start_date) = $year
				"
			);
			$year ? showTable($o, $positions, $year) : false;
		}
		$output_string=ob_get_contents();;
		ob_end_clean();
		return $output_string;
	}
	
	if($atts['showphoto'] == 'true'){
		ob_start();
		getContactPhotosForOpportunity($atts['opportunity'], $atts['size']);
		//getPhotos(array('003C000001GZVvi'));
		$output_string=ob_get_contents();;
		ob_end_clean();
		return $output_string;
	}

	if($atts['showAll'] == 'true'){
		foreach($outreaches as $key => $outreach){
			$result = $wpdb->get_row( 
				"
				SELECT SUM(a.target) total
				FROM $join_table_name a
				INNER JOIN $outreach_table_name b 
				ON b.id = a.outreach_id
				WHERE b.label = '$outreach->label'
				AND a.advertise = true
				"
			);
			if($i > 1){
				$clear = "<div style='clear:both;'></div>";
				$i = 0;
			} else {
				$clear = '';
			}
			$close = '';
			if($result->total){ 
				$start_date = date('F j', strtotime($outreach->start_date));
				$end_date = date('F j', strtotime($outreach->end_date));
			
				echo $clear;
				echo "<div class='outreachbg'>";
				echo "<h1><u>".$outreach->label."</u></h1>";
				echo "<p><span class='redbg'>".$start_date."</span> - 
					  <span class='redbg'>".$end_date."</span></p>";
			
				$close = "</div>";
				$i++;
			}
			foreach($positions as $position){
				$value = '';
				$check_value = '';
				$target = '';
				$data = $wpdb->get_row( 
					"
					SELECT * FROM $join_table_name a
					INNER JOIN $outreach_table_name b 
					ON b.id = a.outreach_id
					INNER JOIN $position_table_name c
					ON a.position_id = c.id
					WHERE a.outreach_id = $outreach->id
					AND a.position_id = $position->id
					AND a.advertise = true
					"
				);
				if($data){
					$needed = $data->target - $data->approved;
					if ($needed > 0){
						//Output data
						$type = position_type($position->value);
						echo "<div class='".$type."'>";
						echo "<p><span class='greenbg'>".$needed."</span> ".$position->value."</p>";
						echo "</div>";
					}
				}
			}
			echo $close;
		}
	}
	
	return;
}

function searchForField($myObjects, $value, $key){
	$item = null;
	foreach($myObjects as $struct) {
	    if ($value == $struct->$key) {
	        $item = $struct;
			return $item;
	        break;
	    }
	}
	return false;
}

function outputOpportunities($fields, $object){
	
	//Output all available opportunities
	$client = getConnection();
	$values = $client->describeSObject($object);

	$opportunities = array();
	foreach($fields as $field){
		$items 	= searchForField($values->fields, $field, "name");
		foreach($items->picklistValues as $item){
			$opportunities[] = $item;
		}
	}
	return $opportunities;
}

function showOpportunityMenu($selected, $fields, $object){
	echo "<form action='cron.php' method='post'>";
	
	//Output all available opportunities
	$client = getConnection();
	$values = $client->describeSObject($object);

	foreach($fields as $field){
		$items 	= searchForField($values->fields, $field, "name");
		echo "<select name='".$field."'>\n";
		echo "\t<option value=''>---Select a Value---</option>\n";
		foreach($items->picklistValues as $item){
			$selected = ($item->value == $selected ? 'selected' : '');
			echo "\t<option value='" . $item->value . "' ". $selected .">" . $item->label . "</option>\n";
		}
		echo "\t<option value='showAll'>Show all on one page</option>\n";
		echo "</select>\n";
	}
	
	echo "<input type='submit' value='Submit' />\n";
	echo "</form>\n";
}

function getContactPhotosForOpportunity($opp, $size){
	global $searchObject, $searchFieldName;

	$client = getConnection();
	$results = $client->query("SELECT ContactId
								FROM OpportunityContactRole 
								WHERE OpportunityId in (SELECT Id FROM Opportunity 
								WHERE StageName = 'Approved Application'
								AND $searchFieldName = '$opp')");

	//Display photos for our contact ID's
	if ($results->size != 0){
		$ids = array();
		foreach($results->records as $record){
			$ids[] = $record->fields->ContactId;
		}
	
		echo "<h1>" . $opp . "</h1>";
		getPhotos($ids, $size);
	}
}

function getNeededApplicantsForPosition($outreach, $volunteerType){
	
	$client = getConnection();
	$results = $client->query("SELECT ContactId FROM OpportunityContactRole 
								WHERE OpportunityId in (SELECT Id FROM Opportunity 
								WHERE Type_of_Volunteer__c INCLUDES ('$volunteerType') 
								AND PNG_Outreach__c = '$outreach' AND StageName = 'Approved Applicant')");

	//TODO add logic to get the target value of positions minus actual positions filled
	
}

function getApprovedApplicantsForPosition($outreach, $volunteerType, $data){
	$count = 0;
	foreach($data->records as $record){

		$o = $record->fields->Opportunity->fields->PNG_Outreach__c;
		$v = $record->fields->Opportunity->fields->Type_of_Volunteer__c;

		if(strpos($o, $outreach) !== false && strpos($v, $volunteerType) !== false){
			$count++;
		}
	}

	return $count;
}

function getAllApprovedApplicants(){
	
	$client = getConnection();
	$results = $client->query("SELECT ContactId, OpportunityId, 
								Opportunity.Id, Opportunity.Name, Opportunity.PNG_Outreach__c, Opportunity.Type_of_Volunteer__c 
								FROM OpportunityContactRole 
								WHERE OpportunityId in (SELECT Id FROM Opportunity 
								WHERE StageName = 'Approved Application')");
	return $results;
}

function position_type($position){
	if (strstr($position, "Medical")){
		return "medical";
	}
	
	if (strstr($position, "Crew")){
		return "crew";
	}
	
	if (strstr($position, "General")){
		return "general";
	}
}

function getPhotos($contactids, $size){
	$mySforceConnection = getConnection();
	$query = "SELECT Id, Name, ParentId from Attachment Where (ParentId in (";
	foreach($contactids as $id){
		$query .= "'". $id . "',";
	}
	$query = substr($query , 0, strlen($query) - 1);
	$query .= ") AND Name = 'Contact Picture') ";
	$queryResult = $mySforceConnection->query($query);
	$records = $queryResult->records;

	if(count($records) > 0){
		echo "\n<table cellspacing='5' cellpadding='5'>\n\t<tr>\n";
		foreach($records as $rec){
			$name = $mySforceConnection->retrieve('Name', 'Contact', $rec->fields->ParentId);
			
			$image = '';
			if (!file_exists(SFDC_PATH."/imagecache/".$rec->Id.".jpg")){
				savePhotoFile($rec->Id);				
			}
			
			$image = plugins_url( '/', __FILE__ )."image.php?width=$size&height=$size&cropratio=1:1&image=/imagecache/".$rec->Id.".jpg";
												
			echo "\t\t<td style='text-align:center;font-size:10px;'>\n".
					"\t\t\t<img src='$image' ".
					"style='width:".$size."px;height:".$size."px;border:1px solid #ccc;padding:".($size/20)."px'/> <br/>\n\t\t\t"
					. $name[0]->fields->Name. 
				 "\n\t\t</td>\n";
			 "\t</tr>\n";
		}
		echo "</table>\n";
	}
	return NULL;
}

function savePhotoFile($id){
	$remote_img = plugins_url( '/', __FILE__ )."/sf-image.php?id=".$id;
	$img = imagecreatefromjpeg($remote_img);
	$path = SFDC_PATH."/imagecache/".$id.".jpg";
	imagejpeg($img, $path, 100);
}

function array_flatten_recursive($array) { 
   if (!$array) return false;
   $flat = array();
   $RII = new RecursiveIteratorIterator(new RecursiveArrayIterator($array));
   foreach ($RII as $value) $flat[] = $value;
   return $flat;
}

function showTable($outreaches, $positions, $year){

	
?>
<h2><?php echo $year ?> Outreaches</h2>
<h3>Available Positions</h3>
<table border="1">
	<tbody>
		<tr>
			<td></td>
			<td></td>
			<td align="center" valign="middle" colspan="6" cellpadding="0">
				<p>Available Positions</p>
			</td>
			<td></td>			
		</tr>
		<tr>
			<td><h3><strong>Dates</strong></h3></td>
			<td><h3><strong>Location</strong></h3></td>

			<td align="center" valign="middle">
				<h3><strong>PHC</strong></h3>
			</td>
			<td align="center" valign="middle">
				<h3><strong>DEN</strong></h3>
			</td>
			<td align="center" valign="middle">
				<h3><strong>OPT</strong></h3>
			</td>
			<td align="center" valign="middle">
				<h3><strong>OPH</strong></h3>
			</td>
			<td align="center" valign="middle">
				<h3><strong>Crew</strong></h3>
			</td>
			<td align="center" valign="middle">
				<h3><strong>GEN</strong></h3>
			</td>
			<td></td>
		</tr>
<?php
	foreach($outreaches as $outreach){
		$start = date('F j', strtotime($outreach->start_date));
		$end = date('F j', strtotime($outreach->end_date));
		$total = tallyPositionGroups($outreach, $year);
?>
		<tr>
			<td><?php echo $start . " - " . $end ?></td>
			<td><?php echo $outreach->location ?></td>
			<td align="center" valign="middle"><?php echo $total['phc'] ?></td>
			<td align="center" valign="middle"><?php echo $total['den'] ?></td>
			<td align="center" valign="middle"><?php echo $total['opt'] ?></td>
			<td align="center" valign="middle"><?php echo $total['oph'] ?></td>
			<td align="center" valign="middle"><?php echo $total['crew'] ?></td>
			<td align="center" valign="middle"><?php echo $total['gen'] ?></td>
			<td align="center" valign="middle">
				<div class="button-wrap ">
				<div class="blue button ">
				<a href="/volunteer/sign-up/online-application/"  >Register Now!</a>
				</div></div>
			</td>
		</tr>
<?php }?>
	</tbody>
</table>
<?php	
}

function tallyPositionGroups($outreach, $year){
	global $wpdb;
	$outreach_table_name = $wpdb->prefix . "outreaches";
	$position_table_name = $wpdb->prefix . "outreach_positions";
	$join_table_name = $wpdb->prefix . "outreach_positions_join";
	$phc 	= "a.position_id IN (19, 20, 21, 22, 29, 30, 31, 32)";
	$den 	= "a.position_id BETWEEN '15' AND '18'";
	$opt 	= "a.position_id BETWEEN '26' AND '27'";
	$oph 	= "a.position_id IN (23,24,25,28)";
	$crew 	= "a.position_id BETWEEN '1' AND '10'";
	$gen 	= "a.position_id = 13";
	$tally 	= array('phc' => $phc, 'den' => $den, 
					'opt' => $opt, 'oph' => $oph, 
					'crew' => $crew, 'gen' => $gen);
	
	foreach($tally as $key => $query){
		$data = $wpdb->get_row( 
			"
			SELECT SUM(target) target, approved FROM $join_table_name a
			WHERE a.outreach_id = $outreach->id
			AND advertise = true
			AND $query
			"
		);
		if($data){
			$needed = $data->target - $data->approved;
			if ($needed > 0){
				$totals[$key] = $needed;
			} else {
				$totals[$key] = "-";
			}
		}
	}
	

	return $totals;
}

function setDefaultPositionValues(){

}

function outreach_admin_footer() {
	?>
	<script type="text/javascript">
	jQuery(document).ready(function(){
		jQuery('.datepicker').datepicker({
			dateFormat : 'yy-mm-dd'
		});
	});
	</script>
	<?php
}


?>