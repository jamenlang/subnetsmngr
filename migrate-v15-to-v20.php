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

die("remove me AFTER MAKING A BACKUP OF YOUR DATABASE");

$sql = "alter table subnets add column noautoalloc smallint default 0";
@pg_query($sql) or die("failed to run query: $sql");

$sql = "alter table subnets add column parent_id smallint default 0 not null";
@pg_query($sql) or die("failed to run query: $sql");

// find base_subnets and add them as normal subnets, and remember their ID
$sql = "select * from base_subnets";
$result = @pg_query($sql) or die("failed to run query: $sql");
while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
	$sql = "insert into subnets (parent_id, base_id, addr, description, notes, last_updated_user_id, last_updated, noautoalloc, free) values(" .
			"'0', '$row[id]', '$row[addr]', '" . addslashes($row['description']) . "', '', '1', now(), '$row[reserved]', '0')";
	
	@pg_query($sql) or die("failed to run query: $sql");
	
	$sql = "select currval('subnets_id_seq') as subnet_id";
	$result2 = @pg_query($sql) or die("error: failed to run query: $sql");
	$subnet_id = pg_fetch_result($result2, 0, 'subnet_id');
	
	$sql = "update subnets set parent_id = '$subnet_id' where base_id = '$row[id]' and id != '$subnet_id'";
	@pg_query($sql) or die("failed to run query: $sql");
}

$sql = "alter table subnets drop column base_id";
@pg_query($sql) or die("failed to run query: $sql");

$sql = "drop table base_subnets";
@pg_query($sql) or die("failed to run query: $sql");

echo "done.";
