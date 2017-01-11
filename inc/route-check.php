<?php
function rc_get_route_info($instance_id, $route, $orlonger = false)
{
	$dcfg = rc_get_device_config($instance_id);
	$d = new Device($dcfg['host'], $dcfg['user'], $dcfg['pass']);
	$d->connect();
	
	$table = (strstr($route, ':') ? 'inet6.0' : 'inet.0');
	try {
		$query_xml = "<rpc><get-route-information><table>$table</table>$route<active-path/>";
		if (!$orlonger) {
			$query_xml .= "<exact/>";
		}
		$query_xml .= "</get-route-information></rpc>]]>]]>\n";
		$reply = $d->execute_rpc($query_xml);
		$reply_xml = simplexml_load_string($reply->to_string());
//		print_r($reply_xml);
		$rt_entry = null;
		if (!$orlonger) {
			$rt_dest = $reply_xml->{'route-information'}->{'route-table'}->{'rt'}->{'rt-destination'};
			if (is_array($reply_xml->{'route-information'}->{'route-table'}->{'rt'}->{'rt-entry'})) {
				foreach ($reply_xml{'route-information'}->{'route-table'}->{'rt'}->{'rt-entry'} as $e) {
					if ($e->{'active-tag'} == '*') {
						$rt_entry = $e;
						break;
					}
				}
			} else {
				$rt_entry = $reply_xml->{'route-information'}->{'route-table'}->{'rt'}->{'rt-entry'};
			}
			return [
				'destination' => $rt_dest,
				'protocol' => $rt_entry->{'protocol-name'},
				'age' => $rt_entry->{'age'},
				'router' => $dcfg['host']
			];
		} else {
			$routes = [];
			
			foreach ($reply_xml->{'route-information'}->{'route-table'}->{'rt'} as $rt) {
				$rt_entry = $rt->{'rt-entry'};
				$routes[] = [
					'destination' => $rt->{'rt-destination'},
					'protocol' => $rt_entry->{'protocol-name'},
					'age' => $rt_entry->{'age'},
					'router' => $dcfg['host']
				];
			}
			/*
			} else {
				$rt_entry = $reply_xml->{'route-information'}->{'route-table'}->{'rt'}->{'rt-entry'};
				$routes[] = [
					'destination' => $reply_xml->{'route-information'}->{'route-table'}->{'rt'}->{'rt-destination'},
					'protocol' => $rt_entry->{'protocol-name'},
					'age' => $rt_entry->{'age'},
					'router' => $dcfg['host']
				];
			}
			*/
			return $routes;
					
		}
		return false;
	} catch(Exception $e) {
		return false;
	}
}

function rc_get_device_config($instance_id)
{
	global $config;
	
	if (isset($config['route_check']) && $config['route_check']['enabled']) {
		foreach ($config['route_check'] as $e) {
			if (is_array($e) && isset($e['instance_id']) && $e['instance_id'] == $instance_id) {
				return $e;
			}
		}
	}
	return null;
}


