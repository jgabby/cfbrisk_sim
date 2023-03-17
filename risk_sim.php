<html>
<head>
	<style>
	table, th, td {
		border: 1px solid black;
		border-collapse: collapse;
	}
	</style>
</head>
<body>
<?php

$max_weight=400;

echo "<h3><a href=" . $_SERVER['PHP_SELF'] . ">CFBRisk Simulator</a></h3>";

$action = validate($_POST["action"]) . validate($_GET["action"]);

switch($action) {
	case "teamselected":
		$day = validate($_POST["day"]);
		$team = html_entity_decode(validate($_POST["team"]));
		echo "$team, Day $day Selected<br>";
		#https://collegefootballrisk.com/api/territories?season=2&day=39
		$json = file_get_contents("https://collegefootballrisk.com/api/territories?season=3&day=$day");
		$json = str_replace("Hawai'i", "Hawaii", $json);
		$obj = json_decode($json);
		$defense;
		$offense;
		$neighbors;

		foreach ($obj as $key => $value) {
			$territory_name = $value->name;
			$territory_owner = $value->owner;
			foreach ($value->neighbors as $key => $value) {
				if ($value->owner != $team && $value->owner != $territory_owner) {
					$neighbors[$territory_name][$value->owner] = 1;
				}
			}
		}

		foreach ($obj as $key => $value) {
				$unsafe;
				if ($value->owner == $team) {
					$name = $value->name;
					#echo "Found Territory: " . $name . "<br>";
					$defense[$name] = $team;
					foreach ($value->neighbors as $key => $value) {
						$neighbor = $value->name;
						if ($value->owner != $team) {
							$offense[$neighbor] = $value->owner;
							$unsafe[$name]=1;
						}
					}
				}
		}
		echo "<form method=post  action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "'>\n";
		echo "<table><tr><th>Opponent Stars:<th>Our Stars:</tr>\n";
		echo "<tr><td><input type=number name=opponent_power><td><input type=number name=our_power><td><input type=submit value='Simulate Battle'></tr>\n";
		echo "<tr><th>Opponent Effective Power:<th>Our Effective Power:<th>Our Total Odds:</tr>";
		echo "<tr><td>$opp_eff_power &nbsp;<td>$our_eff_power<td>$our_odds</tr>";
		echo "</table><br>\n";
		echo "<table><tr><th width=100>Role:<th>Territory Name<th>Opponent Power<th>Opponent Weight<th>Current Owner (Neighbors)<th>Our Power<th>Our Weight<th>Our Odds</tr>";
		foreach ($defense as $key => $value) {
			#echo "Defend: $key<br>";
			$safeword='Defend';
			$default_power=100;
			if ($unsafe[$key] != 1) {
				$safeword="Safe";
				$default_power=0;
				$safe_count++;
			}
			if ($neighbors[$key]) $neighbor_list = '(' . implode(', ',array_keys($neighbors[$key])) . ')';
			else $neighbor_list = '';
			echo "<tr><td>$safeword<input type=hidden name='role_$key' value=$safeword><td>$key<td><input type=text name='opp_power$key' readonly><td><input type=range min=0 max=$max_weight value=$default_power name='opp_weight$key'><td>$value $neighbor_list<input type=hidden name='owner_$key' value='$value'><td><input type=text name='our_power$key' readonly><td><input type=range min=0 max=$max_weight value=$default_power name='our_weight$key'><td></tr>\n";
		}

		foreach ($offense as $key => $value) {
			#echo "Attack: $key ($value)<br>";
			if ($neighbors[$key]) $neighbor_list = '(' . implode(', ',array_keys($neighbors[$key])) . ')';
			else $neighbor_list = '';
			echo "<tr><td>Attack<input type=hidden name='role_$key' value=Attack><td>$key<td><input type=text name='opp_power$key' readonly><td><input type=range min=0 max=$max_weight value=100 name='opp_weight$key'><td>$value $neighbor_list<input type=hidden name='owner_$key' value='$value'><td><input type=text name='our_power$key' readonly><td><input type=range min=0 max=$max_weight value=100 name='our_weight$key'><td></tr>\n";
		}
		echo "</table>";
		$territories = implode(",",array_keys($defense)) . "," . implode(",",array_keys($offense));
		$territories = urlencode($territories);
		echo "<input type=hidden name=action value=battle><input type=hidden name=territories value='$territories'></form>";
		echo "<pre>Defending: " . count($defense) - $safe_count . "\nSafe: $safe_count\nAttacking: " . count($offense) . "</pre>";
		break;

	case "dayselected":
		$day = validate($_POST["day"]);
		echo "$day Selected";
		$json = file_get_contents("https://collegefootballrisk.com/api/stats/leaderboard?season=3&day=" . $day-1);
		$obj = json_decode($json);
		echo "<form method=post  action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "'>";
		echo "<label for=team>Choose a Team: </label>";
		echo "<select name=team>";
		foreach ($obj as $key => $value) {
				echo "<option value='" . $value->name . "''>" . $value->name . "</option>\n";
		}
		echo "</select><input type=hidden name=action value=teamselected><input type=hidden name=day value=$day><input type=submit value=Submit></form>";

		break;

	case "battle":
		$territories_string = urldecode($_POST["territories"]);
		$opponent_power = validate($_POST["opponent_power"]);
		$our_power = validate($_POST["our_power"]);

		$territories = explode(",",$territories_string);
		$opp_weights;
		$opp_powers;
		$our_weights;
		$our_powers;
		$roles;
		$odds;
		$opp_eff_power;
		$our_eff_power;
		$our_odds;
		$owners;

		foreach ($territories as $territory) {
			$html_territory = str_replace(' ', '_', $territory);
			$our_weights["$territory"] = validate($_POST["our_weight$html_territory"]);
			$opp_weights["$territory"] = validate($_POST["opp_weight$html_territory"]);
			$roles["$territory"] = validate($_POST["role_$html_territory"]);
			$owners[$territory] = validate($_POST["owner_$html_territory"]);
		}

		#assign opponent powers

		for ($i=1; $i<=$opponent_power; $i++) {
			#find the least cost
			$best_benefit=0;
			$best_name='';
			foreach ($territories as $territory) {
				#echo "$territory :" . $opp_weights[$territory] . "<br>";
				if (($opp_weights[$territory] > 0) and ($roles[$territory] != 'Safe')){
					$bonus = 1 + 0.5 * ($roles[$territory]=='Attack');
					$multiplier = ($opp_weights[$territory])/100;
					$benefit = $multiplier * $bonus/($opp_powers[$territory] + $bonus);
					#echo "$territory: $multiplier<br>";
					if ($benefit > $best_benefit) { 
						$best_benefit = $benefit;
						$best_name = $territory;
					}				
				}
			}

			switch($roles[$best_name]) {
				case "Attack":
					$opp_powers[$best_name]+=1.5;
					break;
				case "Defend":
					$opp_powers[$best_name]+=1;
					break;
				default:
					break;
			}
		}

		#assign friendly powers

		for ($i=1; $i<=$our_power; $i++) {
			#find the least cost
			$best_increase=0;
			$best_name='';
			foreach ($territories as $territory) {
				#echo "$territory :" . $opp_weights[$territory] . "<br>";
				if (($our_weights[$territory] > 0) and ($roles[$territory] != 'Safe')){
					$multiplier = $our_weights[$territory]/100;
					if ($our_powers[$territory] > 0) {
					$current_odds = $our_powers[$territory]/($our_powers[$territory] + $opp_powers[$territory]);
					}else{
							$current_odds=0;
					}
					$increase_size = 1 + 0.5 * ($roles[$territory] == 'Defend');
					$new_odds = ($our_powers[$territory] + $increase_size)/($our_powers[$territory] + $opp_powers[$territory] + $increase_size);
					$odds_increase = ($new_odds - $current_odds) * $multiplier;
					#echo "$territory: $odds_increase, $best_increase<br>";
					if ($odds_increase > $best_increase) { 
						$best_increase = $odds_increase;
						$best_name = $territory;
					}				
				}
			}
			if ($roles[$best_name] == 'Defend') {
				$our_powers[$best_name]+=1.5;
			}else{
				$our_powers[$best_name]+=1;
			}
			#echo "<br>";
		}

		foreach ($territories as $territory) {
			if ($roles[$territory] != 'Safe') {
				$odds[$territory] = round($our_powers[$territory]/($our_powers[$territory] + $opp_powers[$territory] + 1),2);
			}else{
				$odds[$territory] = 1;
			}
			$our_odds+=$odds[$territory];
			$our_eff_power+=$our_powers[$territory];
			$opp_eff_power+=$opp_powers[$territory];
		}

		#####Monte Carlo
		$result=array();
		$raw_results=array();

		$rounds = 50000;

		for ($i=0; $i<$rounds; $i++) {
			$round_result=0;
			foreach ($odds as $territory => $odd) {
				$odd *= 100;
				$roll = rand(0,1000)/10;

				#echo "$roll<br>\n";
				$round_result+= ($odd >= $roll);
			}
			$result[$round_result]++;
			array_push($raw_results,$round_result);
		}
		#####end Monte Carlo


		echo "<form method=post  action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "'>\n";
		echo "<table><tr><th>Opponent Stars:<th>Our Stars:</tr>\n";
		echo "<tr><td><input type=number name=opponent_power value='$opponent_power'><td><input type=number name=our_power value='$our_power'><td><input type=submit value='Simulate Battle'></tr>\n";
		echo "<tr><th>Opponent Effective Power:<th>Our Effective Power:<th>Our Total Odds:</tr>";
		echo "<tr><td>$opp_eff_power &nbsp;<td>$our_eff_power<td>$our_odds</tr>";
		echo "</table><br>\n";

		echo "Monte Carlo Result (n=$rounds)<br>";
		echo "<table><tr><th>Territories<th>Chance</tr>\n";
		ksort($result);
		//for ($i=0; $i<sizeof($result); $i++) {
		foreach($result as $i => $count) {
			//$percent = 100 * round($result[$i]/$rounds,4);
			$percent = 100*round($count/$rounds, 4);
			echo "<tr><td>$i<td>$percent%</tr>\n";
		}
		echo "</table><br>\n";

		echo "<label>Percentile</label><table><tr><th>25th<th>75th<th>90th</tr>\n";
		echo "<td>" . mypercentile($raw_results,25) . "<td>" . mypercentile($raw_results, 75) . "<td>" . mypercentile($raw_results, 90) . "</tr>\n";
		echo "</table><br>";


		echo "<table><tr><th width=100>Role:<th>Territory Name<th>Opponent Power<th>Opponent Weight<th>Current Owner<th>Our Power (Our Stars)<th>Our Weight<th>Our Odds</tr>";
		foreach ($territories as $territory) {
			$key = $territory;
			$role = $roles[$territory];
			$our_power = $our_powers[$territory];
			$our_stars = " (" . $our_power / (1 + 0.5 * ($role == 'Defend')) . ")";
			$our_weight = $our_weights[$territory];
			$opp_power = $opp_powers[$territory];
			$opp_weight = $opp_weights[$territory];
			$owner = $owners[$territory];
			#$default_power=100;
			echo "<tr><td>$role<input type=hidden name='role_$key' value=$role><td>$key<td><input type=text name='opp_power$key' value ='$opp_power' readonly><td><input type=range min=0 max=$max_weight value=$opp_weight name='opp_weight$key'><td>$owner<input type=hidden name='owner_$key' value='$owner'><td><input type=text name='our_power$key' value='$our_power$our_stars' readonly><td><input type=range min=0 max=$max_weight value=$our_weight name='our_weight$key'><td>$odds[$territory]</tr>\n";
		}
		echo "</table>";

		#$territories_string = implode(",",array_keys($defense)) . "," . implode(",",array_keys($offense));

		if ($territories) {
			$territories_string = implode(",",$territories);
		}		

		echo "<input type=hidden name=action value=battle><input type=hidden name=territories value='$territories_string'></form>";


		break;

	default:
		#https://collegefootballrisk.com/api/turns
		$json = file_get_contents('https://collegefootballrisk.com/api/turns');
		$obj = json_decode($json);
		echo "<form method=post  action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "'>";
		echo "<label for=day>Choose a Day: </label>";
		echo "<select name=day>";
		foreach (array_reverse($obj) as $key => $value) {
			if ($value->season == 3) {
				echo "<option value=" . $value->day . ">" . $value->day . "</option>\n";
			}
		}
		echo "</select><input type=hidden name=action value=dayselected><input type=submit value=Submit></form>";
}

function validate($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data, ENT_QUOTES);
  return $data;
}

function mypercentile($data,$percentile){
    if( 0 < $percentile && $percentile < 1 ) {
        $p = $percentile;
    }else if( 1 < $percentile && $percentile <= 100 ) {
        $p = $percentile * .01;
    }else {
        return "";
    }
    $count = count($data);
    $allindex = ($count-1)*$p;
    $intvalindex = intval($allindex);
    $floatval = $allindex - $intvalindex;
    sort($data);
    if(!is_float($floatval)){
        $result = $data[$intvalindex];
    }else {
        if($count > $intvalindex+1)
            $result = $floatval*($data[$intvalindex+1] - $data[$intvalindex]) + $data[$intvalindex];
        else
            $result = $data[$intvalindex];
    }
    return $result;
} 

?>
</body>
</html>
