<?php

function add_bounty($reward, $starttime, $endtime, $accountname, $description, $accounts, $bounties) {
	//print('$reward, $starttime, $endtime, $accountname, $description: ');var_dump($reward, $starttime, $endtime, $accountname, $description);
	if($endtime - $starttime < 1200) { // seems we're off by some minutes somewhere
		add_warning_message('Bounties must be at least 20 minutes long.');
	} else {
		$bounty_id_counter = file_get_contents('bounty_id_counter.txt');
		$duplicate_id = false;
		if($bounties->_('.bounty_id=' . $bounty_id_counter) !== false) {
			add_warning_message('Cannot add a bounty that already exists.');
			$duplicate_id = true;
		}
		$sufficient_currency = true;
		$account_currency = get_account_currency($accountname, $accounts);
		if($account_currency >= $reward) {
			
		} else {
			add_warning_message('Insufficent currency (' . $account_currency . ') to add a bounty with a reward of (' . $reward . ').');
			$sufficient_currency = false;
		}
		if(!$duplicate_id && $sufficient_currency) {
			$bounties->new_('<bounty>
<id>' . $bounty_id_counter . '</id>
<reward>' . $reward . '</reward>
<starttime>' . $starttime . '</starttime>
<endtime>' . $endtime . '</endtime>
<accountname>' . $accountname . '</accountname>
<description>' . $description . '</description>
<accepted>
</accepted>
<completed>
</completed>
</bounty>
', 'bounties');
			//print('$accounts in add_bounty: ');var_dump($accounts);
			$bounty_id_counter++;
			file_put_contents('bounty_id_counter.txt', $bounty_id_counter);
			$accounts = add_bounty_reward_to_account_currency(-1 * $reward, $accountname, $accounts);
			return array(true, $accounts, $bounties);
		}
	}
	return array(false, $accounts, $bounties);
}

function add_auction($starting_bid, $buyout, $starttime, $endtime, $accountname, $description, $accounts, $auctions) {
	//print('$starting_bid, $starttime, $endtime, $accountname, $description: ');var_dump($starting_bid, $starttime, $endtime, $accountname, $description);
	if($endtime - $starttime < 1200) { // seems we're off by some minutes somewhere
		add_warning_message('Auctions must be at least 20 minutes long.');
	} else {
		$auction_id_counter = file_get_contents('auction_id_counter.txt');
		$duplicate_id = false;
		if($auctions->_('.auction_id=' . $auction_id_counter) !== false) {
			add_warning_message('Cannot add an auction that already exists.');
			$duplicate_id = true;
		}
		if($buyout === false) {
			$buyout_string = '';
		} else {
			$buyout_string = '<buyout>' . $buyout . '</buyout>
';
		}
		if(!$duplicate_id) {
			$auctions->new_('<auction>
<id>' . $auction_id_counter . '</id>
<startingbid>' . $starting_bid . '</startingbid>
' . $buyout_string . '<starttime>' . $starttime . '</starttime>
<endtime>' . $endtime . '</endtime>
<accountname>' . $accountname . '</accountname>
<description>' . $description . '</description>
<bids>
</bids>
</auction>
', 'auctions');
			//print('$accounts in add_auction: ');var_dump($accounts);
			$auction_id_counter++;
			file_put_contents('auction_id_counter.txt', $auction_id_counter);
			//$accounts = add_auction_starting_bid_to_account_currency(-1 * $starting_bid, $accountname, $accounts);
			return array(true, $accounts, $auctions);
		}
	}
	return array(false, $accounts, $auctions);
}

function bid_on_auction($auction_id, $auction_bid, $accountname, $accounts, $auctions) {
	//print('$auction_id, $auction_bid, $accountname: ');var_dump($auction_id, $auction_bid, $accountname);
	$auction = $auctions->_('auctions_.auction_id=' . $auction_id);
	$bids = $auctions->_('bids_bid', $auction);
	//print('$auction, $bid: ');var_dump($auction, $bid);
	$satisfied_starting_bid = true;
	$last_account_bid_amount = 0;
	if(is_array($bids) && sizeof($bids) > 0) {
		foreach($bids as $last_bid_index => $last_bid) { // check for previous bids by the same account
			$last_bid_amount = $auctions->_('amount', $last_bid);
			if($auctions->_('bidder', $last_bid) === $accountname) {
				$last_account_bid_amount = $last_bid_amount;
			}
		}
	} else {
		$startingbid = $auctions->_('startingbid', $auction);
		if($auction_bid < $startingbid) {
			add_warning_message('Cannot bid less than the starting bid on an auction.');
			$satisfied_starting_bid = false;
		}
	}
	$sufficient_auction_increment = false;
	if($auction_bid >= ceil(1.10 * $last_bid_amount)) {
		$sufficient_auction_increment = true;
	} else {
		add_warning_message('A new bid on an auction must be at least 10% higher than the current bid.');
	}
	$sufficient_currency = true;
	$account_currency = get_account_currency($accountname, $accounts);
	if($account_currency >= $auction_bid - $last_account_bid_amount) {
		
	} else {
		add_warning_message('Insufficent currency (' . $account_currency . ') to bid on an auction with a bid of (' . $auction_bid . ').');
		$sufficient_currency = false;
	}
	if($satisfied_starting_bid && $sufficient_auction_increment && $sufficient_currency) {
		//print('$auctions1: ');var_dump($accounts, $auctions);
		$auctions->new_('<bid><bidder>' . $accountname . '</bidder><amount>' . $auction_bid . '</amount></bid>
', $auctions->_('bids', $auction));
		$accounts = add_to_account_currency(-1 * ($auction_bid - $last_account_bid_amount), $accountname, $accounts);
		//print('$auctions2: ');var_dump($accounts, $auctions);exit(0);
		return array(true, $accounts, $auctions);
	}
	return array(false, $accounts, $auctions);
}

function buyout_auction($auction_id, $auction_bid, $accountname, $accounts, $auctions) {
	$auction = $auctions->_('auctions_.auction_id=' . $auction_id);
	$bids = $auctions->_('bids_bid', $auction);
	$last_account_bid_amount = 0;
	if(is_array($bids) && sizeof($bids) > 0) {
		foreach($bids as $last_bid_index => $last_bid) { // check for previous bids by the same account
			$last_bid_amount = $auctions->_('amount', $last_bid);
			if($auctions->_('bidder', $last_bid) === $accountname) {
				$last_account_bid_amount = $last_bid_amount;
			}
		}
	}
	$sufficient_currency = true;
	$account_currency = get_account_currency($accountname, $accounts);
	if($account_currency >= $auction_bid - $last_account_bid_amount) {
		
	} else {
		add_warning_message('Insufficent currency (' . $account_currency . ') to buyout an auction with a bid of (' . $auction_bid . ').');
		$sufficient_currency = false;
	}
	if($sufficient_currency) {
		$auctions->__('endtime', time(), $auctions->_('auctions_.auction_id=' . $auction_id));
		$auctions->new_('<bid><bidder>' . $accountname . '</bidder><amount>' . $auction_bid . '</amount></bid>
', $auctions->_('bids', $auctions->_('auctions_.auction_id=' . $auction_id)));
		$auctions->new_('<acceptedbid><bid><bidder>' . $accountname . '</bidder><amount>' . $auction_bid . '</amount></bid></acceptedbid>
', $auctions->_('auctions_.auction_id=' . $auction_id));
		$auction_offerer = $auctions->_('accountname', $auctions->_('auctions_.auction_id=' . $auction_id));
		if($accountname !== $auction_offerer) {
			$accounts = add_reputation(1, $auction_offerer, $accounts);
			$accounts = add_reputation(1, $accountname, $accounts);
		}
		$reward_based_on_account_score = round(get_account_fully_logged_in_score($auction_offerer, $accounts) * $auction_bid, 10); // is it a problem that a bounty could be completed here when an account is not fully logged into? why precision of 10?
		//print('$auction_id, $auction_offerer, $accountname, $auction_bid, $reward_based_on_account_score: ');var_dump($auction_id, $auction_offerer, $accountname, $auction_bid, $reward_based_on_account_score);exit(0);
		$accounts = add_to_account_currency($reward_based_on_account_score, $auction_offerer, $accounts);
		$accounts = add_to_account_unavailablecurrency($auction_bid - $reward_based_on_account_score, $auction_offerer, $accounts);
		$accounts = add_to_account_currency(-1 * ($auction_bid - $last_account_bid_amount), $accountname, $accounts);
		return array(true, $accounts, $auctions);
	}
	return array(false, $accounts, $auctions);
}

function new_bounty($id, $reward, $starttime, $endtime, $accountname, $description, $accounts, $bounties) { // alias
	return add_bounty($id, $reward, $starttime, $endtime, $accountname, $description, $accounts, $bounties);
}

function submit_bounty_as_complete($id, $completer, $completion_details, $bounties) {
	$bounty = $bounties->_('.bounty_id=' . $id);
	$bounties->new_('<completiondetails><name>' . $completer . '</name><comment>' . $completion_details . '</comment></completiondetails>
', $bounties->_('completed', $bounty));
	return $bounties;
}

function complete_bounty($id, $name_of_completer, $accounts, $bounties, $completedbounties) {
	$bounty = $bounties->_('.bounty_id=' . $id);
	$bounty_offerer = $bounties->_('accountname', $bounty);
	$reward = $bounties->_('reward', $bounty);
	// add to the reputation of the completer and the issuer (if they are not the same)
	if($name_of_completer !== $bounty_offerer) {
		$accounts = add_reputation(1, $bounty_offerer, $accounts);
		$accounts = add_reputation(1, $name_of_completer, $accounts);
	}
	$reward_based_on_account_score = round(get_account_fully_logged_in_score($name_of_completer, $accounts) * $reward, 10); // is it a problem that a bounty could be completed here when an account is not fully logged into? why precision of 10?
	//print('$id, $name_of_completer, $bounty_offerer, $reward, $reward_based_on_account_score: ');var_dump($id, $name_of_completer, $bounty_offerer, $reward, $reward_based_on_account_score);exit(0);
	$accounts = add_bounty_reward_to_account_currency($reward_based_on_account_score, $name_of_completer, $accounts);
	$accounts = add_bounty_reward_to_account_unavailablecurrency($reward - $reward_based_on_account_score, $name_of_completer, $accounts);
	$bounties->__('endtime', time(), $bounties->_('.bounty_id=' . $id));
	$bounties->new_('<acceptedcompleter>' . $name_of_completer . '</acceptedcompleter>
', $bounties->_('.bounty_id=' . $id));
	//$completedbounties->new_($bounty, 'completedbounties');
	//$bounties->delete($bounty);
	return array($accounts, $bounties, $completedbounties);
}

function remove_bounty($id, $bounties) {
	$bounties->delete('.bounty_id=' . $id);
	return $bounties;
}

function get_account_currency($accountname, $accounts) {
	return $accounts->_('currency', '.account_name=' . $accounts->enc($accountname));
}

function get_currency($accountname, $accounts) { // alias
	return get_account_currency($accountname, $accounts);
}

function get_account_unavailablecurrency($accountname, $accounts) {
	return $accounts->_('unavailablecurrency', '.account_name=' . $accounts->enc($accountname));
}

function get_unavailablecurrency($accountname, $accounts) { // alias
	return get_account_unavailablecurrency($accountname, $accounts);
}

function get_account_reputation($accountname, $accounts, $force_update = false) {
	//print('$accountname, $accounts: ');var_dump($accountname, $accounts);
	if(isset($GLOBALS['reputations'][$accountname]) && !$force_update) {
		
	} else {
		$GLOBALS['reputations'][$accountname] = $accounts->_('reputation', '.account_name=' . $accounts->enc($accountname));
	}
	return $GLOBALS['reputations'][$accountname];
}

function get_reputation($accountname, $accounts) { // alias
	return get_account_reputation($accountname, $accounts);
}

function get_account_starttime($accountname, $accounts) {
	return $accounts->_('starttime', '.account_name=' . $accounts->enc($accountname));
}

function get_starttime($accountname, $accounts) { // alias
	return get_account_starttime($accountname, $accounts);
}

function get_account_password($accountname, $accounts) {
	return $accounts->_('password', '.account_name=' . $accounts->enc($accountname));
}

function get_password($accountname, $accounts) { // alias
	return get_account_password($accountname, $accounts);
}

function get_account_biometric($accountname, $accounts) {
	return $accounts->_('biometric', '.account_name=' . $accounts->enc($accountname));
}

function get_biometric($accountname, $accounts) { // alias
	return get_account_biometric($accountname, $accounts);
}

function get_account_IP($accountname, $accounts) {
	$account = $accounts->_('.account_name=' . $accounts->enc($accountname));
	$IPs = $accounts->_('IP', $account);
	//print('$IPs: ');var_dump($IPs);
	//print('$accounts->context[sizeof($accounts->context) - 1][2]: ');var_dump($accounts->context[sizeof($accounts->context) - 1][2]);
	$highest_login_count = 0;
	if($IPs) {
		if(is_array($IPs)) {
			foreach($IPs as $previous_IP_index => $previous_IP) {
				$logincount = $accounts->get_attribute('logincount', $previous_IP_index - 1); // -1 because the attributes are on the tag, not the text in the tag
				if($logincount > $highest_login_count) {
					$highest_login_count_IP = $previous_IP;
					$highest_login_count = $logincount;
				}
			}
		} else {
			$highest_login_count_IP = $IPs;
		}
	}
	//print('$highest_login_count_IP: ');var_dump($highest_login_count_IP);
	//print('$accounts->LOM: ');$accounts->var_dump_full($accounts->LOM);exit(0);
	return $highest_login_count_IP;
}

function get_IP($accountname, $accounts) {
	return get_account_IP($accountname, $accounts);
}

function account_has_a_password($accountname, $accounts) {
	if($accounts->_('password', '.account_name=' . $accounts->enc($accountname)) !== false) {
		return true;
	} else {
		return false;
	}
}

function account_has_a_biometric($accountname, $accounts) {
	if($accounts->_('biometric', '.account_name=' . $accounts->enc($accountname)) !== false) {
		return true;
	} else {
		return false;
	}
}

function account_has_a_IP($accountname, $accounts) {
	if($accounts->_('IP', '.account_name=' . $accounts->enc($accountname)) !== false) {
		return true;
	} else {
		return false;
	}
}

function account_has_an_IP($accountname, $accounts) { // alias
	return account_has_a_IP($accountname, $accounts);
}

function update_account_parameter($accountname, $parameter_name, $parameter_value) {
	fatal_error('update_account_parameter is obsolete');
	if($parameter_name === 'currency' || $parameter_name === 'unavailablecurrency') {
		$parameter_value = round($parameter_value, 10);
	}
	$accounts_contents = file_get_contents('accounts.xml');
	preg_match('/<account>
<name>' . preg_escape($accountname) . '<\/name>.+?<\/account>/is', $accounts_contents, $account_match);
	$account_string = $account_match[0];
	preg_replace('/<' . $parameter_name . '>([^<>]+)<\/' . $parameter_name . '>/is', '<' . $parameter_name . '>' . $parameter_value . '</' . $parameter_name . '>', $account_string, 1, $count); 
	if($count > 0) {
		
	} else {
		$new_account_string = str_replace('</account>', '<' . $parameter_name . '>' . $parameter_value . '</' . $parameter_name . '>
</account>', $account_string);
	}
	$accounts_contents = str_replace($account_string, $new_account_string, $accounts_contents);
	file_put_contents('accounts.xml', $accounts_contents);
}

function add_reputation($reputation_amount, $accountname, $accounts) {
	$accounts->add($reputation_amount, 'reputation', $accounts->_('.account_name=' . $accounts->enc($accountname)));
	return $accounts;
}

function add_bounty_reward_to_account_currency($reward, $accountname, $accounts) { // alias
	return add_to_account_currency($reward, $accountname, $accounts);
}

function add_to_account_currency($reward, $accountname, $accounts) {
	//print('$accounts in add_bounty_reward_to_account_currency: ');var_dump($accounts);
	$accounts->add($reward, 'currency', $accounts->_('.account_name=' . $accounts->enc($accountname)));
	return $accounts;
}

function add_bounty_reward_to_account_unavailablecurrency($reward, $accountname, $accounts) { // alias
	$accounts->add($reward, 'unavailablecurrency', $accounts->_('.account_name=' . $accounts->enc($accountname)));
	return $accounts;
}

function add_to_account_unavailablecurrency($reward, $accountname, $accounts) {
	$accounts->add($reward, 'unavailablecurrency', $accounts->_('.account_name=' . $accounts->enc($accountname)));
	return $accounts;
}

function new_account($access_credentials, $accounts) {
	if($access_credentials['accountname'] == false) {
		print('$access_credentials[\'accountname\']: ');var_dump($access_credentials['accountname']);
		fatal_error('accountname cannot be false in new_account');
	}
	if($access_credentials['password'] == false) {
		$password_string = '';
	} else {
		if(strpos($access_credentials['password'], '&') !== false || strpos($access_credentials['password'], '"') !== false || strpos($access_credentials['password'], "'") !== false || strpos($access_credentials['password'], '<') !== false || strpos($access_credentials['password'], '>') !== false) {
			add_warning_message('password cannot contain &amp; or &quot or &#039; or &lt; or &gt;.');
			return $accounts;
		} else {
			$password_string = '<password>' . password_hash($access_credentials['password'], PASSWORD_DEFAULT) . '</password>		
';
		}
	}
	if($access_credentials['biometric'] != false) {
		print('$access_credentials[\'biometric\']: ');var_dump($access_credentials['biometric']);
		fatal_error('biometric not handled in in new_account');
	}
	if($access_credentials['IP'] == false) {
		$IP_string = '';
	} else {
		$IP_string = '<IP logincount="1">' . $access_credentials['IP'] . '</IP>
';
	}
	$new_account_string = '<account>
<name>' . xml_enc($access_credentials['accountname']) . '</name>
<currency>10000</currency>
<unavailablecurrency>0</unavailablecurrency>
<reputation>0</reputation>
<starttime>' . time() . '</starttime>
' . $password_string . $IP_string . '<settings>100000010000000</settings>
</account>
'; // every account starts with 10000 units; a number chosen to balance divisibility with ability to intuitively grasp portions therein
	$accounts->new_($new_account_string, 'accounts');
	return $accounts;
}

function get_account_score($access_credentials, $accounts) {
	if(!isset($access_credentials['accountname']) || $access_credentials['accountname'] === false || $access_credentials['accountname'] === NULL) {
		print('$access_credentials[\'accountname\']: ');var_dump($access_credentials['accountname']);
		fatal_error('get_account_score requires accountname');
	}
	//print('$access_credentials in get_account_sccore: ');var_dump($access_credentials);
	$total_weighting = 0; // seems redundant
	
	$account_activity_score = get_account_activity_score(xml_enc($access_credentials['accountname']), $accounts);
	$account_activity_weighting = 0.3;
	$total_weighting += $account_activity_weighting;
	
	$account_lifetime_score = get_account_lifetime_score(xml_enc($access_credentials['accountname']), $accounts);
	$account_lifetime_weighting = 0.1;
	$total_weighting += $account_lifetime_weighting;
	
	$account_password = get_password(xml_enc($access_credentials['accountname']), $accounts);
	//print('$account_password, $access_credentials[\'password\']: ');var_dump($account_password, $access_credentials['password']);
	if($account_password !== false && password_verify($access_credentials['password'], $account_password)) {
		$account_password_score = 1;
	} else {
		$account_password_score = 0;
	}
	$account_password_weighting = 0.5;
	$total_weighting += $account_password_weighting;
	
	$account_biometric = get_biometric(xml_enc($access_credentials['accountname']), $accounts);
	//print('$account_biometric, $access_credentials[\'biometric\']: ');var_dump($account_biometric, $access_credentials['biometric']);
	if($account_biometric !== false && $account_biometric === $access_credentials['biometric']) {
		$account_biometric_score = 1;
	} else {
		$account_biometric_score = 0;
	}
	$account_biometric_weighting = 0.9;
	$total_weighting += $account_biometric_weighting;
	
	$account_IP_score = get_account_IP_score($access_credentials, $accounts);
	$account_IP_weighting = 0.3;
	$total_weighting += $account_IP_weighting;
	
	//$account_score = $account_activity_score * $account_activity_weighting + $account_lifetime_score * $account_lifetime_weighting + $account_password_score * $account_password_weighting + $account_biometric_score * $account_biometric_weighting + $account_IP_score * $account_IP_weighting;
	// roughly, this multiplies factors that take time to work on by ones that take no work
	$account_score = (($account_activity_score * $account_activity_weighting) + ($account_lifetime_score * $account_lifetime_weighting)) * (1 + ($account_password_score * $account_password_weighting) + ($account_biometric_score * $account_biometric_weighting) + ($account_IP_score * $account_IP_weighting));
	//print('$account_score, $account_activity_score, $account_lifetime_score, $account_password_score, $account_biometric_score, $account_IP_score: ');var_dump($account_score, $account_activity_score, $account_lifetime_score, $account_password_score, $account_biometric_score, $account_IP_score);exit(0);
	if($account_score > 1) {
		$account_score = 1;
	}
	if($account_score < 0) {
		$account_score = 0;
	}
	return $account_score;
}

function get_account_fully_logged_in_score($accountname, $accounts) {
	$account_activity_score = get_account_activity_score($accountname, $accounts);
	$account_activity_weighting = 0.3;
	
	$account_lifetime_score = get_account_lifetime_score($accountname, $accounts);
	$account_lifetime_weighting = 0.1;
	
	if(account_has_a_password($accountname, $accounts)) {
		$account_password_score = 1;
	} else {
		$account_password_score = 0;
	}
	$account_password_weighting = 0.5;
	
	if(account_has_a_biometric($accountname, $accounts)) {
		$account_biometric_score = 1;
	} else {
		$account_biometric_score = 0;
	}
	$account_biometric_weighting = 0.9;
	
	if(account_has_a_IP($accountname, $accounts)) {
		$account_IP_score = 1;
	} else {
		$account_IP_score = 0;
	}
	$account_IP_weighting = 0.9;
	
	// roughly, this multiplies factors that take time to work on by ones that take no work
	$account_score = (($account_activity_score * $account_activity_weighting) + ($account_lifetime_score * $account_lifetime_weighting)) * (1 + ($account_password_score * $account_password_weighting) + ($account_biometric_score * $account_biometric_weighting) + ($account_IP_score * $account_IP_weighting));
	if($account_score > 1) {
		$account_score = 1;
	}
	if($account_score < 0) {
		$account_score = 0;
	}
	return $account_score;
}

function get_account_IP_score($access_credentials, $accounts) {
	//print('$access_credentials: ');var_dump($access_credentials);
	$account = $accounts->_('.account_name=' . $accounts->enc($access_credential['accountname']));
	$account_IPs = $accounts->_('IP', $account);
	if(!is_array($account_IPs)) {
		if($account_IPs === $access_credentials['IP']) {
			return 1;
		} else {
			return 0;
		}
	}
	//print('$account_IPs: ');var_dump($account_IPs);
	$loggedincount = 0;
	$logincount_sum = 0;
	foreach($account_IPs as $account_IP_index => $account_IP_value) {
		if($account_IP_value === $access_credentials['IP']) {
			$loggedincount = $accounts->get_attribute('logincount', $account_IP_index - 1);;
		}
		$logincount_sum += $accounts->get_attribute('logincount', $account_IP_index - 1);
	}
	//print('$loggedincount, $logincount_sum: ');var_dump($loggedincount, $logincount_sum);
	if($access_credentials['IP'] === false) {
		$account_IP_score = 0;
	} else {
		$account_IP_score = $loggedincount / $logincount_sum;
	}
	//print('$account_IP_score: ');var_dump($account_IP_score);exit(0);
	return $account_IP_score;
}

function get_account_activity_score($accountname, $accounts, $force_update = false) {
	$account_activity_score = get_account_reputation($accountname, $accounts, $force_update) / 100;
	//print('$account_activity_score: ');var_dump($account_activity_score);
	if($account_activity_score > 1) {
		$account_activity_score = 1;
	}
	return $account_activity_score;
}

function get_account_lifetime_score($accountname, $accounts) {
	//print('get_account_starttime($accountname): ');var_dump(get_account_starttime($accountname));
	$account_starttime = get_account_starttime($accountname, $accounts);
	if($account_starttime === null || $account_starttime === false) {
		$account_lifetime_score = 0;
	} else {
		$account_lifetime_score = (time() - $account_starttime) / (365 * 86400);
	}
	//print('$account_lifetime_score: ');var_dump($account_lifetime_score);
	if($account_lifetime_score > 1) {
		$account_lifetime_score = 1;
	}
	return $account_lifetime_score;
}

function get_by_request($variable) {
	if($_REQUEST[$variable] == '') {
		//warning($variable . ' not properly specified.<br>');
		return false;
	} else {
		$variable = query_decode($_REQUEST[$variable]);
	}
	return $variable;
}

function query_encode($string) {
	$string = str_replace('&', '%26', $string);
	return $string;
}

function query_decode($string) {
	$string = str_replace('%26', '&', $string);
	return $string;
}

function preg_escape($string) {
	return str_replace('/', '\/', preg_quote($string));
}

function preg_escape_replacement($string) {
	$string = str_replace('$', '\$', $string);
	$string = str_replace('{', '\{', $string);
	$string = str_replace('}', '\}', $string);
	return $string;
}

function fatal_error($message) { 
	print('<span class="fatal_error">' . $message . '</span>');exit(0);
}

function warning($message) { 
	print('<span class="warning">' . $message . '</span><br>');
}

function good_news($message) { 
	print('<span class="good_news">' . $message . '</span><br>');
}

function bad_news($message) { 
	print('<span class="bad_news">' . $message . '</span><br>');
}

function fatal_error_once($string) {
	if(!isset($printed_strings[$string])) {
		print('<span class="fatal_error">' . $string . '</span>');exit(0);
		$printed_strings[$string] = true;
	}
	return true;
}

function warning_if($string, $count) {
	if($count > 1) {
		warning($string);
	}
}

function warning_once($string) {
	if(!isset($printed_strings[$string])) {
		print('<span class="warning">' . $string . '</span><br>');
		$printed_strings[$string] = true;
	}
	return true;
}

function good_news_once($string) {
	if(!isset($printed_strings[$string])) {
		print('<span class="good_news">' . $string . '</span><br>');
		$printed_strings[$string] = true;
	}
	return true;
}

function add_message($message) { 
	//global $messages;
	$GLOBALS['messages'][] = $message . '<br>';
}

function add_warning_message($message) { 
	//global $messages;
	$GLOBALS['messages'][] = '<span class="warning">' . $message . '</span><br>';
}

function time_from_formatted_date($new_bounty_endtimeformatted) {
	$year = substr($new_bounty_endtimeformatted, 0, 4);
	$month = substr($new_bounty_endtimeformatted, 5, 2);
	$day = substr($new_bounty_endtimeformatted, 8, 2);
	$hour = substr($new_bounty_endtimeformatted, 11, 2);
	$minute = substr($new_bounty_endtimeformatted, 14, 2);
	$second = substr($new_bounty_endtimeformatted, 17, 2);
	//print('$year, $month, $day, $hour, $minute, $second: ');var_dump($year, $month, $day, $hour, $minute, $second);
	//warning_once('years and months are helpful for human organization of time but quite bad for computer tracking of time... pretty much guaranteed the formula the computer uses that takes months and years in will be incorrect');
	$month_seconds = 0;
	// 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31
	if($month > 1) { // january
		$month_seconds += 31 * 86400;
	}
	if($month > 2) { // february
		if($year % 4 == 0) { // leap year
			$month_seconds += 29 * 86400;
		} else {
			$month_seconds += 28 * 86400;
		}
	}
	if($month > 3) { // march
		$month_seconds += 31 * 86400;
	}
	if($month > 4) { // april
		$month_seconds += 30 * 86400;
	}
	if($month > 5) { // may
		$month_seconds += 31 * 86400;
	}
	if($month > 6) { // june
		$month_seconds += 30 * 86400;
	}
	if($month > 7) { // july
		$month_seconds += 31 * 86400;
	}
	if($month > 8) { // august
		$month_seconds += 31 * 86400;
	}
	if($month > 9) { // september
		$month_seconds += 30 * 86400;
	}
	if($month > 10) { // october
		$month_seconds += 31 * 86400;
	}
	if($month > 11) { // november
		$month_seconds += 30 * 86400;
	}
	//if($month > 12) { // december
	//	$month_seconds += 31 * 86400;
	//}
	$new_bounty_endtime = (($year - 1970) * 365.25 * 86400) + $month_seconds + (($day - 0.5) * 86400) + (($hour - 1) * 3600) + ($minute * 60) + $second; // why day - 0.5? I have no idea
	//$new_bounty_endtime = strptime($format, (int)$new_bounty_endtimeformatted);
	//$new_bounty_endtime = mktime($new_bounty_endtimeformatted);
	//print('$new_bounty_endtimeformatted, $new_bounty_endtime: ');var_dump($new_bounty_endtimeformatted, $new_bounty_endtime);
	//print('$new_bounty_endtime - time(): ');var_dump($new_bounty_endtime - time());
	//print('date(\'Y/m/d H:i:s\', $new_bounty_endtime): ');var_dump(date('Y/m/d H:i:s', $new_bounty_endtime));
	return $new_bounty_endtime;
}

function htmlize_newline_characters($string) {
	$string = str_replace('
', '<br>', $string);
	return $string;
}

function format_for_printing($string, $access_credentials, $issuer_accountname, $completer_accountname, $accounts) {
	//print('$string in format_for_printing: ');var_dump($string);
	//if(!is_string($string)) {
	//	print('$string: ');var_dump($string);
	//	fatal_error('!is_string($string) in format_for_printing');
	//}
	//$string = xml_dec($string);
	$string = htmlize_newline_characters($string);
//	$string = process_private_content($string, $access_credentials, $issuer_accountname, $completer_accountname, $accounts); // takes a LONG time!
	return $string;
}

function process_private_content($string, $access_credentials, $issuer_accountname, $completer_accountname, $accounts) {
	preg_match_all('/&lt;private&gt;(.*?)&lt;\/private&gt;/is', $string, $private_matches);
	$account_password = get_password(xml_enc($access_credentials['accountname']), $accounts);
	//print('$string, $access_credentials[\'accountname\'], $issuer_accountname, $completer_accountname, $account_password: ');var_dump($string, $access_credentials['accountname'], $issuer_accountname, $completer_accountname, $account_password);
	if(($access_credentials['accountname'] === $issuer_accountname || $access_credentials['accountname'] === $completer_accountname) && $account_password !== false && password_verify($access_credentials['password'], $account_password)) {
		//print('can see<br>');
	} else {
		//print('can\'t see<br>');
		foreach($private_matches[0] as $index => $value) {
			$content = $private_matches[1][$index];
			$content = preg_replace('/[^ ]/is', '&nbsp;', $content);
			$new_private = '<span class="private">' . $content . '</span>';
			$string = str_replace($value, $new_private, $string);
		}
	}
	return $string;
}

function completion_details_to_string($bounties, $bounty_you_issued, $access_credentials, $issuer_accountname, $accounts) {
	$completiondetails = $bounties->_('completiondetails', $bounty_you_issued);
	//print('$bounties->context[sizeof($bounties->context) - 1][2]: ');var_dump($bounties->context[sizeof($bounties->context) - 1][2]);
	//print('$completiondetails1: ');var_dump($completiondetails);
	$completiondetails_string = '';
	if($completiondetails === false) {
		
	} else {
		if(is_string($completiondetails)) {
			$completiondetails = array($completiondetails);
		}
		foreach($completiondetails as $index => $completiondetail) {
			$completer = $bounties->_('name', $completiondetail);
			//print('$completer1: ');var_dump($completer);
			if(strlen($completer) > 0) {
				$completiondetails_string .= '<div><em>' . $completer . '</em></div>
<div class="monospace">' . format_for_printing($bounties->_('comment', $completiondetail), $access_credentials, $issuer_accountname, $completer, $accounts) . '</div>
';
			}
		}
	}
	return array($bounties, $completiondetails_string);
}

function get_existing_comment($bounties, $bounty_you_issued, $accountname) {
	$completiondetails = $bounties->_('completiondetails', $bounty_you_issued);
	if($completiondetails === false) {
		
	} else {
		if(is_string($completiondetails)) {
			$completiondetails = array($completiondetails);
		}
		foreach($completiondetails as $index => $completiondetail) {
			$completer = $bounties->_('name', $completiondetail);
			//print('$completer1: ');var_dump($completer);
			if($completer === $accountname) {
				return $bounties->_('comment', $completiondetail);
			}
		}
	}
	return false;
}

function bounty_reported_string($bounties, $bounty_you_issued) {
	$reported = $bounties->_('reported', $bounty_you_issued);
	$reportingreason_string = '';
	if($reported === false) {
		
	} else {
		if(is_string($reported)) {
			$reported = array($reported);
		}
		foreach($reported as $index => $report) {
			$reporter = $bounties->_('reporter', $report);
			if(strlen($reporter) > 0) {
				$reportingreason_string .= '<div><em>' . $reporter . '</em></div>
<div class="monospace">' . htmlize_newline_characters($bounties->_('reason', $report)) . '</div>
';
			}
		}
	}
	return array($bounties, $reportingreason_string);
}

function auction_reported_string($auctions, $auction_you_issued) {
	$reported = $auctions->_('reported', $auction_you_issued);
	$reportingreason_string = '';
	if($reported === false) {
		
	} else {
		if(is_string($reported)) {
			$reported = array($reported);
		}
		foreach($reported as $index => $report) {
			$reporter = $auctions->_('reporter', $report);
			if(strlen($reporter) > 0) {
				$reportingreason_string .= '<div><em>' . $reporter . '</em></div>
<div class="monospace">' . format_for_printing($auctions->_('reason', $report)) . '</div>
';
			}
		}
	}
	return array($auctions, $reportingreason_string);
}

function hidden_form_inputs($access_credentials, $tab_settings) {
	$hidden_inputs_string = '';
	foreach($access_credentials as $index => $value) {
		if($index === 'accountname') {
			$value = xml_enc($value);
		}
		$hidden_inputs_string .= '<input type="hidden" name="' . $index . '" value="' . $value . '" />
';
	}
	$hidden_inputs_string .= '<input type="hidden" name="tab_settings" class="tab_settings" value="' . $tab_settings . '" />
';
	return $hidden_inputs_string;
}

function accept_as_complete_actions_string($id, $access_credentials, $bounties, $bounty_you_issued, $tab_settings) {
	$actions_string = '';
	$completiondetails = $bounties->_('completiondetails', $bounty_you_issued);
	//print('$bounties->context[sizeof($bounties->context) - 1][2]: ');var_dump($bounties->context[sizeof($bounties->context) - 1][2]);
	//print('$completiondetails2: ');var_dump($completiondetails);
	if($completiondetails === false) {
		
	} else {
		if(time() - $bounties->_('starttime', $bounty_you_issued) < 1200) { // allow 20 minutes to prevent abuse. seems we're off by some minutes somewhere
			
		} else {
			if(is_string($completiondetails)) {
				$completiondetails = array($completiondetails);
			}
			foreach($completiondetails as $index => $completiondetail) {
				$completer = $bounties->_('name', $completiondetail);
				//print('$completer2: ');var_dump($completer);
				$actions_string .= '<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
<input type="hidden" name="completed_bounty_id" value="' . $id . '" />
<input type="hidden" name="completer" value="' . $completer . '" />
<input class="good_button" type="submit" value="accept as complete by ' . $completer . '" />
</form>
';
			}
		}
	}
	return array($bounties, $actions_string);
}

function bounties_you_accepted_actions_string($id, $access_credentials, $tab_settings, $existing_comment, $issuer_accountname, $completer_accountname, $accounts) {
	// <textarea name="completion_details" rows="2" cols="50"></textarea>
	$actions_string = '<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
<input type="hidden" name="offer_bounty_as_complete_id" value="' . $id . '" />
<!--small>Text in a &lt;private&gt; tag will only be visible to the bounty issuer and yourself.</small-->
';
if($existing_comment === false) {
	$actions_string .= '<textarea name="completion_details" placeholder="completing details"></textarea><br>
';
} else {
	$actions_string .= '<textarea name="completion_details">' . process_private_content($existing_comment, $access_credentials, $issuer_accountname, $completer_accountname, $accounts) . '</textarea><br>
';
}
	$actions_string .= '<input class="good_button" type="submit" value="offer bounty as complete" />
</form>
';
	return $actions_string;
}

function available_bounties_actions_string($id, $access_credentials, $tab_settings, $accounts) {
	$actions_string = '<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
<input type="hidden" name="accept_bounty_id" value="' . $id . '" />
<input class="button" type="submit" value="accept" />
</form>
';
	if(xml_enc(get_reputation($access_credentials['accountname'], $accounts)) > -1) {
		$actions_string .= '<details>
<summary>report bounty</summary>
<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
<input type="hidden" name="report_bounty_id" value="' . $id . '" />
<textarea name="report_bounty_reason" rows="2" cols="20" placeholder="reporting reason"></textarea>
<input class="warning_button" type="submit" value="report bounty" />
</form>
</details>
';
	}
	return $actions_string;
}

function available_auctions_actions_string($id, $access_credentials, $minimum_next_bid, $buyout, $tab_settings, $auctions, $accounts) {
	$actions_string = '<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
<input type="hidden" name="auction_id" value="' . $id . '" />
<input type="text" name="auction_bid" value="' . $minimum_next_bid . '" />
<input class="button" type="submit" value="bid" />
</form>
';
	if(is_numeric($buyout) && time() - $auctions->_('starttime', $auctions->_('.auction_id=' . $id)) < 1200) { // allow 20 minutes to prevent abuse. seems we're off by some minutes somewhere
		$actions_string .= '<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
<input type="hidden" name="auction_id" value="' . $id . '" />
<input type="hidden" name="auction_buyout" value="' . $buyout . '" />
<input class="button" type="submit" value="buyout" />
</form>
';
	}
	if(xml_enc(get_reputation($access_credentials['accountname'], $accounts)) > -1) {
		$actions_string .= '<details>
<summary>report auction</summary>
<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
<input type="hidden" name="report_auction_id" value="' . $id . '" />
<textarea name="report_auction_reason" rows="2" cols="20" placeholder="reporting reason"></textarea>
<input class="warning_button" type="submit" value="report auction" />
</form>
</details>
';
	}
	return $actions_string;
}

function reported_auction_actions_string($id, $access_credentials, $tab_settings) {
	return '<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
<input type="hidden" name="reported_auction_id" value="' . $id . '" />
<input type="hidden" name="reported_auction_vote" value="agree" />
<input class="button" type="submit" value="agree" />
</form>
<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
<input type="hidden" name="reported_auction_id" value="' . $id . '" />
<input type="hidden" name="reported_auction_vote" value="disagree" />
<input class="button" type="submit" value="disagree" />
</form>
';
}

function reported_bounty_actions_string($id, $access_credentials, $tab_settings) {
	return '<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
<input type="hidden" name="reported_bounty_id" value="' . $id . '" />
<input type="hidden" name="reported_bounty_vote" value="agree" />
<input class="button" type="submit" value="agree" />
</form>
<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
<input type="hidden" name="reported_bounty_id" value="' . $id . '" />
<input type="hidden" name="reported_bounty_vote" value="disagree" />
<input class="button" type="submit" value="disagree" />
</form>
';
}

function accepted_string($bounties, $bounty_you_issued) {
	//warning_once('problem to pass the bounties object unless it\'s by reference or something?');
	$acceptednames = $bounties->_('acceptedname', $bounty_you_issued);
	$accepted_string = '<div class="accepted_list">
<ul>
';
	if($acceptednames) {
		if(!is_array($acceptednames)) {
			$acceptednames = array($acceptednames);
		}
		//print('$acceptednames: ');var_dump($acceptednames);
		foreach($acceptednames as $acceptedname) {
			$accepted_string .= '<li>' . $acceptedname . '</li>
';
		}
	}
	$accepted_string .= '</ul>
</div>
';
	return array($bounties, $accepted_string);
}

function guid($string) {
	include_once('FeedWriter.php');
	$TestFeed = new FeedWriter(ATOM);
	return $TestFeed->uuid($string, '');
}

function countdown_timer_script($id, $timeleft, $prefix = '') {
	if($GLOBALS['timers'][$prefix . 'timer' . $id] === true) {
		return '';
	}
	$GLOBALS['timers'][$prefix . 'timer' . $id] = true;
	return '<script type="text/javascript">
$(document).ready(function(){
var ' . $prefix . 'count' . $id . ' = ' . $timeleft . ';
var ' . $prefix . 'counter' . $id . ' = setInterval(' . $prefix . 'timer' . $id . ', 1000); // 1000 will run it every 1 second
// days + "d" + hours + "h" + minutes + "m" + seconds + "s"
var days = Math.floor(' . $prefix . 'count' . $id . ' / 86400);
var hours = (Math.floor(' . $prefix . 'count' . $id . ' / 3600) % 24);
if(days > 0) {
	if(hours < 10) {
		hours = "0" + hours;
	}
	var countdown_string = days + "d" + hours + "h";
} else {
	var minutes = (Math.floor(' . $prefix . 'count' . $id . ' / 60) % 60);
	if(hours > 0) {
		if(minutes < 10) {
			minutes = "0" + minutes;
		}
		var countdown_string = hours + "h" + minutes + "m";
	} else {
		var seconds = ' . $prefix . 'count' . $id . ' % 60;
		if(minutes > 0) {
			if(seconds < 10) {
				seconds = "0" + seconds;
			}
			var countdown_string = minutes + "m" + seconds + "s";
		} else {
			var countdown_string = seconds + "s";
		}
	}
}
//document.getElementById("' . $prefix . 'timer' . $id . '").innerHTML = countdown_string;
var x = document.getElementsByClassName("' . $prefix . 'timer' . $id . '");
var i;
for (i = 0; i < x.length; i++) {
	x[i].innerHTML = countdown_string;
} 
//$(".' . $prefix . 'timer' . $id . '").text = countdown_string;
function ' . $prefix . 'timer' . $id . '() {
	' . $prefix . 'count' . $id . ' = ' . $prefix . 'count' . $id . ' - 1;
	if (' . $prefix . 'count' . $id . ' <= 0) {
		clearInterval(' . $prefix . 'counter' . $id . ');
		//counter ended, do something here
		//document.getElementById("' . $prefix . 'timer' . $id . '").innerHTML = "ended";
		var x = document.getElementsByClassName("' . $prefix . 'timer' . $id . '");
		var i;
		for (i = 0; i < x.length; i++) {
			x[i].innerHTML = "ended";
		} 
		//$(".' . $prefix . 'timer' . $id . '").text = "ended";
		return;
	}
	//Do code for showing the number of seconds here
	var days = Math.floor(' . $prefix . 'count' . $id . ' / 86400);
	var hours = (Math.floor(' . $prefix . 'count' . $id . ' / 3600) % 24);
	if(days > 0) {
		if(hours < 10) {
			hours = "0" + hours;
		}
		var countdown_string = days + "d" + hours + "h";
	} else {
		var minutes = (Math.floor(' . $prefix . 'count' . $id . ' / 60) % 60);
		if(hours > 0) {
			if(minutes < 10) {
				minutes = "0" + minutes;
			}
			var countdown_string = hours + "h" + minutes + "m";
		} else {
			var seconds = ' . $prefix . 'count' . $id . ' % 60;
			if(minutes > 0) {
				if(seconds < 10) {
					seconds = "0" + seconds;
				}
				var countdown_string = minutes + "m" + seconds + "s";
			} else {
				var countdown_string = seconds + "s";
			}
		}
	}
	//document.getElementById("' . $prefix . 'timer' . $id . '").innerHTML = countdown_string;
	var x = document.getElementsByClassName("' . $prefix . 'timer' . $id . '");
	var i;
	for (i = 0; i < x.length; i++) {
		x[i].innerHTML = countdown_string;
	} 
	//$(".' . $prefix . 'timer' . $id . '").text = countdown_string;
}
});
</script>';
}

function clean_up($auctions) {
	// move expired bounties and reimburse bounty issuer
	
	// once the time on an auction runs out we have to call the bidder with the highest bid the winner
	$auctions = process_ended_auctions($accounts, $auctions, $completedauctions, $expiredauctions);
	// move expired auctions and give the auction issuer the winning bid amount and (do what to give the content of the auction to auction winner) and reimburse bids that didn't win
	
	// reports...
	
}

function clean_bounties() {
	// need a function to clean bounties that expire before being completed
	// ability to extend bounties
}

function process_ended_auctions($accounts, $auctions, $completedauctions, $expiredauctions) {
	$all_auctions = $auctions->_('auctions_auction');
	if(is_array($all_auctions) && sizeof($all_auctions) > 0) {
		// have to go in reverse order
		$counter = sizeof($all_auctions) - 1;
		//foreach($all_auctions as $auction) {
		while($counter > -1) {
			$auction = $all_auctions[$counter];
			if(time() > ($auctions->_('endtime', $auction) + 604800) && $auctions->_('acceptedbid', $auction) !== false) { // show completed auctions for a week
				$completedauctions->new_($auction, 'completedauctions');
				$auctions->delete($auction);
			} elseif(time() > $auctions->_('endtime', $auction) && $auctions->_('acceptedbid', $auction) === false) {
				$bids = $auctions->_('bids_bid', $auction);
				$auction_id = $auctions->_('id', $auction);
				$last_account_bid_amount = 0;
				if(is_array($bids) && sizeof($bids) > 0) {
					foreach($bids as $last_bid_index => $last_bid) {  }
					$last_bid_amount = $auctions->_('amount', $last_bid);
					$last_bid_accountname = $auctions->_('bidder', $last_bid);
					$auctions->__('endtime', time(), $auctions->_('.auction_id=' . $auction_id));
					$auctions->new_('<acceptedbid><bidder>' . $last_bid_accountname . '</bidder><amount>' . $last_bid_amount . '</amount></acceptedbid>
', $auctions->_('.auction_id=' . $auction_id));
					$auction_offerer = $auctions->_('accountname', $auctions->_('.auction_id=' . $auction_id));
					if($last_bid_accountname !== $auction_offerer) {
						$accounts = add_reputation(1, $auction_offerer, $accounts);
						$accounts = add_reputation(1, $last_bid_accountname, $accounts);
					}
					$reward_based_on_account_score = round(get_account_fully_logged_in_score($auction_offerer, $accounts) * $auction_bid, 10); // is it a problem that a bounty could be completed here when an account is not fully logged into? why precision of 10?
					//print('$auction_id, $auction_offerer, $last_bid_accountname, $auction_bid, $reward_based_on_account_score: ');var_dump($auction_id, $auction_offerer, $last_bid_accountname, $auction_bid, $reward_based_on_account_score);exit(0);
					$accounts = add_to_account_currency($reward_based_on_account_score, $auction_offerer, $accounts);
					$accounts = add_to_account_unavailablecurrency($auction_bid - $reward_based_on_account_score, $auction_offerer, $accounts);
					//$completedauctions->new_($auction, 'completedauctions');
					//$auctions->delete($auction);
				} else {
					// dummy value since LOM would return false when looking for the acceptedbid if there were no characters in the tag
					$auctions->new_('<acceptedbid>-1</acceptedbid>
', $auctions->_('.auction_id=' . $auction_id));
					$expiredauctions->new_($auctions->_('.auction_id=' . $auction_id), 'expiredauctions');
					$auctions->delete($auctions->_('.auction_id=' . $auction_id));
				}
			}
			$counter--;
		}
	}
	return array($accounts, $auctions, $completedauctions, $expiredauctions);
}

function process_ended_bounties($accounts, $bounties, $completedbounties, $expiredbounties) {
	$all_bounties = $bounties->_('bounties_bounty');
	if(is_array($all_bounties) && sizeof($all_bounties) > 0) {
		// have to go in reverse order
		$counter = sizeof($all_bounties) - 1;
		//foreach($all_bounties as $bounty) {
		while($counter > -1) {
			$bounty = $all_bounties[$counter];
			if(time() > ($bounties->_('endtime', $bounty) + 604800) && $bounties->_('acceptedcompleter', $bounty) !== false) { // show completed bounties for a week
				$completedbounties->new_($bounty, 'completedbounties');
				$bounties->delete($bounty);
			} elseif(time() > $bounties->_('endtime', $bounty) && $bounties->_('acceptedcompleter', $bounty) === false) {
				$bounty_id = $bounties->_('id', $bounty);
				//print('expired bounty: ');$bounties->var_dump_full($bounty);
				$bounty_offerer = $bounties->_('accountname', $bounties->_('.bounty_id=' . $bounty_id));
				$reward_based_on_account_score = round(get_account_fully_logged_in_score($bounty_offerer, $accounts) * $bounties->_('reward', $bounty), 10); // is it a problem that a bounty could be completed here when an account is not fully logged into? why precision of 10?
				//print('$bounty_id, $bounty_offerer, $last_bid_accountname, $bounty_bid, $reward_based_on_account_score: ');var_dump($bounty_id, $bounty_offerer, $last_bid_accountname, $bounty_bid, $reward_based_on_account_score);exit(0);
				$accounts = add_to_account_currency($reward_based_on_account_score, $bounty_offerer, $accounts);
				$accounts = add_to_account_unavailablecurrency($bounties->_('reward', $bounties->_('.bounty_id=' . $bounty_id)) - $reward_based_on_account_score, $bounty_offerer, $accounts);
				$expiredbounties->new_($bounties->_('.bounty_id=' . $bounty_id), 'expiredbounties');
				$bounties->delete($bounties->_('.bounty_id=' . $bounty_id));
				//$bounties->generate_LOM($bounties->to_string($bounties->LOM));
				//print('$bounties->LOM after deleting: ');$bounties->var_dump_full($bounties->LOM);
				//break;
			}
			$counter--;
		}
	}
	return array($accounts, $bounties, $completedbounties, $expiredbounties);
}

function complete_ended_reports($accounts, $auctions, $bounties) {
	$all_auctions = $auctions->_('auctions_auction');
	if(is_array($all_auctions) && sizeof($all_auctions) > 0) {
		// have to go in reverse order
		$counter = sizeof($all_auctions) - 1;
		//foreach($all_auctions as $auction) {
		while($counter > -1) {
			$auction = $all_auctions[$counter];
			if($auctions->_('reported', $auction) !== false && time() > $auctions->_('reportedtime', $auction) + 86400) {
				$results_array = complete_reported_auction($auction, $accounts, $auctions);
				$accounts = $results_array[0];
				$auctions = $results_array[1];
			}
			$counter--;
		}
	}
	$all_bounties = $bounties->_('bounties_bounty');
	if(is_array($all_bounties) && sizeof($all_bounties) > 0) {
		// have to go in reverse order
		$counter = sizeof($all_bounties) - 1;
		//foreach($all_bounties as $bounty) {
		while($counter > -1) {
			$bounty = $all_bounties[$counter];
			if($bounties->_('reported', $bounty) !== false && time() > $bounties->_('reportedtime', $bounty) + 86400) {
				$results_array = complete_reported_bounty($bounty, $accounts, $bounties);
				$accounts = $results_array[0];
				$bounties = $results_array[1];
			}
			$counter--;
		}
	}
	return array($accounts, $auctions, $bounties);
}

function complete_reported_auction($auction, $accounts, $auctions) {
	$agree_count = 1;
	$disagree_count = 0;
	$votes = $auctions->_('reported_vote', $auction);
	//print('$auction, $votes: ');var_dump($auction, $votes);
	if(is_array($votes) && sizeof($votes) > 0) {
		foreach($votes as $vote) {
			if($auctions->_('choice', $vote) === 'agree') {
				$agree_count++;
			} else {
				$disagree_count++;
			}
		}
	}
	if($agree_count > $disagree_count) {
		$accounts = add_reputation(2, $auctions->_('reporter', $auction), $accounts);
		if(is_array($votes) && sizeof($votes) > 0) {
			foreach($votes as $vote) {
				if($auctions->_('choice', $vote) === 'agree') {
					$accounts = add_reputation(2, $auctions->_('voter', $vote), $accounts);
				}
			}
		}
		$accounts = add_reputation(-4, $auctions->_('accountname', $auction), $accounts);
		$bids = $auctions->_('bids_bid', $auction);
		$highest_bids = array();
		if(is_array($bids) && sizeof($bids) > 0) {
			foreach($bids as $bid) {
				if(!isset($highest_bids[$auctions->_('bidder', $bid)]) || $auctions->_('amount', $bid) > $highest_bids[$auctions->_('bidder', $bid)]) {
					$highest_bids[$auctions->_('bidder', $bid)] = $auctions->_('amount', $bid);
				}
			}
		}
		foreach($highest_bids as $bidder => $amount) {
			$accounts->add($amount, 'currency', $accounts->_('accounts_.account_name=' . $bidder));
		}
		$auctions->delete($auction);
	} elseif($agree_count < $disagree_count) {
		$auctions->delete('reported', $auction);
	} else { // it's a tie
		$accounts = add_reputation(1, $auctions->_('reporter', $auction), $accounts);
		if(is_array($votes) && sizeof($votes) > 0) {
			foreach($votes as $vote) {
				$accounts = add_reputation(1, $auctions->_('voter', $vote), $accounts);
			}
		}
		$auctions->delete('reported', $auction);
	}
	return array($accounts, $auctions);
}

function complete_reported_bounty($bounty, $accounts, $bounties) {
	$agree_count = 1;
	$disagree_count = 0;
	$votes = $bounties->_('reported_vote', $bounty);
	if(is_array($votes) && sizeof($votes) > 0) {
		foreach($votes as $vote) {
			if($bounties->_('choice', $vote) === 'agree') {
				$agree_count++;
			} else {
				$disagree_count++;
			}
		}
	}
	if($agree_count > $disagree_count) {
		$accounts = add_reputation(2, $bounties->_('reporter', $bounty), $accounts);
		if(is_array($votes) && sizeof($votes) > 0) {
			foreach($votes as $vote) {
				if($bounties->_('choice', $vote) === 'agree') {
					$accounts = add_reputation(2, $bounties->_('voter', $vote), $accounts);
				}
			}
		}
		$accounts = add_reputation(-4, $bounties->_('accountname', $bounty), $accounts);
		$accounts->add($bounties->_('reward', $bounty), 'currency', $accounts->_('accounts_.account_name=' . $bounties->_('accountname', $bounty)));
		$bounties->delete($bounty);
	} elseif($agree_count < $disagree_count) {
		$bounties->delete('reported', $bounty);
	} else { // it's a tie
		$accounts = add_reputation(1, $bounties->_('reporter', $bounty), $accounts);
		if(is_array($votes) && sizeof($votes) > 0) {
			foreach($votes as $vote) {
				$accounts = add_reputation(1, $bounties->_('voter', $vote), $accounts);
			}
		}
		$bounties->delete('reported', $bounty);
	}
	return array($accounts, $bounties);
}

function delayed_print($string) {
	$GLOBALS['delayed_print_string'] .= $string;
}

function xml_enc($string) {
	$string = htmlspecialchars($string, ENT_QUOTES);
	return $string;
}

function xml_dec($string) {
	$string = htmlspecialchars_decode($string, ENT_QUOTES);
	return $string;
}

function getmicrotime() {
	list($usec, $sec) = explode(' ', microtime());
	return ((float)$usec + (float)$sec);
}

function dump_total_time_taken() {
	$time_spent = getmicrotime() - $GLOBALS['initial_time'];
	print('Total time spent generating interface: ' . $time_spent . ' seconds.<br>');
}

?>