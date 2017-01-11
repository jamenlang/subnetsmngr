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
require('inc/country_codes.php');

$action = isset($_GET['a']) ? $_GET['a'] : 'main';
if ($action == 'main')
{
	$sql = "select * from customers order by name asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	
	$sidebar = '<aside class="rightbar"><div class="header">options</div><ul><li><a href="customers.php?a=add">Add Customer</a></li><li><a href="customers.php?a=addbywhois">Add Customers via Whois</a></li></ul></aside>';
	layout_header('Manage Customers', '', $sidebar);
	
	echo '<h1>Manage Customers</h1>';
	
	if (pg_num_rows($result) == 0)
		echo 'There are no customers added yet.';
	else
	{
		echo '<table>';
		echo '<tr>';
		echo '<th>Name</th>';
		echo '<th>Address</th>';
		echo '<th>City</th>';
		echo '<th>State</th>';
		echo '<th>Zip</th>';
		echo '<th>Country</th>';
		echo '<th><b>Action</th>';
		echo '</tr>';
		
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC))
		{
			echo '<tr>';
			echo '<td>' . $row['name'] . '</td>';
			echo '<td>' . $row['addr1'];
			if ($row['addr2']) echo ", $row[addr2]";
			echo '</td>';
			echo '<td>' . $row['city'] . '</td>';
			echo '<td>' . $row['state'] . '</td>';
			echo '<td>' . $row['zip'] . '</td>';
			echo '<td>' . $row['country'] . '</td>';
			echo '<td align="center"><a href="?a=mod&amp;id=' . $row['id'] . '">Modify</a> / <a onclick="if (!confirm(\'Are you sure you want to delete this customer?\')) { return false; }" href="?a=del&amp;id=' . $row['id'] . '">Delete</a></td>';
			echo '</tr>';
		}
		
		echo '</table>';
	}
	layout_footer();
} else if ($action == 'add') {
	if ($_POST) {
		if (!$_POST['name'] || !$_POST['addr1'] || !$_POST['city'] || !$_POST['state'] || !$_POST['zip'])
			$_SESSION['error_msg'] = 'All fields are required.';
		else {
			$sql = "insert into customers (name, addr1, addr2, city, state, zip, country) values('$_POST[name]', '$_POST[addr1]', '$_POST[addr2]', '$_POST[city]', '$_POST[state]', '$_POST[zip]', '$_POST[country]')";
			@pg_query($sql) or die("error: failed to run query: $sql");
			$_SESSION['notice_msg'] = 'Customer added.';
			header("Location: customers.php");
			exit;
		}
	}

	layout_header('Add Customer');
	
	echo '<h1>Add Customer</h1>';
	echo '<form action="?a=add" method="post" class="general">';
	echo '<ul>';
	echo '<li><label class="label" for="name">Name:</label> <input type="text" id="name" name="name" value="' . ifsetor($_POST['name']) . '" size="30" /></li>';
	echo '<li><label class="label" for="addr1">Address:</label> <input type="text" id="addr1" name="addr1" value="' . ifsetor($_POST['addr1']) . '" size="30" /></li>';
	echo '<li><label class="label" for="addr2">Address cont.:</label> <input type="text" id="addr2" name="addr2" value="' . ifsetor($_POST['addr2']) . '" size="30" /></li>';
	echo '<li><label class="label" for="city">City:</label> <input type="text" id="city" name="city" value="' . ifsetor($_POST['city']) . '" size="20" /></li>';
	echo '<li><label class="label" for="state">State:</label> <input type="text" id="state" name="state" value="' . ifsetor($_POST['state']) . '" maxlength="2" size="2" /></li>';
	echo '<li><label class="label" for="zip">Zip:</label> <input type="text" id="zip" name="zip" value="' . ifsetor($_POST['zip']) . '" size="10" maxlength="10" /></li>';
	echo '<li><label class="label" for="country">Country:</label> <select id="country" name="country">';
	foreach ($country_codes as $code => $country)
	{
		echo '<option value="' . $code . '"';
		if (ifsetor($_POST['country'], 'US') == $code)
			echo ' selected="selected"';
		echo '>' . $country . '</option>';
	}
	echo '</select></li>';
	echo '<li><div class="label">&nbsp;</div> <input type="submit" value="  Add Customer  " />';
	echo '</ul>';
	echo '</form>';
	
	layout_footer();
} else if ($action == 'mod') {
	if ($_POST) {
		if (!$_POST['name'] || !$_POST['addr1'] || !$_POST['city'] || !$_POST['state'] || !$_POST['zip'])
			$_SESSION['error_msg'] = 'All fields are required.';
		else {
			$sql = "update customers set name = '$_POST[name]', addr1 = '$_POST[addr1]', addr2 = '$_POST[addr2]', city = '$_POST[city]', state = '$_POST[state]', zip = '$_POST[zip]', country = '$_POST[country]' where id = '$_GET[id]'";
			@pg_query($sql) or die("error: failed to run query: $sql");
			
			// if customer actually changed, find subnets belonging to this customer and send SWIP updates
			if (pg_affected_rows() == 1) {
				$failed_swips = array();
				
				$sql = "select addr from subnets where customer_id = '$_GET[id]' and swiped = '1'";
				$result = @pg_query($sql) or die("error: failed to run query: $sql");
				while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
					// get current swip record so we can send the correct netname and originas
					$swip = get_swip($row['addr']);
					if (isset($swip['netname']) && isset($swip['originas'])) {
						if (!send_swip($row['addr'], 'M', $_GET['id'], $swip['netname'], null, null, $swip['originas']))
							$failed_swips[] = $subnet;
					} else
						$failed_swips[] = $subnet;
				}
				
				if ($failed_swips)
					$_SESSION['error_msg'] = 'Update SWIPs were not sent for the following subnets:<ul>' . implode('<li>', $failed_swips) . '</ul>';
			}
			$_SESSION['notice_msg'] = 'Customer modified.';
			header("Location: customers.php");
			exit;
		}
	}
	
	$sql = "select * from customers where id = '$_GET[id]'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$row = pg_fetch_array($result, null, PGSQL_ASSOC);
	
	layout_header('Modify Customer');
	
	echo '<h1>Modify Customer</h1>';
	echo '<form action="?a=mod&id=' . $_GET['id'] . '" method="post" class="general">';
	echo '<ul>';
	echo '<li>';
	echo '<label for="name" class="label">Name:</label> <input type="text" name="name" id="name" value="' . ifsetor($_POST['name'], $row['name']) . '" size="30" /></li>';
	echo '<li><label class="label" for="addr1">Address:</label> <input type="text" name="addr1" id="addr1" value="' . ifsetor($_POST['addr1'], $row['addr1']) . '" size="30" /></li>';
	echo '<li><label class="label" for="addr2">Address cont.:</label> <input type="text" name="addr2" id="addr2" value="' . ifsetor($_POST['addr2'], $row['addr2']) . '" size="30" /></li>';
	echo '<li><label class="label" for="city">City:</label> <input type="text" name="city" id="city" value="' . ifsetor($_POST['city'], $row['city']) . '" size="20" /></li>';
	echo '<li><label class="label" for="state">State:</label> <input type="text" id="state" name="state" value="' . ifsetor($_POST['state'], $row['state']) . '" maxlength="2" size="2" /></li>';
	echo '<li><label class="label" for="zip">Zip:</label> <input type="text" id="zip" name="zip" value="' . ifsetor($_POST['zip'], $row['zip']) . '" size="10" maxlength="10" /></li>';
	echo '<li><label class="label" for="country">Country:</label> <select name="country" id="country">';
	foreach ($country_codes as $code => $country) {
		echo '<option value="' . $code . '"';
		if (ifsetor($_POST['country'], $row['country']) == $code)
			echo ' selected="selected"';
		echo '>' . $country . '</option>';
	}
	echo '</select></li>';
	echo '<li><div class="label">&nbsp;</div>';
	echo '<input type="submit" value="  Modify Customer  " /></li>';
	echo '</ul>';
	echo '<p><b>Note:</b> When modifying a customer, an update SWIP record will be sent for all SWIPed subnets belonging to this customer.</p>';
	echo '</form>';
} else if ($action == 'del') {
	if (!isset($_GET['id'])) exit;
	
	// send remove SWIPs for every subnet that belonged to this customer and was SWIPed
	$failed_swips = array();
	$sql = "select addr from subnets where customer_id = '$_GET[id]' and swiped = '1'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		// get current swip record so we can send the correct netname and originas
		$swip = get_swip($row['addr']);
		if (isset($swip['netname']) && isset($swip['originas'])) {
			if (!send_swip($row['addr'], 'R', $_GET['id'], $swip['netname'], null, null, $swip['originas']))
				$failed_swips[] = $subnet;
		}
		else
			$failed_swips[] = $subnet;
	}
	if ($failed_swips)
		$_SESSION['error_msg'] = 'Removal SWIPs were not sent for the following subnets:<ul>' . implode('<li>', $failed_swips) . '</ul>';
	
	$sql = "update hosts set customer_id = NULL where customer_id = '$_GET[id]'";
	@pg_query($sql) or die("error: failed to run query: $sql");
	
	$sql = "update subnets set customer_id = NULL where customer_id = '$_GET[id]'";
	@pg_query($sql) or die("error: failed to run query: $sql");
	
	$sql = "delete from customers where id = '$_GET[id]'";
	@pg_query($sql) or die("error: failed to run query: $sql");
	
	$_SESSION['notice_msg'] = 'Customer deleted.';
	header("Location: customers.php");
	exit;
} else if ($action == 'addbywhois') {
	if (isset($_SESSION['addbywhois_customers']) && $_SESSION['addbywhois_customers']) {
		if (isset($_POST['addcusts'])) {
			foreach ($_POST['add'] as $key) {
				$customer = $_SESSION['addbywhois_customers'][$key];
				foreach ($customer as $k => $v) $customer[$k] = addslashes($v);
				
				$sql = "select id from customers where name = '$customer[name]' and addr1 = '$customer[addr1]' and addr2 = '" . (isset($customer['addr2']) ? $customer['addr2'] : '') . "' and zip = '$customer[zip]'";
				$result = @pg_query($sql) or die("error: failed to run query: $sql");
				if (pg_num_rows($result) == 0) {
					$sql = "insert into customers (name, addr1, addr2, city, state, zip, country) values('$customer[name]', '$customer[addr1]', '" . (isset($customer['addr2']) ? $customer['addr2'] : '') . "', '$customer[city]', '$customer[state]', '$customer[zip]', '$customer[country]')";
					@pg_query($sql) or die("error: failed to run query: $sql");

					$sql = "select currval('customers_id_seq') as id";
					$result = @pg_query($sql) or die("error: failed to run query: $sql");
					$id = pg_fetch_result($result, 0, 'id');
				}
				else
					$id = pg_fetch_result($result, 0, 'id');
				list($addr,$mask) = explode('/', $customer[CIDR]);
				$sql = "update subnets set customer_id = '$id', lastswiped = '$customer[lastswiped]', swiped = '1' where addr = '" . inet_aton($addr) . "' and mask = '" . inet_cidrton($mask) . "'";
				@pg_query($sql) or die("error: failed to run query: $sql");
			}
			unset($_SESSION['addbywhois_customers']);
			$_SESSION['notice_msg'] = 'Customers added.';
			header("Location: customers.php");
			exit;
		}
		
		layout_header('Add Customers via Whois');
		
		echo '<div class="main-header">';
		echo '<h1>Add Customers via Whois</h1>';
		echo '</div>';
		echo '<div class="main-body">';
		echo '<p>Below are the results from the Whois lookups.  Choose which should be added.  Duplicate customers will be added only once.</p>';
		echo '<form action="?a=addbywhois" method="post">';
		echo '<input type="submit" value="  Add Checked  " name="addcusts" /><br /><br />';
		echo '<table width="100%" cellspacing="1" cellpadding="4" class="datatbl">';
		echo '<tr class="datatbl-hdr">';
		echo '<td align="center" width="30"><b>Add</b></td>';
		echo '<td align="center" width="120"><b>Subnet</b></td>';
		echo '<td>Name</td>';
		echo '<td align="center"><b>Address</b></td>';
		echo '<td align="center"><b>City</b></td>';
		echo '<td align="center" width="30"><b>State</b></td>';
		echo '<td align="center" width="50"><b>Zip</b></td>';
		echo '<td align="center" width="50"><b>Country</b></td>';
		echo '</tr>';
		for ($css_class = 'datatbl-color1'; list($k,$customer) = each($_SESSION['addbywhois_customers']); $css_class = $css_class == 'datatbl-color1' ? 'datatbl-color2' : 'datatbl-color1') {
			echo '<tr class="' . $css_class . '">';
			echo '<td align="center"><input type="checkbox" name="add[]" value="' . $k . '" checked="checked" /></td>';
			echo '<td>' . $customer['cidr'] . '</td>';
			echo '<td>' . $customer['name'] . '</td>';
			echo '<td>' . $customer['addr1']; if (isset($customer['addr2'])) echo ", $customer[addr2]";
			echo '</td>';
			echo '<td>' . $customer['city'] . '</td>';
			echo '<td width="30">' . $customer['state'] . '</td>';
			echo '<td width="50">' . $customer['zip'] . '</td>';
			echo '<td width="50">' . $customer['country'] . '</td>';
			echo '</tr>';
		}
		echo '</table><br />';
		echo '<input type="submit" value="  Add Checked  " name="addcusts" />';
		echo '</form>';
		echo '</div>';
		
		layout_footer();
	} else {
		if (isset($_POST['run'])) {
			if (!$_POST['subnets'])
				$_SESSION['error_msg'] = 'Choose at least one root subnet.';
			else {
				$customers = array();
				foreach ($_POST['subnets'] as $base_id) {
					$sql = "select id, addr from subnets where parent_id = '$base_id' and free = '0' and ((family(addr) = 4 and masklen(addr) <= 29) or (family(addr) = 6 and masklen(addr) <= 64)) order by addr asc";
					$result = @pg_query($sql) or die("error: failed to run query: $sql");
					while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
						$customer = get_swip($row['addr']);
						if (isset($customer['cidr']) && $customer['cidr'] == $row['addr'])
							$customers[] = $customer;
					}
				}
				
				$_SESSION['addbywhois_customers'] = $customers;
				header("Location: customers.php?a=addbywhois");
				exit;
			}
		}
		
		$sql = "select id, addr from subnets where parent_id = '0' order by addr asc";
		$result = @pg_query($sql) or die("error: failed to run query: $sql");
		$subnets = array();
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			$subnets[] = $row;
		}
		
		layout_header('Add Customers via Whois');
		
		echo '<div class="main-header">';
		echo '<h1>Add Customers via Whois</h1>';
		echo '</div>';
		echo '<div class="main-body">';
		echo '<p>Use this form if you\'d like to run Whois lookups on your subnets and automatically add customers based on the contact information returned.  Choose the base subnet(s) to run queries on.  It will run individual lookups on each subnet larger than a /30 within the base subnet.</p>';
		echo '<form action="?a=addbywhois" method="post">';
		echo '<table border="0" cellspacing="0" cellpadding="0" class="formtbl" width="100%">';
		echo '<tr><td valign="top" width="180"><b>Subnets:</b></td><td><select name="subnets[]" size="8" multiple="multiple">';
		foreach ($subnets as $subnet)
		{
			echo '<option value="' . $subnet['id'] . '"';
			if (in_array($subnet['id'], ifsetor($_POST['subnets'], array())))
				echo ' selected="selected"';
			echo '>' . $subnet['addr'] . '</option>';
		}
		echo '</select></td></tr>';
		echo '</table><br />';
		echo '<input type="submit" value="  Run  " name="run" />';
		echo '</form></div>';
		
		layout_footer();
	}
}
?>
