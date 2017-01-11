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

if (!isset($_GET['a']) || !isset($_GET['addr']))
	exit;
?>
<html>
<head>
<title></title>
<style type="text/css">
body {
	font-family: "lucida grande", tahoma, verdana, arial, sans-serif;
        font-size: 11px;
	background-color: #eee;
}
h1 {
        font-size: 13pt;
        margin-bottom: 10px;
        margin-top: 0;
}
.geekbox {
	width: 575px;
	background-color: black;
	color: #51d02f;
	border: 1px solid #c4c4c4;
	font: 11px courier;
	padding: 4px;
}
</style>
</head>
<body>
<?php
if ($_GET['a'] == 'ping')
{
?>
<h1>Ping Host</h1>
<form action="cmd.php" method="get">
<input type="hidden" name="a" value="ping" />
<b>Host:</b> <input type="text" size="25" value="<?php echo ifsetor($_GET['addr']); ?>" name="addr" /> <b>Count:</b> <input type="text" name="count" size="3" maxlength="3" value="<?php echo ifsetor($_GET['count'], 5); ?>" /> <input type="submit" value="Run" />
</form>
<?php
	if (isset($_GET['addr']))
	{
		$count = isset($_GET['count']) ? $_GET['count'] : 5;
		echo '<div class="geekbox"><pre>';
		system(sprintf(ip_version($_GET['addr']) == 4 ? $config['ping_cmd'] : $config['ping6_cmd'], $count, escapeshellarg($_GET['addr'])));
		echo '</pre></div>';
	}
}
else if ($_GET['a'] == 'tracert')
{
?>
<h1>Traceroute to Host</h1>
<form action="cmd.php" method="get">
<input type="hidden" name="a" value="tracert" />
<b>Host:</b> <input type="text" size="25" value="<?php echo ifsetor($_GET['addr']); ?>" name="addr" /> <input type="submit" value="Run" />
</form>
<?php
	if (isset($_GET['addr']))
	{
		echo '<div class="geekbox"><pre>';
		system(sprintf(ip_version($_GET['addr']) == 4 ? $config['traceroute_cmd'] : $config['traceroute6_cmd'], escapeshellarg($_GET['addr'])));
		echo '</pre></div>';
	}
}
else if ($_GET['a'] == 'whois')
{
?>
<h1>Whois Lookup</h1>
<form action="cmd.php" method="get">
<input type="hidden" name="a" value="whois" />
<b>Address:</b> <input type="text" size="30" value="<?php echo ifsetor($_GET['addr']); ?>" name="addr" /> <input type="submit" value="Lookup" />
</form>
<?php
	if (isset($_GET['addr']))
	{
		$output = whois($_GET['addr']);
		echo '<div class="geekbox">';
		foreach ($output as $line)
			echo "$line<br />";
		echo '</div>';
	}
}

?>
<br />
<a href="javascript:window.close();">Close window</a>

</body>
</html>
