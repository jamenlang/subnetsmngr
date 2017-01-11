<?php
/*
 * subnetsmngr
 * Author(s): Sean Kennedy <sean@kndy.net> 
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 *
 */
require('inc/global.php');

if ($_POST) {
	$subnet = $mask = null;
	if ($auto_subnet = $_POST['auto_version'] == 4 ? $_POST['auto_subnet4'] : $_POST['auto_subnet6']) {
		list($subnet, $mask) = explode('/', $auto_subnet);
	}
	$desired_mask = $_POST['auto_version'] == 4 ? $_POST['auto_mask4'] : $_POST['auto_mask6'];
	
	if (!($new_subnet = find_free_subnet($_POST['instance_id'], $_POST['auto_version'], $desired_mask, $subnet ? "$subnet/$mask" : null, true))) {
		$_SESSION['error_msg'] = "There are no unused /{$desired_mask}s available in " . ($subnet == '' ? 'any root subnet' : "$subnet/$mask") . ".";
	} else {
		$new_subnet_addr = substr($new_subnet, 0, strpos($new_subnet, '/'));
		$parent = subnet_get_parent($_POST['instance_id'], "$new_subnet_addr/$desired_mask");
		
		$parent_id = (isset($_POST['variably_subnet']) && $_POST['variably_subnet'] == 1) || ($parent['parent_id'] == 0 && "$new_subnet_addr/$desired_mask" != $new_subnet) ? $parent['id'] : $parent['parent_id'];
		
		// mark parent as used
		$sql = "update subnets set free = '0' where id = '$parent_id'";
		@pg_query($sql) or die("error: failed to run query: $sql");
		
		// remove hosts if we are variably subnetting
		if (isset($_POST['variably_subnet']) && $_POST['variably_subnet'] == 1) {
			$sql = "delete from hosts where subnet_id = '$parent_id'";
			@pg_query($sql) or die("error: failed to run query: $sql");
		
		// remove the subnet we're going to break apart (unless that subnet happens to be a root subnet that we're allocating in
		} else if ($parent['parent_id'] != 0 || "$new_subnet_addr/$desired_mask" == $new_subnet) {
			$sql = "delete from subnets where addr = '$new_subnet'";
			@pg_query($sql) or die("error: failed to run query: $sql");
		}
		
		$create_subnets = subnet_allocate($new_subnet, "$new_subnet_addr/$desired_mask");
		foreach ($create_subnets as $subnet) {
			list($ip,$mask) = explode('/', $subnet);
			if ($subnet == "$new_subnet_addr/$desired_mask") {
				$desc = (!isset($_POST['auto_description']) || !$_POST['auto_description'] ? "NET-" . str_replace(array('.',':','/'), '-', $subnet) : $_POST['auto_description']);
				$notes = !isset($_POST['auto_notes']) ? '' : $_POST['auto_notes'];
				$customer_id = $_POST['auto_customer'] ? "'$_POST[auto_customer]'" : "NULL";
				$vlan = $_POST['vlan'] ? "'{$_POST['vlan']}'" : "NULL";
				
				$sql = "insert into subnets (instance_id, parent_id, customer_id, vlan, addr, free, description, notes, last_updated, " .
						"last_updated_user_id, created, created_user_id) values('{$_POST['instance_id']}', '$parent_id', $customer_id, $vlan, '$subnet', '" . 
						($subnet != "$new_subnet_addr/$desired_mask" ? '1' : (isset($_POST['auto_used']) ? '0' : '1')) . "', " .
						"'$desc', '$notes', current_timestamp, '{$_SESSION['user']['id']}', current_timestamp, '{$_SESSION['user']['id']}')";
				@pg_query($sql) or die("error: failed to run query: $sql");
				
				$sql = "select currval('subnets_id_seq') as subnet_id";
				$result = @pg_query($sql) or die("error: failed to run query: $sql");
				$subnet_id = pg_fetch_result($result, 0, 'subnet_id');
			} else {
				$desc = 'UNUSED; AUTO-ALLOCATED';
				$notes = '';
				$customer_id = "NULL";
				
				$sql = "insert into subnets (instance_id, parent_id, customer_id, addr, free, description, notes, last_updated, " .
						"last_updated_user_id) values('{$_POST['instance_id']}', '$parent_id', $customer_id, '$subnet', '" . 
						($subnet != "$new_subnet_addr/$desired_mask" ? '1' : (isset($_POST['auto_used']) ? '0' : '1')) . "', " .
						"'$desc', '$notes', current_timestamp, '{$_SESSION['user']['id']}')";
				@pg_query($sql) or die("error: failed to run query: $sql");
			}			
		}
		$_SESSION['notice_msg'] = "Subnet <a href=\"hosts.php?subnet_id=$subnet_id\">$new_subnet_addr/$desired_mask</a> added.";
	}
	if (isset($_POST['goto'])) {
		if (isset($subnet_id)) {
			header("Location: " . str_replace("%d", $subnet_id, $_POST['goto']));
			exit;	
		} else {
			header("Location: {$_SERVER['HTTP_REFERER']}");
			exit;
		}
	}
}

if (isset($_GET['in_subnet_id'])) {
	$in_subnet = subnet_get($_GET['in_subnet_id']);
	$variably_subnet = 1;
	if (isset($_GET['variably_subnet']) && $_GET['variably_subnet'] == 0) {
		$variably_subnet = 0;
	}
}

$sql = "select id, addr, description from subnets where parent_id = '0' order by addr asc";
$result = @pg_query($sql) or die("error: failed to run query: $sql");
$subnets = array();
while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
	$subnets[] = $row;
}

$sql = "select id, name from customers order by name asc";
$result = @pg_query($sql) or die("error: failed to run query: $sql");
$customers = array();
while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
	$customers[] = $row;
}

$js = <<<END_JS
<script type="text/javascript">
function populateSubnetSelect(selectEle, instanceId, ver)
{
	var req = false;
	// For Safari, Firefox, and other non-MS browsers
	if (window.XMLHttpRequest) {
		try {
			req = new XMLHttpRequest();
		} catch (e) {
			req = false;
		} 
	} else if (window.ActiveXObject) {
		// For Internet Explorer on Windows
		try {
			req = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try {
				req = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e) {
				req = false;
			}
		}
	}
	
	req.onreadystatechange = function() {
		if (req.readyState == 4 && req.status  == 200) {
			selectEle.options.length = 1;
			var lines = req.responseText.split(/\\n/);
			for (var i = 0; i < lines.length; i++) {
				if (lines[i] != '') {
					var opt = document.createElement('option');
					opt.text = lines[i];
					opt.value = lines[i];
					selectEle.options.add(opt);
				}
			}
		}
	};
	
	req.open("GET", "/index.php?a=ajax-subnet-list&instance_id=" + instanceId + "&parent_id=0&ver=" + ver, true);
	req.send(null);
}

function populateSubnetSelects()
{
	var instanceId = document.getElementById('instance_id').options[document.getElementById('instance_id').selectedIndex].value;
	populateSubnetSelect(document.getElementById('auto_subnet4'), instanceId, 4);
	populateSubnetSelect(document.getElementById('auto_subnet6'), instanceId, 6);
}
</script>
END_JS;

layout_header('Allocate Subnet', $js);
?>
<h1>Allocate Subnet</h1>
<form action="allocate.php" method="post" class="general">
<ul>
<?php if (isset($in_subnet)): ?>
<input type="hidden" name="auto_version" value="<?php echo $in_subnet['ip_family']; ?>" />
<input type="hidden" name="variably_subnet" value="<?php echo $variably_subnet; ?>" />
<input type="hidden" name="instance_id" value="<?php echo $in_subnet['instance_id']; ?>" />
<ul>
<li>
<div class="label">Instance:</div> <?php echo $in_subnet['instance_name']; ?>
</li>
<?php else: ?>
<ul>
<li>
<label for="instance_id" class="label">Instance:</label>
<select name="instance_id" id="instance_id" onchange="populateSubnetSelects();">
<?php
	foreach (instances_get() as $i) {
		echo '<option value="' . $i['id'] . '"';
		if (ifsetor($_POST['instance_id'], $_SESSION['user']['instance_id']) == $i['id']) {
			echo ' selected="selected"';
		}
		echo '>' . $i['name'] . '</option>';
	}
?>
</select>
</li>
<li>
<div class="label">Version:</div>
<input onclick="if (this.checked) {
	document.getElementById('auto_mask4_dropdown').style.display = 'block';
	document.getElementById('auto_mask6_dropdown').style.display = 'none';
	document.getElementById('within_subnet4').style.display = 'block';
	document.getElementById('within_subnet6').style.display = 'none';
} else {
	document.getElementById('auto_mask6_dropdown').style.display = 'block';
	document.getElementById('auto_mask4_dropdown').style.display = 'none';
	document.getElementById('within_subnet4').style.display = 'none';
	document.getElementById('within_subnet6').style.display = 'block';
}" type="radio" name="auto_version" value="4"<?php echo (ifsetor($_POST['auto_version'], 4) == 4 ? ' checked="checked"' : ''); ?> id="auto_version_4" /> <label for="auto_version_4">IPv4</label>
<input onclick="if (this.checked) {
	document.getElementById('auto_mask4_dropdown').style.display = 'none';
	document.getElementById('auto_mask6_dropdown').style.display = 'block';
	document.getElementById('within_subnet4').style.display = 'none';
	document.getElementById('within_subnet6').style.display = 'block';
} else {
	document.getElementById('auto_mask6_dropdown').style.display = 'none';
	document.getElementById('auto_mask4_dropdown').style.display = 'block';
	document.getElementById('within_subnet4').style.display = 'block';
	document.getElementById('within_subnet6').style.display = 'none';
}" type="radio" name="auto_version" value="6"<?php echo (ifsetor($_POST['auto_version'], 4) == 6 ? ' checked="checked"' : ''); ?> id="auto_version_6" /> <label for="auto_version_6">IPv6</label>
</li>
<?php endif; ?>
<?php if (!isset($in_subnet) || $in_subnet['ip_family'] == 6): ?>
<li id="auto_mask6_dropdown" style="display:<?php echo (ifsetor($_POST['auto_version'], 4) == 6 || (isset($in_subnet) && $in_subnet['ip_family'] == 6) ? 'block' : 'none'); ?>">
<label for="auto_mask6" class="label">Block:</label> <?php echo subnet_mask_dropdown('auto_mask6', 6, (isset($in_subnet) ? $in_subnet['cidr']+1 : 1), true); ?>
</li>
<?php endif; ?>
<?php if (!isset($in_subnet) || $in_subnet['ip_family'] == 4): ?>
<li id="auto_mask4_dropdown" style="display:<?php echo (ifsetor($_POST['auto_version'], 4) == 4 ? 'block' : 'none'); ?>">
<label for="auto_mask4" class="label">Block:</label> <?php echo subnet_mask_dropdown('auto_mask4', 4, (isset($in_subnet) ? $in_subnet['cidr']+1 : 1), true); ?>
</li>
<?php endif; ?>
<?php if (!isset($in_subnet)): ?>
<li id="within_subnet6" style="display:<?php echo (ifsetor($_POST['auto_version'], 4) == 6 ? 'block' : 'none'); ?>">
<label class="label" for="auto_subnet6">Within Root Subnet:</label>
<select name="auto_subnet6" id="auto_subnet6"><option value="">Any</option>
<?php
	/**
	foreach ($subnets as $subnet) {
		list($addr,$mask) = explode('/', $subnet['addr']);
		if (ip_version($addr) != 6)
			continue;
		
		echo '<option value="' . $subnet['addr'] . '"';
		if (ifsetor($_POST['auto_subnet6']) == $subnet['addr'])
			echo ' selected="selected"';
		echo '>' . $subnet['addr'] . ' (' . $subnet['description'] . ')</option>';
	}
	**/
?>
</select>
</li>
<li id="within_subnet4" style="display:<?php echo (ifsetor($_POST['auto_version'], 4) == 4 ? 'block' : 'none'); ?>">
<label for="auto_subnet4" class="label">Within Root Subnet:</label>
<select name="auto_subnet4" id="auto_subnet4"><option value="">Any</option>
<?php
	/**
	foreach ($subnets as $subnet) {
		list($addr,$mask) = explode('/', $subnet['addr']);
		if (ip_version($addr) != 4)
			continue;
		
		echo '<option value="' . $subnet['addr'] . '"';
		if (ifsetor($_POST['auto_subnet4']) == $subnet['addr'])
			echo ' selected="selected"';
		echo '>' . $subnet['addr'] . ' (' . $subnet['description'] . ')</option>';
	}
	**/
?>
</select>
</li>
<?php else: ?>
<li>
<div class="label">Within Subnet:</div>
<input type="hidden" name="auto_subnet<?php echo $in_subnet['ip_family']; ?>" value="<?php echo $in_subnet['addr']; ?>" /><?php echo $in_subnet['addr']; ?> &nbsp;&nbsp;&nbsp; (<b>Warning:</b> All hosts within this subnet are going to get nuked)
</li>
<?php endif; ?>
<li>
<div class="label">Mark Used?</div>
<input type="checkbox" value="1" name="auto_used"<?php if (ifsetor($_POST['auto_used'], 1)) { echo ' checked="checked"'; } ?> />
</li>
<li>
<label class="label" for="auto_customer">Customer:</label>
<select name="auto_customer"><option value="">None</option>
<?php
foreach ($customers as $customer) {
	echo '<option value="' . $customer['id'] . '"';
	if (ifsetor($_POST['auto_customer']) == $customer['id'])
		echo ' selected="selected"';
	echo '>' . $customer['name'] . '</option>';
}
?>
</select>
</li>
<li>
<label for="vlan" class="label">VLAN:</label>
<input id="vlan" type="text" name="vlan" maxlength="4" size="5" />
</li>
<li>
<label class="label" for="auto_description">Description:</label>
<textarea name="auto_description" rows="8" cols="50"><?php echo ifsetor($_POST['auto_description']); ?></textarea>
</li>
<li>
<label class="label" for="auto_notes">Notes:</label>
<textarea name="auto_notes" rows="8" cols="50"><?php echo ifsetor($_POST['auto_notes']); ?></textarea>
</li>
<li>
<div class="label">&nbsp;</div>
<input type="submit" value="  Allocate  " name="auto" />
</li>
</ul>
</form>
<script type="text/javascript">populateSubnetSelects();</script>
<?php
layout_footer();
