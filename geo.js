function distance(lat1, lon1, lat2, lon2, unit)
{
	var radlat1 = Math.PI * lat1/180
	var radlat2 = Math.PI * lat2/180
	var theta = lon1-lon2
	var radtheta = Math.PI * theta/180
	var dist = Math.sin(radlat1) * Math.sin(radlat2) + Math.cos(radlat1) * Math.cos(radlat2) * Math.cos(radtheta);
	dist = Math.acos(dist)
	dist = dist * 180/Math.PI
	dist = dist * 60 * 1.1515
	if (unit=="K") { dist = dist * 1.609344 }
	if (unit=="N") { dist = dist * 0.8684 }
	return dist
}

function angleFromCoords(lat1, long1, lat2, long2)
{
	lat1 = deg2rad(lat1);
	long1 = deg2rad(long1);
	lat2 = deg2rad(lat2);
	long2 = deg2rad(long2);

	var dLon = (long2 - long1);
	var y = Math.sin(dLon) * Math.cos(lat2);
	var x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLon);
	var brng = Math.atan2(y, x);

	brng = brng * (180/Math.PI);
	brng = (brng + 360) % 360;
//	brng = 360 - brng;
	
	return brng;
}

function deg2rad(deg)
{
	return deg * Math.PI/180;
}

// given "0-360" returns the nearest cardinal direction "N/NE/E/SE/S/SW/W/NW/N" 
function getCardinal(angle)
{
	// easy to customize by changing the number of directions you have 
        var directions = 8;
        
	var degree = 360 / directions;
	angle = angle + degree/2;
	
        if (angle >= 0 * degree && angle < 1 * degree)
            return "North";
        if (angle >= 1 * degree && angle < 2 * degree)
            return "North East";
        if (angle >= 2 * degree && angle < 3 * degree)
            return "East";
        if (angle >= 3 * degree && angle < 4 * degree)
            return "Sotuh East";
        if (angle >= 4 * degree && angle < 5 * degree)
            return "South";
        if (angle >= 5 * degree && angle < 6 * degree)
            return "South West";
        if (angle >= 6 * degree && angle < 7 * degree)
            return "West";
        if (angle >= 7 * degree && angle < 8 * degree)
            return "North West";
	
	//Should never happen: 
	return "North";
}

function populateNearbyGroupOptions(maxDist)
{
	if (!maxDist) {
		maxDist = 2.5;
	}
	
	if (!navigator.geolocation) {
		return;
	}
	navigator.geolocation.getCurrentPosition(function (position) {
		updateUserLastLoc(position);
		
		var req = new XMLHttpRequest();
		if (!req) {
			return;
		}
		req.onreadystatechange = function() {
			if (req.readyState == 4 && req.status == 200) {
				var json = JSON.parse(req.responseText);
				if (json) {
					doPopulateNearbyGroupOptions(maxDist, position, json);
				}
			}
		};

		req.open("GET", "index.php?a=ajax-group-list", true);
	        req.send(null);
	});	
}

function updateUserLastLoc(position)
{
	var req = new XMLHttpRequest();
	if (!req) {
		return;
	}
	req.onreadystatechange = function() {
		if (req.readyState == 4 && req.status == 200) {
			;
		}
	};

	req.open("GET", "index.php?a=update-user-loc&lat=" + position.coords.latitude + "&lng=" + position.coords.longitude, true);
        req.send(null);
}

function doPopulateNearbyGroupOptions(maxDist, position, groups)
{
	var bar = document.getElementById('rightbar');
	var shdiv;
	var ul;
	var dist;
	var hasops = 0;
	for (var i = 0; i < groups.length; i++) {
		if (groups[i].lat && groups[i].lng && (dist = distance(groups[i].lat, groups[i].lng, position.coords.latitude, position.coords.longitude)) <= maxDist) {
			groups[i].distance = dist;
		} else {
			groups[i].distance = -1;
		}
	}
	groups.sort(function (a, b) {
		return a.distance < b.distance ? -1 : (a.distance > b.distance ? 1 : 0);
	});
	for (var i = 0; i < groups.length; i++) {
		if (groups[i].distance > 0) {
			if (!hasops) {
				hasops = 1;
				ul = document.createElement('ul');
			}
			var li = document.createElement('li');
			var aTag = document.createElement('a');
			aTag.setAttribute('href', 'group.php?a=view&id=' + groups[i].id);
			aTag.innerHTML = groups[i].name + ' (' + Math.round(groups[i].distance * 100) / 100 + ' miles ' + getCardinal(angleFromCoords(position.coords.latitude, position.coords.longitude, groups[i].lat, groups[i].lng)) + ')';
			li.appendChild(aTag);
			
			ul.appendChild(li);
		}
	}
	if (hasops) {
		shdiv = document.createElement('div');
		shdiv.setAttribute('class', 'header');
		shdiv.innerHTML = "nearby net groups";
		bar.appendChild(shdiv);
		bar.appendChild(ul);
	}
}
