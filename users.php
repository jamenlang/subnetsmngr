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

$action = isset($_GET['a']) ? $_GET['a'] : 'main';
if ($action == 'main') {
	$sql = "select users.id, users.lastip, users.last_loc_time, users.name, users.username, users.last_lat, users.last_lng, instances.name as def_instance, to_char(lastlogin, 'Mon DD, YYYY HH12:MIam') as lastlogin from users left join instances on instances.id = users.default_instance_id order by users.username asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	
	$sidebar = '<aside class="rightbar"><div class="header">options</div><ul><li><a href="users.php?a=add">Add User</a></li></ul></aside>';
	layout_header('Manage Users', null, $sidebar);
	
	echo '<h1>Manage Users</h1>';
	echo '<table>';
	echo '<tr>';
	echo '<td><b>Username</b></td>';
	echo '<td><b>Name</b></td>';
	echo '<td class="un"><b>Default Instance</b></td>';
	echo '<td class="un"><b>Last Login</b></td>';
	echo '<td class="un"><b>Last IP</b></td>';
	echo '<td class="un"><b>Last Location</b></td>';
	echo '<td><b>Action</b></td>';
	echo '</tr>';
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		echo '<tr>';
		echo '<td>' . $row['username'] . ' - <a href="search.php?q=' . $row['username'] . '&history">Search History</a></td>';
		echo '<td>' . $row['name'] . '</td>';
		echo '<td class="un">' . $row['def_instance'] . '</td>';
		echo '<td class="un" align="center">' . ($row['lastlogin'] ? ago(strtotime($row['lastlogin'])) . ' ago' : 'Never') . '</td>';
		echo '<td class="un" align="center">' . ($row['lastip'] ? $row['lastip'] : 'N/A') . '</td>';
		echo '<td class="un" align="center">' . ($row['last_lat'] ? '<a target="_blank" href="http://maps.google.com/maps?z=12&t=m&q=loc:' . $row['last_lat'] . ',' . $row['last_lng'] . '">' . sprintf("%.6f", $row['last_lat']) . ',' . sprintf("%.6f", $row['last_lng']) . '</a>' : 'N/A');
		if ($row['last_loc_time']) {
			echo ', ' . ago(strtotime($row['last_loc_time'])) . ' ago ';
		}
		echo '</td>';
		echo '<td align="center"><a href="users.php?a=mod&id=' . $row['id'] . '">Modify</a> / <a onclick="if (!confirm(\'Are you sure you want to delete this user?\')) { return false; }" href="users.php?a=del&id=' . $row['id'] . '">Delete</a></td>';
		echo '</tr>';
	}
	echo '</table>';
	
	layout_footer();
} else if ($action == 'add') {
	if ($_POST) {
		if (!($_POST['name'] && $_POST['username'] && $_POST['password'] && $_POST['password2']))
			$_SESSION['error_msg'] = 'All fields are required.';
		else if (@pg_num_rows(@pg_query("select id from users where username = '$_POST[username]'")) == 1)
			$_SESSION['error_msg'] = 'That username is already taken.';
		else if ($_POST['password'] != $_POST['password2'])
			$_SESSION['error_msg'] = 'Passwords do not match.';
		else {
			$sql = "insert into users (name, username, password, default_instance_id) values('$_POST[name]', '$_POST[username]', md5('$_POST[password]'), '{$_POST['instance_id']}')";
			@pg_query($sql) or die("error: failed to run query: $sql");
			$_SESSION['notice_msg'] = 'User added.';
			header("Location: ?a=main");
			exit;
		}
	}
	
	layout_header('Add User');

	echo '<h1>Add User</h1>';
	echo '<form action="?a=add" method="post" class="general">';
	echo '<ul><li>';
	echo '<label for="name" class="label">Name:</label> <input id="name" type="text" name="name" value="' . ifsetor($_POST['name']) . '" size="30" /></li>';
	echo '<li><label for="username" class="label">Username:</label> <input type="text" name="username" value="' . ifsetor($_POST['username']) . '" id="username" size="20" /></li>';
	echo '<li><label for="password" class="label">Password:</label> <input id="password" type="password" name="password" value="" size="30" /></li>';
	echo '<li><label for="password2" class="label">Confirm Password:</label> <input type="password" id="password2" name="password2" value="" size="30" /></li>';
	echo '<li><label for="instance_id" class="label">Default Instance:</label> <select id="instance_id" name="instance_id">';
	foreach (instances_get() as $i) {
		echo '<option value="' . $i['id'] . '"';
		if (ifsetor($_POST['instance_id'], $_SESSION['user']['instance_id']) == $i['id']) {
			echo ' selected="selected"';
		}
		echo '>' . $i['name'] . '</option>';
	}
	echo '</select></li>';
	echo '<li><div class="label">&nbsp;</div>';
	echo '<input type="submit" value="  Add User  " /></li>';
	echo '</ul>';
	echo '</form>';
	
	layout_footer();
} else if ($action == 'mod') {
	if ($_POST) {
		if (!$_POST['name'])
			$_SESSION['error_msg'] = 'Enter a name.';
		else if ($_POST['password'] && ($_POST['password'] != $_POST['password2']))
			$_SESSION['error_msg'] = 'Passwords do not match.';
		else {
			$sql = "update users set name = '$_POST[name]', default_instance_id = '{$_POST['instance_id']}'";
			if ($_POST['password'])
				$sql .= ", password = md5('$_POST[password]')";
			$sql .= " where id = '$_GET[id]'";
			@pg_query($sql) or die("error: failed to run query: $sql");
			$_SESSION['notice_msg'] = 'User modified.';
			header("Location: ?a=main");
			exit;
		}
	}
	
	$sql = "select id, name, default_instance_id, username from users where id = '$_GET[id]'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$row = pg_fetch_array($result, null, PGSQL_ASSOC);
	
	layout_header('Modify User');

	echo '<h1>Modify User</h1>';
	echo '<form action="?a=mod&id=' . $_GET['id'] . '" method="post" class="general">';
	echo '<ul>';
	echo '<li>';
	echo '<label class="label" for="name">Name:</label> <input type="text" id="name" name="name" value="' . ifsetor($_POST['name'], $row['name']) . '" size="30" /></li>';
	echo '<li><label class="label" for="username">Username:</label> <i>' . $row['username'] . '</i></li>';
	echo '<li><label class="label" for="password">Change Password:</label> <input id="password" type="password" name="password" value="" size="30" /></li>';
	echo '<li><label class="label" for="password2">Confirm Password:</label> <input id="password2" type="password" name="password2" value="" size="30" /></li>';
	echo '<li><label class="label" for="instance_id">Default Instance:</label> <select id="instance_id" name="instance_id">';
	foreach (instances_get() as $i) {
		echo '<option value="' . $i['id'] . '"';
		if (ifsetor($_POST['instance_id'], $row['default_instance_id']) == $i['id']) {
			echo ' selected="selected"';
		}
		echo '>' . $i['name'] . '</option>';
	}
	echo '</select></li>';
	echo '<li><div class="label">&nbsp;</div>';
	echo '<input type="submit" value="  Modify User  " /></li>';
	echo '</ul>';
	echo '</form>';
	
	layout_footer();
} else if ($action == 'del') {
	if (!isset($_GET['id'])) exit;
	
	$sql = "delete from users where id = '{$_GET['id']}'";
	@pg_query($sql) or die("error: failed to run query: $sql");
	
	$_SESSION['notice_msg'] = 'User deleted.';
	header("Location: ?a=main");
}
