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

$a = isset($_GET['a']) ? $_GET['a'] : 'main';
if ($a == 'main') {
	$sql = "select g.id, g.name, g.lat, g.lng, count(gs.group_id) as num_subnets from groups as g left join group_subnets as gs on gs.group_id = g.id group by g.id, g.name, g.lat, g.lng, gs.group_id order by g.name asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");

	$sidebar = '<aside class="rightbar"><div class="header">options</div><ul><li><a href="group.php?a=add">Add Subnet Group</a></li></ul></aside>';
	
	layout_header('Subnet Groups', null, $sidebar);
	
	echo '<h1>Subnet Groups</span></h1>';
	if (pg_num_rows($result) == 0)
		echo '<p>There are no groups defined.</p>';
	else {
		echo '<table>';
		echo '<tr>';
		echo '<th>Group Name</th>';
		echo '<th>Subnets</th>';
		echo '<th>Location Tag</th>';
		echo '</tr>';
		for ($i = 1; $row = pg_fetch_array($result, null, PGSQL_ASSOC); $i++) {
			echo '<tr class="clickable" onclick="rowLink(event, \'group.php?a=view&amp;id=' . $row['id'] . '\');">';
			echo '<td>' . $row['name'] . '</td>';
			echo '<td>' . $row['num_subnets'] . '</td>';
			echo '<td>';
			if ($row['lat'] && $row['lng']) {
				echo $row['lat'] . ',' . $row['lng'];
			} else
				echo 'N/A';
			echo '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	layout_footer();
} else if ($a == 'view') {
	$sql = "select name, lat, lng from groups where id = '$_GET[id]'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$name = pg_fetch_result($result, 0, 'name');
	$lat = pg_fetch_result($result, 0, 'lat');
	$lng = pg_fetch_result($result, 0, 'lng');
	
	$sql = "select subnets.id, subnets.instance_id, subnets.parent_id, subnets.free, subnets.addr, masklen(subnets.addr) as cidr, subnets.description, subnets.notes, subnets.swiped, " .
			"to_char(subnets.last_swiped, 'Mon DD, YYYY HH12:MIam') as lastswiped, subnets.last_updated, customers.id as cust_id, customers.name as cust_name from " .
			"subnets left join customers on customers.id = subnets.customer_id where subnets.id in (select gs.subnet_id " .
			"from group_subnets as gs where gs.group_id = '{$_GET['id']}') order by subnets.addr asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");

	$sidebar = '<aside class="rightbar">';
	if ($lat && $lng) {
		$sidebar .= '<div class="header">' . $name . '</div><div class="subheader">Location Tag</div><p><a target="_blank" href="http://maps.google.com/maps?z=12&t=m&q=loc:' . $lat . ',' . $lng . '">' . $lat . ',' . $lng . '</a></p>';
	}
	$sidebar .= '<div class="header">options</div><ul><li><a href="?a=mod&id=' . $_GET['id']. '">Modify</a></li><li><a href="?a=del&id='  .$_GET['id'] . '" onclick="if (!confirm(\'Are you sure you want to delete this group?\n\nNOTE: This will NOT delete the subnets associated with this group.\')) { return false; }">Delete</a></li></ul></aside>';
	layout_header($name, '', $sidebar);
	
	echo '<span class="subnet-crumb"><a href="group.php">Subnet Groups</a> <span>//</span> <span class="current">' . $name . '</span></span>';
	
	echo '<table>';
	echo '<tr>';
	echo '<th>Subnet</th>';
	echo '<th class="un">Used?</th>';
	echo '<th class="un">Customer</th>';
	echo '<th>Description</th>';
	echo '<th class="un">Notes</th>';
	echo '<th class="un">Updated</th>';
	echo '<th>Action</th>';
	echo '</tr>';
	
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC))
	{
		if (subnet_has_children($row['id'])) {
			$free_hosts = free_hosts($row['instance_id'], $row['addr'], true);
		} else {
			$free_hosts = free_hosts($row['instance_id'], $row['addr'], false);
		}
		$nmask = ($ipv = ip_version($row['addr'])) == 4 ? inet_cidrton($row['cidr']) : inet6_cidrton($row['cidr']);
		$usage_width = floor(bcmul(bcdiv(bcsub($nmask, $free_hosts), ip_version($row['addr']) == 4 ? ($nmask-2) : $nmask, 10), 100));
		
		//$free_hosts = free_hosts(inet_ntoa($row['addr']) . '/' . inet_ntocidr($row['mask']));
		//$usage_width = floor((($row['mask']-$free_hosts)/($row['mask']-2))*150);
		
		echo '<tr class="clickable" onclick="rowLink(event, \'hosts.php?subnet_id=' . $row['id']. '\');">';
		echo '<td>' . $row['addr']. '<br />';
		echo '<div class="subnet-usage subnet-unused"><div style="width:' . $usage_width . 'px" class="subnet-used">&nbsp;</div></div></td>';
		echo '<td class="un" align="center">' . ($row['free'] ? 'No' : 'Yes') . '</td>';
		echo '<td class="un">' . ($row['cust_name'] ? $row['cust_name'] : 'N/A') . '</td>';
		echo '<td>';
		if (strstr($row['description'], "\n") || strlen($row['description']) > 42)
		{
			echo substr(preg_replace('/[\r\n]+/', ' ', $row['description']), 0, 32) . '...' . substr(preg_replace('/[\r\n]+/', ' ', $row['description']), -10);
		}
		else
			echo $row['description'];
		echo '</td>';
		echo '<td class="un">';
		if (strstr($row['notes'], "\n") || strlen($row['notes']) > 42)
		{
			echo substr(preg_replace('/[\r\n]+/', ' ', $row['notes']), 0, 32) . '...' . substr(preg_replace('/[\r\n]+/', ' ', $row['notes']), -10);
		}
		else
			echo $row['notes'];
		echo '</td>';
		echo '<td class="un" align="center">' . ago(strtotime($row['last_updated'])) . ' ago</td>';
		echo '<td align="center"><a href="index.php?a=mod&amp;id=' . $row['id']. '">Modify</a> / ';
		if ($row['cust_id'] && (($ipv == 4 && $row['cidr'] <= 29) || ($ipv == 6 && $row['cidr'] <= 64)))
			echo '<a href="subnets.php?a=swip&id=' . $row['id'] . '">SWIP</a>';
		else
			echo 'SWIP';
		echo '</td>';
		echo '</tr>';
	}
	echo '</table>';
	
	layout_footer();
} else if ($a == 'add') {
	if ($_POST) {
		if (!$_POST['name'] && !$_POST['subnets'])
			$_SESSION['error_msg'] = "Enter a name and choose associated subnets.";
		else {
			$sql = "insert into groups (name, lat, lng) values('{$_POST['name']}', " . (!empty($_POST['lat']) ? "'{$_POST['lat']}'" : 'null') . ", " . (!empty($_POST['lng']) ? "'{$_POST['lng']}'" : 'null') . ")";
			@pg_query($sql) or die("error: failed to run query: $sql");
			
			$sql = "select currval('groups_id_seq') as id";
			$result = @pg_query($sql) or die("error: failed to run query: $sql");
			$group_id = pg_fetch_result($result, 0, 'id');
			
			foreach ($_POST['subnets'] as $subnet_id) {
				$sql = "insert into group_subnets (group_id, subnet_id) values('$group_id', '$subnet_id')";
				@pg_query($sql) or die("error: failed to run query: $sql");
			}
			$_SESSION['notice_msg'] = "Subnet group " . $_POST['name'] . " added.";
			header("Location: group.php");
			exit;
		}
	}
	$sql = "select id, addr, description from subnets where free = '0' order by addr asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$subnets = array();
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC))
		$subnets[] = $row;
	
	layout_header('Add Subnet Group');
	
	echo '<h1>Add Subnet Group</h1>';
	echo '<form action="?a=add" method="post" class="general">';
	echo '<ul>';
	echo '<li><label for="name" class="label">Group Name:</label> <input type="text" id="name" name="name" size="30" value="' . ifsetor($_POST['name']). '" /></li>';
	echo '<li><label for="subnets" class="label">Associated Subnets:</label> <select id="subnets" name="subnets[]" multiple="multiple" size="20">';
	foreach ($subnets as $subnet) {
		echo '<option value="' . $subnet['id'] . '"';
		if (in_array($subnet['id'], ifsetor($_POST['subnets'], array())))
			echo ' selected="selected"';
		echo '>' . $subnet['addr'];
		if ($subnet['description']) {
			echo ' - ' . (strlen($subnet['description']) > 20 ? (substr($subnet['description'], 0, 20) . '...') : $subnet['description']);
		}
		echo '</option>';
	}
	echo '</select></li>';
	echo '<li><label for="lat" class="label">Latitude:</label> <input type="text" id="lat" name="lat" size="15" value="' . ifsetor($_POST['lat']). '" /></li>';
	echo '<li><label for="lng" class="label">Longitude:</label> <input type="text" id="lng" name="lng" size="15" value="' . ifsetor($_POST['lng']). '" /></li>';
	echo '<li><div class="label">&nbsp;</div> <input type="submit" value="  Add Group  " /></li>';
	echo '</ul>';
	echo '</form>';
	
	layout_footer();
} else if ($a == 'mod') {
	if (!isset($_GET['id'])) exit;
	
	if ($_POST) {
		if (!$_POST['name'] && !$_POST['subnets'])
			$_SESSION['error_msg'] = "Enter a name and choose associated subnets.";
		else {
			$sql = "update groups set name = '{$_POST['name']}', lat = '{$_POST['lat']}', lng = '{$_POST['lng']}' where id = '{$_GET['id']}'";
			@pg_query($sql) or die("error: failed to run query: $sql");
			
			$sql = "delete from group_subnets where group_id = '{$_GET['id']}'";
			@pg_query($sql) or die("error: failed to run query: $sql");
			
			foreach ($_POST['subnets'] as $subnet_id) {
				$sql = "insert into group_subnets (group_id, subnet_id) values('$_GET[id]', '$subnet_id')";
				@pg_query($sql) or die("error: failed to run query: $sql");
			}
			$_SESSION['notice_msg'] = "Subnet group modified.";
			header("Location: group.php?id={$_GET['id']}");
			exit;
		}
	}
	
	$sql = "select id, addr, description from subnets where free = '0' order by addr asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$subnets = array();
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC))
		$subnets[] = $row;
	
	$sql = "select subnet_id from group_subnets where group_id = '$_GET[id]'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$selected_subnets = array();
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC))
		$selected_subnets[] = $row['subnet_id'];
	
	$sql = "select * from groups where id = '$_GET[id]'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$row = pg_fetch_array($result, null, PGSQL_ASSOC);
	
	layout_header('Modify Subnet Group');
	
	echo '<h1>Modify Subnet Group</h1>';
	echo '<form action="?a=mod&id=' . $_GET['id']. '" method="post" class="general">';
	echo '<ul>';
	echo '<li><label for="name" class="label">Group Name:</label> <input type="text" id="name" name="name" size="30" value="' . ifsetor($_POST['name'], $row['name']). '" /></li>';
	echo '<li><label for="subnets" class="label">Associated Subnets:</label> <select id="subnets" name="subnets[]" multiple="multiple" size="40" style="width:500px">';
	foreach ($subnets as $subnet)
	{
		echo '<option value="' . $subnet['id'] . '"';
		if (in_array($subnet['id'], ifsetor($_POST['subnets'], $selected_subnets)))
			echo ' selected="selected"';
		echo '>' . $subnet['addr'];
		if ($subnet['description'])
		{
			echo ' - ' . (strlen($subnet['description']) > 20 ? (substr($subnet['description'], 0, 20) . '...') : $subnet['description']);
		}
		echo '</option>';
	}
	echo '</select></li>';
	echo '<li><label for="lat" class="label">Latitude:</label> <input type="text" id="lat" name="lat" size="15" value="' . ifsetor($_POST['lat'], $row['lat']). '" /></li>';
	echo '<li><label for="lng" class="label">Longitude:</label> <input type="text" id="lng" name="lng" size="15" value="' . ifsetor($_POST['lng'], $row['lng']). '" /></li>';
	echo '<li><div class="label">&nbsp;</div> <input type="submit" value="  Modify Group  " /></li>';
	echo '</ul>';
	echo '</form>';
	
	layout_footer();
} else if ($a == 'del') {
	if (!isset($_GET['id'])) exit;
	
	$sql = "delete from group_subnets where group_id = '$_GET[id]'";
	@pg_query($sql) or die("error: failed to run query: $sql");
	
	$sql = "delete from groups where id = '$_GET[id]'";
	@pg_query($sql) or die("error: failed to run query: $sql");
	
	$_SESSION['notice_msg'] = "Group deleted.";
	header("Location: group.php");
	exit;
}
