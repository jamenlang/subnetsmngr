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

function layout_header($title = '', $head_code = '', $sidebar = '')
{
	global $config, $VERSION;
?>
<!DOCTYPE html>
<html>
<head>
<title>subnetsmngr</title>
<link rel="stylesheet" media="all" href="style.css?2" />
<link rel="stylesheet" media="only screen and (max-width: 768px)" href="mobile.css" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<script type="text/javascript" src="geo.js"></script>
<script type="text/javascript">
var newwindow;
function poptastic(url)
{
	newwindow=window.open(url,'name','height=400,width=600,scrollbars=yes');
	if (window.focus) {newwindow.focus()}
}
function rowLink(ev, url)
{
	var e = ev || window.event;
	if (document.getSelection() != '') return;
	if (e.target.tagName != 'INPUT' && e.target.tagName != 'SELECT') {
		location.href=url;
	}
}

function toggleNav()
{
	var nav = document.getElementById('nav');
	if (nav.style.display == 'none') {
		nav.style.display = 'block';
	} else {
		nav.style.display = 'none';
	}
	return false;
}
	
var isMobile = {
    Android: function() {
        return navigator.userAgent.match(/Android/i);
    },
    BlackBerry: function() {
        return navigator.userAgent.match(/BlackBerry/i);
    },
    iOS: function() {
        return navigator.userAgent.match(/iPhone|iPad|iPod/i);
    },
    Opera: function() {
        return navigator.userAgent.match(/Opera Mini/i);
    },
    Windows: function() {
        return navigator.userAgent.match(/IEMobile/i) || navigator.userAgent.match(/WPDesktop/i);
    },
    any: function() {
        return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Opera() || isMobile.Windows());
    }
};
</script>
<?php echo $head_code . "\n"; ?>
</head>
<body>

<div class="wrapper">
  <header>
     <div class="header-content">
       <div class="show-menu" onclick="toggleNav();">&nbsp;</div>
       <div class="logo"><a href="index.php"><span>subnets</span>mngr</a></div>
<?php if (isset($_SESSION['user'])): ?>
       <div class="search">
          <form action="search.php" method="get">
             <input type="text" name="q" value="search..." onfocus="if (this.value == 'search...') { this.value = ''; }" onblur="if (this.value == '') { this.value = 'search...'; }" /> <input type="submit" value="go" />
<?php
		if (isset($_SESSION['recent_searches']) && $_SESSION['recent_searches']) {
			$links = array();
			foreach ($_SESSION['recent_searches'] as $rs) {
				if (strlen($rs) > 20)
					$rsname = substr($rs, 0, 20) . '...';
				else
					$rsname = $rs;
				$links[] = '<a href="search.php?q=' . $rs . '">' . $rsname . '</a>';
			}
			echo '<br /> ' . implode(', ', $links);
		}
?>
          </form>
       </div>
<?php endif; ?>
     </div>
  </header>
<?php if (isset($_SESSION['error_msg']) && $_SESSION['error_msg']): ?>
  <div class="alertmsg-error"><b>ERROR:</b> <?php echo $_SESSION['error_msg']; ?></div>
<?php $_SESSION['error_msg'] = ''; endif; ?>
<?php if (isset($_SESSION['plat_msg']) && $_SESSION['plat_msg']): ?>
  <div class="alertmsg-plat"><b>PLATYPUS:</b> <?php echo $_SESSION['plat_msg']; ?></div>
<?php $_SESSION['plat_msg'] = ''; endif; ?>
<?php if (isset($_SESSION['mnpp_msg']) && $_SESSION['mnpp_msg']): ?>
  <div class="alertmsg-mnpp"><b>MNPP:</b> <?php echo $_SESSION['mnpp_msg']; ?></div>
<?php $_SESSION['mnpp_msg'] = ''; endif; ?>
<?php if (isset($_SESSION['notice_msg']) && $_SESSION['notice_msg']): ?>
  <div class="alertmsg-notice"><b>NOTICE:</b> <?php echo $_SESSION['notice_msg']; ?></div>
<?php $_SESSION['notice_msg'] = ''; endif; ?>
  <div class="main">
<?php if (isset($_SESSION['user'])): ?>
     <aside class="leftbar" id="nav">
        <div class="header">main menu</div>
        <ul>
<?php foreach ([
		'subnets' => [$config['web_path'] . '/index.php',$config['web_path'] . '/hosts.php',$config['web_path'] . '/host.php'],
		'allocate' => [$config['web_path'] . '/allocate.php'],
		'calc' => [$config['web_path'] . '/calc.php'],
		'groups' => [$config['web_path'] . '/group.php'],
		'customers' => [$config['web_path'] . '/customers.php'],
		'users' => [$config['web_path'] . '/users.php'],
		'logout (' . $_SESSION['user']['username'] . ')' => [$config['web_path'] . '/logout.php']
	] as $title => $links):
?>
           <li<?php if (in_array($_SERVER['PHP_SELF'], $links)) { echo ' class="active"'; } ?>><a href="<?php echo $links[0]; ?>"><?php echo $title; ?></a></li>
<?php endforeach; ?>
        </ul>
        <div class="header">instance selection</div>
        <ul>
<?php foreach (instances_get() as $i): ?>
          <li<?php if ($_SESSION['user']['instance_id'] == $i['id']) { echo ' class="active"'; } ?>><a href="index.php?instance_id=<?php echo $i['id']; ?>"><?php echo strtolower($i['name']); ?></a></li>
<?php endforeach; ?>
        </ul>
     </aside>
<?php endif; ?>
<?php if (!empty($sidebar)): ?>
     <?php echo $sidebar . "\n"; ?>
<?php endif; ?>
     <div class="content">
<?php
}

function layout_footer()
{
?>
     </div>
  </div>
  <footer>
     <div class="copyright">&copy; <?php echo date('Y'); ?> Copyright Visionary Communications, Inc. All Rights Reserved.</div>
  </footer>
</div>

</body>
</html>
<?php
}

function subnet_tree_breadcrumb($subnet_tree, $cur_subnet_id = null)
{
	if ($cur_subnet_id == null && isset($_REQUEST['subnet_id'])) {
		$cur_subnet_id = $_REQUEST['subnet_id'];
	}
	$breadcrumb = '<span class="subnet-crumb"><a href="index.php">Subnets</a>';
	if (count($subnet_tree) > 0) {
		$breadcrumb .= ' <span>//</span> ';
	}
	for ($i	= count($subnet_tree)-1; $i >= 0; $i--) {
		if ($cur_subnet_id == $subnet_tree[$i]['id']) {
			$breadcrumb .= '<span class="current">' . $subnet_tree[$i]['addr'] . '</span>';
		} else {
			$breadcrumb .= '<a href="index.php?subnet_id=' . $subnet_tree[$i]['id'] . '">' . $subnet_tree[$i]['addr'] . '</a> <span>//</span> ';
		}
	}
	$breadcrumb .= '</span>';
	return $breadcrumb;
}
