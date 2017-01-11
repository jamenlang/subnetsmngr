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

if (isset($_SESSION['user'])) header("Location: index.php");

if ($_POST)
{
	$sql = "select id, default_instance_id from users where username = '{$_POST['username']}' and password = MD5('{$_POST['password']}')";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	if (pg_num_rows($result) == 0)
		$error_msg = "Login failed.";
	else
	{
		$row = pg_fetch_array($result, null, PGSQL_ASSOC);
		$_SESSION['user'] = array(
			'id' => $row['id'],
			'username' => $_POST['username'],
			'instance_id' => $row['default_instance_id']
		);
		
		@pg_query("update users set lastlogin = now() where id = '{$row['id']}'");
		
		$goto = $_POST['ref'] ? $_POST['ref'] : "index.php";
		header("Location: $goto");
	}
}

layout_header('Login');
?>
<div id="loginbox">
  <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="login">
    <input type="hidden" name="ref" value="<?php ifsetor($_GET['ref']); ?>" />
<?php if (isset($error_msg)): ?>
    <div class="error"><?php echo $error_msg; ?></div>
<?php endif; ?>
    <label for="username">Username:</label>
    <input type="text" id="username" name="username" value="<?php echo ifsetor($_REQUEST['username']); ?>" size="30" />
    <label for="password">Password:</label>
    <input type="password" id="password" name="password" value="" size="30" />
    <button type="submit" class="btn-login">Login</button>
  </form>
</div>
<?php
layout_footer();
