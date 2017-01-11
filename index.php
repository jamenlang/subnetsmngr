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

if (isset($_GET['instance_id'])) {
	$_SESSION['user']['instance_id'] = $_GET['instance_id'];
	if (isset($_GET['goto'])) {
		header("Location: " . urldecode($_GET['goto']));
	}
} else if (!isset($_SESSION['user']['instance_id'])) {
	$sql = "select default_instance_id from users where id = '{$_SESSION['user']['id']}'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$_SESSION['user']['instance_id'] = pg_fetch_result($result, null, 'default_instance_id');
}

$a = isset($_GET['a']) ? $_GET['a'] : 'main';
if ($a == 'main') {
	$subnet_id = isset($_GET['subnet_id']) ? $_GET['subnet_id'] : 0;
	
	// do we have any subnets at all?
	if ($subnet_id == 0) {
		$sql = "select id from subnets where instance_id = '{$_SESSION['user']['instance_id']}' limit 1";
		$result = @pg_query($sql) or die("error: failed to run query: $sql");
		if (pg_num_rows($result) == 0) {
			header("Location: index.php?a=add");
			exit;
		}
	}
	
	// if this subnet has no children, go to hosts list instead
	$sql = "select count(id) as num_children from subnets where parent_id = '$subnet_id' and instance_id = '{$_SESSION['user']['instance_id']}'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	if (($num_children = pg_fetch_result($result, 0, 'num_children')) == 0) {
		header("Location: hosts.php?subnet_id=$subnet_id");
		exit;
	}
	
	$subnet_tree = array();
	if ($subnet_id != 0) {
		$subnet_tree = subnet_get_tree($subnet_id);
	}
	
	$sql = "select subnets.id, subnets.parent_id, to_char(subnets.last_updated, 'Mon DD, YYYY HH12:MIam') as last_updated, subnets.free, " .
			"subnets.addr, masklen(subnets.addr) as cidr, subnets.description, subnets.vlan, subnets.notes, subnets.swiped, " .
			"age(current_timestamp, subnets.last_swiped) as lastswiped, customers.id as cust_id, customers.name as cust_name " .
			"from subnets left join customers on customers.id = subnets.customer_id where subnets.parent_id = '$subnet_id' and instance_id = '{$_SESSION['user']['instance_id']}'";
	
	if (isset($_GET['showused']) && $_GET['showused'] != 'Used & Unused') {
		$sql .= " and free = '" . ($_GET['showused'] == 'Used' ? '0' : '1') . "'";
	}
	$sql .= " order by addr asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	
	$subnet_rows = array();
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		$subnet_rows[] = $row;
	}
	
	// sort array naturally
	//usort($subnet_rows, function ($a,$b) {
	//	return strnatcmp($a['addr'], $b['addr']);
	//});	
	
	$sidebar = '<aside class="rightbar" id="rightbar">';
	if ($subnet_id) {
		$sidebar .= '<div class="header">' . $subnet_tree[count($subnet_tree)-1]['addr'];
		$sidebar .= '</div>';
		$sidebar .= '<div class="subheader">description</div>';
		$sidebar .= '<p>' . ($subnet_tree[0]['description'] ? nl2br($subnet_tree[0]['description']) : 'N/A') . '</p>';
		$sidebar .= '<div class="subheader">notes</div>';
		$sidebar .= '<p>' . ($subnet_tree[0]['notes'] ? nl2br($subnet_tree[0]['notes']) : 'N/A') . '</p>';
	}
	
	$sidebar .= '<div class="header">options</div>';
	$sidebar .= '<ul>';
	if ($subnet_id) {
		$sidebar .= '<li><a href="index.php?a=mod&id=' . $subnet_id . '">Modify</a></li>';
		$sidebar .= '<li><a href="index.php?a=del&id=' . $subnet_id . '" onclick="if (!confirm(\'Are you sure you want to delete this subnet?  Every subnet/host under it will be removed!\')) { return false; }">Delete</a></li>';
		if (defined('ROUTE_CHECK') && rc_get_device_config($_SESSION['user']['instance_id'])) {
			$sidebar .= '<li><a href="index.php?a=route-check&route=' . $subnet_tree[count($subnet_tree)-1]['addr'] . '">Route Check</a></li>';
			if (subnet_has_children($_GET['subnet_id'])) {
				$sidebar .= '<li><a href="?subnet_id=' . $_GET['subnet_id'] . '&routecheck=true">Route Check Children</a></li>';
			}
		}
		$sidebar .= '<li class="nolink">Allocate: ';
		$sidebar .= '<form action="allocate.php" method="post">';
		$sidebar .= '<input type="hidden" name="auto_used" value="1" />';
		$sidebar .= '<input type="hidden" name="instance_id" value="' . $subnet_tree[0]['instance_id'] . '" />';
		$sidebar .= '<input type="hidden" name="auto_version" value="' . $subnet_tree[count($subnet_tree)-1]['ip_family'] . '" />';
		$sidebar .= '<input type="hidden" name="auto_subnet' . $subnet_tree[0]['ip_family'] . '" value="' . $subnet_tree[0]['addr'] . '" />';
		$sidebar .= '<input type="hidden" name="goto" value="index.php?a=mod&id=%d" />';
		$sidebar .= subnet_mask_dropdown('auto_mask' . $subnet_tree[count($subnet_tree)-1]['ip_family'], $subnet_tree[0]['ip_family'], $subnet_tree[0]['cidr']+1) . ' <input type="submit" value="go" />';
		$sidebar .= '</form>';
		$sidebar .= '</li>';
	} else {
		$sidebar .= '<li><a href="index.php?a=add">Add Subnet</a></li>';
	}
	
	$sidebar .= '</ul>';
	
	if (!$subnet_id) {
		$sql = "select u.username, h.addr, h.subnet_id from hosts as h left join users as u on u.id = h.created_user_id left join subnets as s on s.id = h.subnet_id where s.instance_id = '{$_SESSION['user']['instance_id']}' order by h.created desc nulls last limit 6";
		if (($rh_res = @pg_query($sql)) && pg_num_rows($rh_res) > 0) {
			$sidebar .= '<div class="header un">recently added hosts</div><ul class="un">';
			while ($rh_row = pg_fetch_assoc($rh_res)) {
				$sidebar .= '<li><a href="host.php?subnet_id=' . $rh_row['subnet_id'] . '&addr=' . $rh_row['addr'] . '">' . $rh_row['addr'];
				if ($rh_row['username']) {
					$sidebar .= ' (' . $rh_row['username'] . ')';
				}
				$sidebar .= '</a></li>';
			}
			$sidebar .= '</ul>';
		}
	}

	$sidebar .= '</aside>';
	
	//
	// fetch this subnet or-longer from router
	//
	if ($routecheck = isset($_GET['routecheck']) && $_GET['routecheck'] == 'true') {
		$routes = rc_get_route_info($_SESSION['user']['instance_id'], $subnet_tree[count($subnet_tree)-1]['addr'], true);
	}
	
	layout_header($subnet_tree ? $subnet_tree[count($subnet_tree)-1]['addr'] : 'Subnets', '<script>window.onload=function(){ if (isMobile.any()) { populateNearbyGroupOptions(8); } };</script>', $sidebar);
	
	echo subnet_tree_breadcrumb($subnet_tree);

	if ($routecheck) {
		$rtr = rc_get_device_config($_SESSION['user']['instance_id'])['host'];
		echo '<div style="border:1px solid #000; padding:6px; background-color: #ccc; margin:4px">Route Check from the perspective of router @ <b>' . $rtr . '</b>.<br />&nbsp; &nbsp; NM = No Match, NEM = No Exact Match, U = Unknown Protocol, Stat = Static, Loc = Local, Dir = Directly Connected</div>';
	}
	
	if (pg_num_rows($result) == 0) {
		if (isset($_GET['showused'])) {
			if ($_GET['showused'] == 'Used') {
				echo '<p>There are no used subnets.</p>';
			} else {
				echo '<p>There are no unused subnets.</p>';
			}
		} else {
			echo '<p>There are no root subnets.  <a href="?a=add">Add one</a>.</p>';
		}
	} else {
		echo '<div class="showused">';
		echo '<form action="index.php" method="get">';
		if ($subnet_id) {
			echo '<input type="hidden" name="subnet_id" value="' . $_GET['subnet_id'] . '" />';
		}
		echo 'Show: <select name="showused" onchange="this.form.submit();">';

		foreach (array('Used &amp; Unused', 'Used', 'Unused') as $v) {
			echo '<option value="' . $v . '"';
			if (ifsetor($_GET['showused']) == $v)
				echo ' selected="selected"';
			echo '>' . $v . '</option>';
		}
		echo '</select>&nbsp;';
		echo '</form>';
		echo '</div>';
		echo '<table>';
		echo '<tr>';
		if ($routecheck) {
			echo '<th>RC</th>';
		}
		echo '<th>Subnet</th>';
		echo '<th>Description</th>';
		echo '<th class="un">Free</th>';
		echo '<th class="un">Customer</th>';
		echo '<th class="un">VLAN</th>';
		echo '<th>Updated</th>';
		echo '</tr>';
		reset($subnet_rows);
		foreach ($subnet_rows as $row) {
			// if subnet has subnet-children, calculate 'free hosts' as any host that is within a 'free' subnet rather than 
			// every free host within every free or used subnet
			if (subnet_has_children($row['id'])) {
				$free_hosts = free_hosts($_SESSION['user']['instance_id'], $row['addr'], true);
				$nmask = ($ipv = ip_version($row['addr'])) == 4 ? inet_cidrton($row['cidr']) : inet6_cidrton($row['cidr']);
				$usage_width = floor(bcmul(@bcdiv(bcsub($nmask, $free_hosts), ip_version($row['addr']) == 4 ? ($nmask-2) : $nmask, 10), 100));
			} else {
				$free_hosts = free_hosts($_SESSION['user']['instance_id'], $row['addr'], false);
				$nmask = ($ipv = ip_version($row['addr'])) == 4 ? inet_cidrton($row['cidr']) : inet6_cidrton($row['cidr']);
				$usage_width = floor(bcmul(@bcdiv(bcsub($nmask, $free_hosts), ip_version($row['addr']) == 4 ? ($nmask-2) : $nmask, 10), 100));
			}
			echo '<tr onclick="rowLink(event, \'?subnet_id=' . $row['id'] . '\');" class="clickable">';
			if ($routecheck) {
				$paddr = $subnet_tree[count($subnet_tree)-1]['addr'];
				$found = false;
				foreach ($routes as $r) {
					if ($r['destination'] == $row['addr']) {
						switch ($r['protocol']) {
							case 'Static':
							$p = 'Stat';
							break;
							case 'Local':
							$p = 'Loc';
							break;
							case 'Direct':
							$p = 'Dir';
							break;
							case 'BGP':
							$p = 'BGP';
							break;
							case 'OSPF':
							$p = 'OSPF';
							break;
							default:
							$p = 'U';
							break;
						}
						echo '<td align="center" style="background-color: #c6ffc6">' . $p . '</td>';
						$found = 'exact';
						break;
					}
				}
				if (!$found) {
					foreach ($routes as $r) {
						if ($r['destination'] != $paddr && (ip_in_network(explode('/', $row['addr'])[0], $r['destination']) || ip_in_network($r['destination'], $row['addr']))) {
							$found = 'sub';
						}
					}
					if ($found) {
						echo '<td align="center" style="background-color: #e2b6fb">NEM</td>';
					}
				}
				if (!$found) {
					echo '<td align="center" style="background-color: ' . ($row['free'] ? '#c6ffc6' : '#ffb5b5') . '">NM</td>';
				}
			}			
			echo '<td class="addr"><a href="?subnet_id=' . $row['id'] . '">' . $row['addr'] . '</a><br />';
			echo '<div class="subnet-usage subnet-unused"><div style="width:' . $usage_width . 'px" class="subnet-used">&nbsp;</div></div>';
			echo '</td>';
			echo '<td>';
			if (strstr($row['description'], "\n") || strlen($row['description']) > 42)
				echo substr(preg_replace('/[\r\n]+/', ' ', $row['description']), 0, 32) . '...' . substr(preg_replace('/[\r\n]+/', ' ', $row['description']), -10);
			else
				echo $row['description'];
			echo '</td>';
			echo '<td class="un" align="center"';
			if ($row['free']) {
				echo ' style="background-color: #c6ffc6;"';
			} else {
				echo ' style="background-color: #ffb5b5;"';
			}
			echo '>' . ($row['free'] ? 'Yes' : 'No') . '</td>';
			echo '<td class="un">' . ($row['cust_name'] ? $row['cust_name'] : 'N/A') . '</td>';
			echo '<td class="un" align="center">' . ($row['vlan'] ? $row['vlan'] : 'N/A') . '</td>';
			echo '<td align="center">' . ($row['last_updated'] ? ago(strtotime($row['last_updated'])) . ' ago' : 'N/A') . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	layout_footer();
} else if ($a == 'add') {
	if ($_POST) {
		$addr = $_POST['addr'];
		$mask = $_POST['version'] == 4 ? $_POST['mask4'] : $_POST['mask6'];
		if (!subnet_valid("$addr/$mask"))
			$_SESSION['error_msg'] = "Invalid subnet address: $addr/$mask";
		else {
			if ($instance_name = trim($_POST['instance_name'])) {
				$sql = "insert into instances (name) values('$instance_name')";
				@pg_query($sql) or die("error: failed to run query: $sql");
				
				$sql = "select currval('instances_id_seq') as instance_id";
				$result = @pg_query($sql) or die("error: failed to run query: $sql");
				$instance_id = pg_fetch_result($result, 0, 'instance_id');
			} else {
				$instance_id = $_POST['instance_id'];
			}
			
			if (!$_POST['description'])
				$desc = "NET-" . str_replace(array('.',':'), '-', $addr) . "-$mask";
			else
				$desc = $_POST['description'];
			
			if (!($conflicts = subnet_conflicts($instance_id, "$addr/$mask"))) {
				$sql = "insert into subnets (instance_id, parent_id, addr, free, description, noautoalloc, last_updated_user_id, last_updated, created_user_id, created) values('$instance_id', '0', '$addr/$mask', '1', '$desc', '" .
					(isset($_POST['noautoalloc']) ? '1' : '0') . "', '{$_SESSION['user']['id']}', now(), '{$_SESSION['user']['id']}', now())";
				@pg_query($sql) or die("error: failed to run query: $sql");
				$_SESSION['notice_msg'] = "Subnet added.";
				header("Location: index.php");
				exit;
			} else {
				$_SESSION['error_msg'] = "Subnet $addr/$mask conflicts with:<ul>";
				foreach ($conflicts as $conflict) {
					$_SESSION['error_msg'] .= "<li>{$conflict['addr']}</li>";
				}
				$_SESSION['error_msg'] .= "</ul>";
			}
		}
	}
	
	layout_header('Add Root Subnet');
?>
<h1>Add Root Subnet</h1>
<form action="?a=add" method="post" class="general">
<ul>
<li>
<label class="label" for="instance_id">Instance:</label>
<select name="instance_id" id="instance_id">
<?php
	foreach (instances_get() as $i) {
		echo '<option value="' . $i['id'] . '"';
		if (ifsetor($_POST['instance_id'], $_SESSION['user']['instance_id']) == $i['id']) {
			echo ' selected="selected"';
		}
		echo '>' . $i['name'] . '</option>';
	}
?>
</select> or add new: <input type="text" name="instance_name" value="" style="width:20%" />
</li>
<li>
<div class="label">Version:</div>
<input onclick="if (this.checked) {
	document.getElementById('ipv4_dropdown').style.display = 'block';
	document.getElementById('ipv6_dropdown').style.display = 'none';
} else {
	document.getElementById('ipv6_dropdown').style.display = 'block';
	document.getElementById('ipv4_dropdown').style.display = 'none';
}" type="radio" name="version" id="version_4" value="4"<?php echo (ifsetor($_POST['version'], 4) == 4 ? ' checked="checked"' : ''); ?> /> <label for="version_4">IPv4</label>
<input id="version_6" onclick="if (this.checked) {
	document.getElementById('ipv4_dropdown').style.display = 'none';
	document.getElementById('ipv6_dropdown').style.display = 'block';
} else {
	document.getElementById('ipv6_dropdown').style.display = 'none';
	document.getElementById('ipv4_dropdown').style.display = 'block';
}" type="radio" name="version" value="6"<?php echo (ifsetor($_POST['version'], 4) == 6 ? ' checked="checked"' : ''); ?> /> <label for="version_6">IPv6</label>
</li>
<li>
<label class="label" for="address">Address:</label>
<input type="text" name="addr" style="width:20%" value="<?php echo ifsetor($_POST['addr']); ?>" />
</li>
<li id="ipv6_dropdown" style="display:<?php echo (ifsetor($_POST['version'], 4) == 6 ? 'block' : 'none'); ?>">
<label class="label" for="mask6">Mask:</label> <?php echo subnet_mask_dropdown('mask6', 6, 1, true); ?>
</li>
<li id="ipv4_dropdown" style="display:<?php echo (ifsetor($_POST['version'], 4) == 4 ? 'block' : 'none'); ?>">
<label class="label" for="mask4">Mask:</label> <?php echo subnet_mask_dropdown('mask4', 4, 1, true); ?>
</li>
<li>
<label for="noautoalloc" class="label">Exclude from Auto-alloc:</label>
<input type="checkbox" name="noautoalloc" value="1"<?php echo (isset($_POST['noautoalloc']) ? ' checked="checked"' : ''); ?> />
</li>
<li>
<label class="label" for="description">Description:</label>
<textarea name="description" rows="8" style="width:40%"><?php echo ifsetor($_POST['description']); ?></textarea>
</li>
<li>
<div class="label">&nbsp;</div>
<button type="submit" name="manual">Add Subnet</button>
</li>
</ul>
</form>
<?php
	layout_footer();
}
else if ($a == 'del')
{
	if (!isset($_GET['id'])) exit;
	
	$sql = "select customer_id, addr from subnets where parent_id = '$_GET[id]' and swiped = '1' and customer_id is not null";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	if (pg_num_rows($result) > 0) {
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			// get current swip record so we can send the correct netname and originas
			$swip = get_swip($row['addr']);
			if (isset($swip['netname']) && isset($swip['originas'])) {
				if (!send_swip($row['addr'], 'R', $row['customer_id'], $swip['netname'], null, null, $swip['originas']))
					$failed_swips[] = $subnet;
			} else
				$failed_swips[] = $subnet;
		}
		if ($failed_swips) {
			$_SESSION['error_msg'] = 'Failed to send removal SWIPs for the following subnets:<ul>' . implode('<li>', $failed_swips) . '</ul>';
		}
	}
	
	// constraints will remove subnets/hosts for this base subnet
	if (!subnet_delete_children($_GET['id'])) {
		return false;
	}
	$sql = "delete from subnets where id = '{$_GET['id']}'";
	@pg_query($sql) or die("error: failed to run query: $sql");
	
	$_SESSION['notice_msg'] = 'Subnet removed.';
	
	header("Location: index.php");
} else if ($a == 'mod') {
	if (!isset($_GET['id'])) exit;
	
	$sql = "select *, users.username as last_updated_user from subnets left join users on users.id = subnets.last_updated_user_id " .
			"where subnets.id = '{$_GET['id']}'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$row = pg_fetch_array($result, null, PGSQL_ASSOC);
	
	if ($_POST) {
		if (isset($_POST['delete'])) {
			if (!@subnet_delete($_GET['id'])) {
				$_SESSION['error_msg'] = 'Failed to remove subnet ' . $row['addr'] . '.';
			} else {
				$_SESSION['notice_msg'] = 'Subnet ' . $row['addr'] . ' removed.';
				header("Location: index.php");
				exit;
			}
		} else {
			if (isset($_POST['used']) && !$_POST['description'] && !$_POST['notes']) {
				$_SESSION['error_msg'] = 'Enter a description and/or some notes.';
			} else if (!isset($_POST['used'])) {
				if (!subnet_mark_unused($_GET['id']))
					$_SESSION['error_msg'] = 'Failed to mark subnet ' . $row['addr'] . ' as unused.';
				else
					$_SESSION['notice_msg'] = 'Subnet ' . $row['addr'] . ' marked unused.';
				header("Location: ?subnet_id={$_POST['parent_id']}");
				exit;
			} else {
				$vlan = $_POST['vlan'] ? "'{$_POST['vlan']}'" : "NULL";
				$sql = "update subnets set vlan = $vlan, description = '$_POST[description]', notes = '$_POST[notes]', free = '" .
						(isset($_POST['used']) ? 0 : 1) . "', customer_id = " . ($_POST['customer'] ? "'$_POST[customer]'" : "NULL") . ", " .
						"last_updated = now(), last_updated_user_id = '{$_SESSION['user']['id']}', noautoalloc = '" . (isset($_POST['noautoalloc']) ? '1' : '0') . "' where id = '$_GET[id]'";
				@pg_query($sql) or die("error: failed to run query: $sql");
				
        	                $sql = "delete from group_subnets where subnet_id = '{$_GET['id']}'";
	                        @pg_query($sql) or die("error: failed to run query: $sql");

                	        foreach ($_POST['groups'] as $group_id) {
        	                        $sql = "insert into group_subnets (subnet_id, group_id) values('$_GET[id]', '$group_id')";
	                                @pg_query($sql) or die("error: failed to run query: $sql");
	                        }
				
				$_SESSION['notice_msg'] = 'Subnet <a href="?subnet_id=' . $_GET['id'] . '">' . $row['addr'] . '</a> updated.';
				header("Location: ?subnet_id={$_POST['parent_id']}");
				exit;
			}
		}
	}
	
	$sql = "select id, name from customers order by name asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$customers = array();
	while ($crow = pg_fetch_array($result, null, PGSQL_ASSOC))
		$customers[] = $crow;
	
	$sql = "select id, name from groups order by name asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$groups = array();
	while ($grow = pg_fetch_array($result, null, PGSQL_ASSOC))
		$groups[] = $grow;
	$sql = "select group_id from group_subnets where subnet_id = '$_GET[id]'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$selected_groups = array();
	while ($gsrow = pg_fetch_array($result, null, PGSQL_ASSOC))
		$selected_groups[] = $gsrow['group_id'];

	
	$head_code = <<<END_CODE
<script type="text/javascript">
var newwindow;
function poptastic(url)
{
  	newwindow=window.open(url,'name','height=400,width=600,scrollbars=yes');
        if (window.focus) {newwindow.focus()}
}
</script>
END_CODE;

	layout_header('Modify Subnet', $head_code);
	
	echo '<h1>Modify Subnet</h1>';
	echo '<form action="?a=mod&id=' . $_GET['id'] . '" method="post" class="general">';
	echo '<input type="hidden" name="parent_id" value="' . $row['parent_id'] . '" />';
	echo '<ul>';
	echo '<li><div class="label">Subnet:</div> ' . $row['addr'] . '</li>';
	echo '<li><div class="label">Last Updated:</div> ' . ($row['last_updated'] ? $row['last_updated'] . ' (' . $row['last_updated_user'] . ')' : 'N/A') . '</li>';
	echo '<li><div class="label">SWIPed?:</div> ' . ($row['swiped'] ? 'Yes (<a href="javascript:poptastic(\'cmd.php?a=whois&amp;addr=' . inet_ntoa($row['addr']) . '\');">Whois</a>)' : 'No');
    echo '<a href="index.php?a=swip&id=' . $_GET['id'] . '"> SWIP</a></li>';
	echo '<li><div class="label">Last SWIPed:</div> ' . ($row['last_swiped'] ? $row['last_swiped'] : 'N/A') . '</li>';
	echo '<li><label for="customer" class="label">Customer:</label> <select id="customer" name="customer"><option value="">None</option>';
	foreach ($customers as $customer) {
		echo '<option value="' . $customer['id'] . '"';
		if (ifsetor($_POST['customer'], $row['customer_id']) == $customer['id'])
			echo ' selected="selected"';
		echo '>' . $customer['name'] . '</option>';
	}
	echo '</select></li>';
	echo '<li><label class="label" for="vlan">VLAN:</label> <input type="text" id="vlan" name="vlan" size="5" maxlength="4" value="' . ifsetor($_POST['vlan'], $row['vlan']) . '" /></li>';
	echo '<li><label class="label" for="description">Description:</label> <textarea id="description" name="description" rows="8" cols="65">' . ifsetor($_POST['description'], ifsetor($row['description'])) . '</textarea></li>';
	echo '<li><label class="label" for="notes">Notes:</label> <textarea id="notes" name="notes" rows="8" cols="65">' . ifsetor($_POST['notes'], ifsetor($row['notes'])) . '</textarea></li>';
	echo '<li><label for="groups" class="label">Groups:</label> <select id="groups" name="groups[]" multiple="multiple" size="10" style="width:40%">';
	foreach ($groups as $group) {
		echo '<option value="' . $group['id'] . '"';
		if (in_array($group['id'], ifsetor($_POST['groups'], $selected_groups)))
			echo ' selected="selected"';
		echo '>' . $group['name'] . '</option>';
	}
	echo '</select></li>';
	echo '<li><label class="label" for="used">Used:</label> <input type="checkbox" id="used" name="used" value="1"' . (ifsetor($_POST['used'], !$row['free']) ? ' checked="checked"' : '') . ' /></li>';
	if ($row['parent_id'] == 0 || subnet_has_children($_GET['id'])) {
		echo '<li><label class="label" for="noautoalloc">Exclude from Auto-allocation:</label> <input id="noautoalloc" type="checkbox" name="noautoalloc" value="1"' . (ifsetor($_POST['noautoalloc'], $row['noautoalloc']) ? ' checked="checked"' : '') . ' /></li>';
	} else {
		//echo '<li><label class="label" for="migrating
	}
	echo '<li><div class="label">&nbsp;</div> <input type="submit" value="  Modify Subnet  " name="modify" />';
	if ($row['parent_id'] == 0) {
		echo ' <input type="submit" style="color:red;" value="  Delete Subnet  " name="delete" onclick="if (!confirm(\'Are you sure?\')) { return false; }" />';
	}
	echo '</li></ul></form>';
	
	layout_footer();
} else if ($a == 'manual_swip') {
	if ($_POST) {
		if ($_POST['type'] == 'netmod') {
			$attribs = array(
				'template' => 'netmod',
				'reg_action' => $_POST['netmod_regaction'],
				'subnet' => $_POST['netmod_subnet'],
				'netname' => $_POST['netmod_netname'],
				'tech_poc_handle' => $_POST['tech_poc_handle'],
				'abuse_poc_handle' => $_POST['abuse_poc_handle'],
				'noc_poc_handle' => $_POST['noc_poc_handle'],
				'originas' => $_POST['netmod_originas'],
				'comments' => $_POST['netmod_comments'],
				'bcc' => $_POST['netmod_bcc']
			);
		} else {
			$attribs = array(
				'template' => 'reassign-simple',
				'reg_action' => $_POST['regaction'],
				'subnet' => $_POST['subnet'],
				'netname' => $_POST['netname'],
				'name' => $_POST['name'],
				'addr1' => $_POST['addr1'],
				'addr2' => $_POST['addr2'],
				'city' => $_POST['city'],
				'state' => $_POST['state'],
				'zip' => $_POST['zip'],
				'country' => $_POST['country'],
				'originas' => $_POST['originas'],
				'comments' => $_POST['comments'],
				'bcc' => $_POST['bcc']
			);
		}
		if (send_manual_swip($attribs)) {
			$_SESSION['notice_msg'] = 'Swip sent.';
		} else {
			$_SESSION['error_msg'] = 'Swip was not sent.';
		}
	}
	
	$script = <<<END_SCRIPT
<script type="text/javascript">
function handleFormTypeChange(ele) {
	var type = ele.options[ele.selectedIndex].value;
	if (type == 'netmod') {
		document.getElementById('netmod_fields').style.display = 'block';
		document.getElementById('reassignsimple_fields').style.display = 'none';
	} else if (type == 'reassign-simple') {
		document.getElementById('netmod_fields').style.display = 'none';
		document.getElementById('reassignsimple_fields').style.display = 'block';
	}
}
</script>
END_SCRIPT;
	layout_header('Send Manual SWIP', $script);
	
	echo '<div class="main-header">';
	echo '<h1>Send Manual SWIP</h1>';
	echo '</div>';
	echo '<div class="main-body">';
	echo '<form action="?a=manual_swip" method="post">';
	echo '<table border="0" cellspacing="0" cellpadding="0" class="formtbl">';
	echo '<tr><td width="180"><b>Form Type:</b></td><td><select id="typeselect" name="type" onchange="handleFormTypeChange(this);">';
	echo '<option value="reassign-simple"' . (ifsetor($_POST['type']) == 'reassign-simple' ? ' selected="selected"' : '') . '>REASSIGN SIMPLE</option>';
	echo '<option value="netmod"' . (ifsetor($_POST['type']) == 'netmod' ? ' selected="selected"' : '') . '>NET-MOD</option>';
	echo '</select></td></tr>';
	echo '</table>';
	echo '<script>handleFormTypeChange(document.getElementById(\'typeselect\'));</script>';
	
	echo '<div id="netmod_fields" style="display:none">';
	echo '<table border="0" cellspacing="0" cellpadding="0" class="formtbl">';
	echo '<tr><td width="180"><b>Action:</b></td><td><select name="netmod_regaction">';
	foreach (array('M' => 'Update', 'R' => 'Remove') as $k => $v) {
		echo '<option value="' . $k . '"';
		if (ifsetor($_POST['netmod_regaction']) == $k)
			echo ' selected="selected"';
		echo '>' . $v . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><td><b>Subnet:</b></td><td><input type="text" name="netmod_subnet" size="25" value="' . ifsetor($_POST['netmod_subnet']) . '" /></td></tr>';
	echo '<tr><td><b>Network Name:</b></td><td><input type="text" name="netmod_netname" value="' . ifsetor($_POST['netmod_netname']) . '" size="30" /> (leave blank to auto-generate this based on Subnet)</td></tr>';
	echo '<tr><td><b>Origin AS:</b></td><td><input type="text" name="netmod_originas" value="' . ifsetor($_POST['netmod_originas'], $config['origin_as']) . '" size="5" /></td></tr>';
	echo '<tr><td><b>Tech POC Handle:</b></td><td><input type="text" name="tech_poc_handle" size="20" value="' . ifsetor($_POST['tech_poc_handle']) . '" /></td></tr>';
	echo '<tr><td><b>Abuse POC Handle:</b></td><td><input type="text" name="abuse_poc_handle" size="20" value="' . ifsetor($_POST['abuse_poc_handle']) . '" /></td></tr>';
	echo '<tr><td><b>NOC POC Handle:</b></td><td><input type="text" name="noc_poc_handle" size="20" value="' . ifsetor($_POST['noc_poc_handle']) . '" /></td></tr>';
	echo '<tr><td valign="top"><b>Public Comments:</b></td><td><textarea name="netmod_comments" rows="8" cols="60">' . ifsetor($_POST['netmod_comments']) . '</textarea></td></tr>';
	echo '<tr><td><b>Blind Copy To (optional):</b></td><td><input type="text" name="netmod_bcc" value="' . ifsetor($_POST['bcc']) . '" size="30" /></td></tr>';
	echo '</table>';
	echo '</div>';
	echo '<div id="reassignsimple_fields">';
	echo '<table border="0" cellspacing="0" cellpadding="0" class="formtbl">';
	echo '<tr><td width="180"><b>Action:</b></td><td><select name="regaction">';
	foreach (array('N' => 'New', 'M' => 'Update', 'R' => 'Remove') as $k => $v) {
		echo '<option value="' . $k . '"';
		if (ifsetor($_POST['regaction'], ($row['swiped'] ? 'M' : 'N')) == $k)
			echo ' selected="selected"';
		echo '>' . $v . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><td><b>Subnet:</b></td><td><input type="text" name="subnet" size="25" value="' . ifsetor($_POST['subnet']) . '" /></td></tr>';
	echo '<tr><td><b>Network Name:</b></td><td><input type="text" name="netname" value="' . ifsetor($_POST['netname']) . '" size="30" /> (leave blank to auto-generate this based on Subnet)</td></tr>';
	echo '<tr><td><b>Origin AS:</b></td><td><input type="text" name="originas" value="' . ifsetor($_POST['originas'], $config['origin_as']) . '" size="5" /></td></tr>';
	echo '<tr><td><b>Customer Name:</b></td><td><input type="text" name="name" size="30" value="' . ifsetor($_POST['name']) . '" /></td></tr>';
	echo '<tr><td><b>Customer Address:</b></td><td><input type="text" name="addr1" size="30" value="' . ifsetor($_POST['addr1']) . '" /></td></tr>';
	echo '<tr><td><b>Customer Address Cont.:</b></td><td><input type="text" name="addr2" size="30" value="' . ifsetor($_POST['addr2']) . '" /></td></tr>';
	echo '<tr><td><b>Customer City:</b></td><td><input type="text" name="city" size="25" value="' . ifsetor($_POST['city']) . '" /></td></tr>';
	echo '<tr><td><b>Customer State:</b></td><td><input type="text" name="state" size="2" value="' . ifsetor($_POST['state']) . '" /></td></tr>';
	echo '<tr><td><b>Customer Zip:</b></td><td><input type="text" name="zip" size="10" value="' . ifsetor($_POST['zip']) . '" /></td></tr>';
	echo '<tr><td><b>Customer Country:</b></td><td><input type="text" name="country" size="5" value="' . ifsetor($_POST['country'], 'US') . '" /></td></tr>';
	echo '<tr><td valign="top"><b>Public Comments:</b></td><td><textarea name="comments" rows="8" cols="60">' . ifsetor($_POST['comments']) . '</textarea></td></tr>';
	echo '<tr><td><b>Blind Copy To (optional):</b></td><td><input type="text" name="bcc" value="' . ifsetor($_POST['bcc']) . '" size="30" /></td></tr>';
	echo '</table>';
	echo '</div>';
	
	echo '<br /><input type="submit" value="  Send SWIP  " />';
	echo '</form>';
	echo '</div>';

	layout_footer();
} else if ($a == 'swip') {
	if (!isset($_GET['id'])) exit;

	$sql = "select subnets.parent_id, subnets.addr, subnets.swiped, subnets.last_swiped, customers.* from customers left join subnets on subnets.customer_id = customers.id where subnets.id = '$_GET[id]'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$row = pg_fetch_array($result, null, PGSQL_ASSOC);
	
	if (!$row['id']) die("error: subnet is not associated with a customer");
	
	if ($_POST) {
		if ($row['swiped'] && $_POST['action'] == 'N')
			$_SESSION['error_msg'] = 'Subnet has already been SWIPed, so you can only do Update or Remove.';
		else if (!send_swip($row['addr'], $_POST['regaction'], $row['id'], $_POST['netname'], $_POST['comments'], $_POST['bcc'], $_POST['originas']))
			$_SESSION['error_msg'] = 'Failed to send SWIP.';
		else
		{
			$_SESSION['notice_msg'] = 'SWIP sent.';
			header("Location: index.php?base_id={$row['base_id']}");
			exit;
		}
	}
	
	// if swiped, pull netname and originas from whois to populate those fields with current info
	if ($row['swiped'])
		$swip = get_swip(inet_ntoa($row['addr']));
	else
		$swip = array();
	
	layout_header('Send SWIP');

	echo '<div class="main-header">';
	echo '<h1>Send SWIP</h1>';
	echo '</div>';
	echo '<div class="main-body">';
	echo '<form action="?a=swip&id=' . $_GET['id'] . '" method="post">';
	echo '<table border="0" cellspacing="0" cellpadding="0" class="formtbl">';
	echo '<tr><td width="180"><b>Action:</b></td><td><select name="regaction">';
	foreach (array('N' => 'New', 'M' => 'Update', 'R' => 'Remove') as $k => $v) {
		echo '<option value="' . $k . '"';
		if (ifsetor($_POST['regaction'], ($row['swiped'] ? 'M' : 'N')) == $k)
			echo ' selected="selected"';
		echo '>' . $v . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><td><b>Subnet:</b></td><td>' . $row['addr'] . '</td></tr>';
	echo '<tr><td><b>Network Name:</b></td><td><input type="text" name="netname" value="' . ifsetor($_POST['netname'], ifsetor($swip['netname'], 'NET-' . str_replace(array('.', '/'), '-', $row['addr']))) . '" size="30" /></td></tr>';
	echo '<tr><td><b>Origin AS:</b></td><td><input type="text" name="originas" value="' . ifsetor($_POST['originas'], ifsetor($swip['originas'], $config['origin_as'])) . '" size="5" /></td></tr>';
	echo '<tr><td><b>Customer Name:</b></td><td>' . $row['name'] . '</td></tr>';
	echo '<tr><td><b>Customer Address:</b></td><td>' . $row['addr1'] . '</td></tr>';
	if ($row['addr2'])
		echo '<tr><td><b>Customer Address Cont.:</b></td><td>' . $row['addr2'] . '</td></tr>';
	echo '<tr><td><b>Customer City:</b></td><td>' . $row['city'] . '</td></tr>';
	echo '<tr><td><b>Customer State:</b></td><td>' . $row['state'] . '</td></tr>';
	echo '<tr><td><b>Customer Zip:</b></td><td>' . $row['zip'] . '</td></tr>';
	echo '<tr><td><b>Customer Country:</b></td><td>' . $row['country'] . '</td></tr>';
	echo '<tr><td valign="top"><b>Public Comments:</b></td><td><textarea name="comments" rows="8" cols="60">' . ifsetor($_POST['comments']) . '</textarea></td></tr>';
	echo '<tr><td><b>Blind Copy To (optional):</b></td><td><input type="text" name="bcc" value="' . ifsetor($_POST['bcc']) . '" size="30" /></td></tr>';
	echo '</table><br /><br />';
	echo '<input type="submit" value="  Send SWIP  " />';
	echo '</form>';
	echo '</div>';
	
	layout_footer();
} else if ($a == 'ajax-subnet-list') {
	header("Content-type: text/plain");
	
	$sql = "select addr from subnets where instance_id = '{$_GET['instance_id']}' and parent_id = '{$_GET['parent_id']}' and family(addr) = '{$_GET['ver']}' order by addr asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		echo $row['addr'] . "\n";
	}
} else if ($a == 'ajax-group-list') {
	header("Content-type: application/json");
	
	$sql = "select * from groups order by name asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		$groups[] = $row;
	}
	echo json_encode($groups);
} else if ($a == 'update-user-loc') {
	if (isset($_GET['lat']) && isset($_GET['lng'])) {
		$sql = "update users set last_lat = '{$_GET['lat']}', last_lng = '{$_GET['lng']}', last_loc_time = now() where id = '{$_SESSION['user']['id']}'";
		@pg_query($sql) or die("error: failed to run query: $sql");
	}
} else if ($a == 'route-check') {
	$instance_id = isset($_GET['instance_id']) ? $_GET['instance_id'] : $_SESSION['user']['instance_id'];
	if (!rc_get_device_config($instance_id)) {
		echo 'No Route Check config for given instance';
		exit;
	}
	if (isset($_GET['route']) && !preg_match('/^[a-zA-Z0-9\.\:\/]+$/', $_GET['route'])) {
		echo 'Invalid route given';
		exit;
	}
	
	layout_header('Route Check');
	
	echo '<h1>Route Check</h1>';
	echo '<form class="general" method="get" action="index.php"><input type="hidden" name="a" value="route-check" />';
	echo '<ul><li>';
	echo '<label for="instance" class="label">Instance:</label>';
	echo '<select name="instance_id" id="instance">';
	foreach (instances_get() as $i) {
		echo '<option value="' . $i['id'] . '"';
		if ($instance_id == $i['id']) {
			echo ' selected="selected"';
		}
		echo '>' . $i['name'] . '</option>';
	}
	echo '</select></li>';
	echo '<li><label for="route" class="label">Route:</label><input type="text" name="route" id="route" value="' . ifsetor($_GET['route']) . '" /></li>';
	echo '<li><label class="label">&nbsp;</label><input type="submit" value=" Lookup Route " /></li>';
	echo '</ul>';
	echo '</form>';

	if (isset($_GET['route'])) {
		echo '<div class="route-check-results">';
		if ($route_info = rc_get_route_info($_SESSION['user']['instance_id'], $_GET['route'])) {
			if ($route_info['destination'] == '0.0.0.0/0' || $route_info['destination'] == '::/0') {
				echo 'No specific route found (default only) using router @ ' . $route_info['router'] . ': ';
			} else if ($route_info['destination'] == $_GET['route']) {
				echo 'Exact match found using router @ ' . $route_info['router'] . ' : ';
			} else {
				echo 'Non-matching route found using router @ ' . $route_info['router'] . ': ';
			}
			echo '<blockquote>';
			echo '<b>' . $route_info['destination'] . '</b> protocol ' . $route_info['protocol'] . ' (' . $route_info['age'] . ' old)';
			echo '</blockquote>';
		} else
			echo 'Error fetching route info.';
		echo '</div>';
	}
	
	layout_footer();
}
