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

layout_header('Calculator');

echo '<div class="main-header">';
echo '<h1>Calculator</h1>';
echo '</div>';
echo '<div class="main-body">';
echo '<form action="' . $_SERVER['PHP_SELF'] . '" method="post">';
echo '<b>Address:</b> <input type="text" name="ip" size="25" value="' . ifsetor($_POST['ip']) . '" /> ' . subnet_mask_dropdown('mask', 4) . ' <input type="submit" value="Calculate" />';
echo '</form>';

if (isset($_POST['ip']) && $_POST['ip'])
{
	$ip = trim($_POST['ip']);
	$prefix_length = $_POST['mask'];
	$mask = long2ip(ip2long('255.255.255.255') << 32-$prefix_length);
	
	$nhosts = pow(2, 32-$prefix_length)-2;
	$network = long2ip(ip2long($ip) & ip2long($mask));
	$host_min = long2ip( (ip2long($ip) & ip2long($mask)) + 1 );
	$host_max = long2ip( (ip2long($ip) & ip2long($mask)) + $nhosts );
	$bcast = long2ip( (ip2long($ip) & ip2long($mask)) + (pow(2, 32-$prefix_length)-1) );
		
	if (substr(ip2dottedbin($network), 0, 3) == '110')
	{
		$class_ho_bit_length = 3;
		$class = 'C';
	}
	else if (substr(ip2dottedbin($network), 0, 2) == '10')
	{
		$class_ho_bit_length = 2;
		$class = 'B';
	}
	else if (substr(ip2dottedbin($network),  0, 1) == '0')
	{
		$class = 'A';
		$class_ho_bit_length = 1;
	}
	
	echo '<br />';
	echo '<table border="0" cellspacing="0" cellpadding="1">';
	echo '<tr>';
	echo '<td width="120"><b>Address:</b></td><td width="140" style="color:#42c3ff" style="color:#42c3ff">' . $ip . '</td><td style="color:#9da01e">' . ip2dottedbin($ip) . '</td></tr>';
	echo '<td width="120"><b>Netmask:</b></td><td width="140" style="color:#42c3ff">' . $mask . ' = ' . $prefix_length . '</td><td style="color:#b90832">' . ip2dottedbin($mask) . '</td></tr>';
	echo '<td width="120"><b>Wilcard:</b></td><td width="140" style="color:#42c3ff">' . wildcard_mask($mask) . '</td><td style="color:#9da01e">' . ip2dottedbin(wildcard_mask($mask)) . '</td></tr>';
	echo '<td width="120"><b>Network:</b></td><td width="140" style="color:#42c3ff">' . $network . '/' . $prefix_length . '</td><td><span style="color:#c850d6">' . substr(ip2dottedbin($network), 0, $class_ho_bit_length) . '</span><span style="color:#9da01e">' . substr(ip2dottedbin($network), $class_ho_bit_length) . '</span></td></tr>';
	echo '<td width="120"><b>Host Min:</b></td><td width="140" style="color:#42c3ff">' . $host_min . '</td><td style="color:#9da01e">' . ip2dottedbin($host_min) . '</td></tr>';
	echo '<td width="120"><b>Host Max:</b></td><td width="140" style="color:#42c3ff">' . $host_max . '</td><td style="color:#9da01e">' . ip2dottedbin($host_max) . '</td></tr>';
	echo '<td width="120"><b>Broadcast:</b></td><td width="140" style="color:#42c3ff">' . $bcast . '</td><td style="color:#9da01e">' . ip2dottedbin($bcast) . '</td></tr>';
	echo '<td width="120"><b>Hosts/Net:</b></td><td width="140" style="color:#42c3ff">' . $nhosts . '</td><td><span style="color:#c850d6">Class ' . $class . '</span></td></tr>';
	echo '</tr>';
	echo '</table>';
}

echo '</div>';

layout_footer();
