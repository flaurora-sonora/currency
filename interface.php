<?php

// how to avoid the continual bloating of blockchain without sacrificing integrity (verifiability)?
// advantages and disadvantages of translating things like legalese into logical data structures... might be that abuses are more prevalent from the wardens of the law; if strictly defined, there could be far more dynamism to the law
// so that it would be more adaptable and eventually more relevant. 
// being strictly logically defined allows machine learning to work on it. 
include_once('functions.php');
$GLOBALS['initial_time'] = getmicrotime();
$GLOBALS['timers'] = array();
include_once('../LOM/O.php');
error_reporting(0);

$accounts = new O('accounts.xml');
//$accounts = new O('accounts.xml', true, array('accounts', 'account', 'name', 'currency', 'unavailablecurrency', 'reputation', 'starttime', 'IP', 'password', 'biometric'));
//$accounts->set_block_tags(array('accounts', 'account', 'name', 'currency', 'unavailablecurrency', 'reputation', 'starttime', 'IP', 'password', 'biometric'));
$auctions = new O('auctions.xml');
//$auctions = new O('auctions.xml', true, array('auctions', 'auction', 'id', 'startingbid', 'buyout', 'starttime', 'endtime', 'accountname', 'description', 'bids', 'bid', 'acceptedbid'), array('bidder', 'amount'));
//$auctions->set_block_tags(array('auctions', 'auction', 'id', 'startingbid', 'buyout', 'starttime', 'endtime', 'accountname', 'description', 'bids', 'bid', 'acceptedbid'));
//$auctions->set_inline_tags(array('bidder', 'amount'));
$bounties = new O('bounties.xml');
//$bounties = new O('bounties.xml', true, array('bounties', 'bounty', 'id', 'reward', 'starttime', 'endtime', 'accountname', 'description', 'accepted', 'acceptedname', 'completed', 'completiondetails', 'acceptedcompleter'), array('name', 'comment'));
//$bounties->set_block_tags(array('bounties', 'bounty', 'id', 'reward', 'starttime', 'endtime', 'accountname', 'description', 'accepted', 'acceptedname', 'completed', 'completiondetails', 'acceptedcompleter'));
//$bounties->set_inline_tags(array('name', 'comment'));
$completedauctions = new O('completedauctions.xml');
$expiredauctions = new O('expiredauctions.xml');
$completedbounties = new O('completedbounties.xml');
$expiredbounties = new O('expiredbounties.xml');

// could be done independantly of the interface
$results_array = complete_ended_reports($accounts, $auctions, $bounties);
$accounts = $results_array[0];
$auctions = $results_array[1];
$bounties = $results_array[2];
$results_array = process_ended_auctions($accounts, $auctions, $completedauctions, $expiredauctions);
$accounts = $results_array[0];
$auctions = $results_array[1];
$completedauctions = $results_array[2];
$expiredauctions = $results_array[3];
$results_array = process_ended_bounties($accounts, $bounties, $completedbounties, $expiredbounties);
$accounts = $results_array[0];
$bounties = $results_array[1];
$completedbounties = $results_array[2];
$expiredbounties = $results_array[3];
$accountname = get_by_request('accountname');
$password = get_by_request('password');
$biometric = get_by_request('biometric');
$IP = $_SERVER['REMOTE_ADDR'];
//print('$accountname, $password, $biometric, $IP1: ');var_dump($accountname, $password, $biometric, $IP);

// if we're passed information for an existing account that we're logged in to, assume it is to be added to the account's data as long as all other information is correct
// notice that IP shouldn't completely deny login while other credentials if they are incorrect should
//print('$accounts->LOM: ');var_dump($accounts->LOM);

$access_credentials = array('accountname' => $accountname, 'password' => $password, 'biometric' => $biometric, 'IP' => $IP);
$other_currency_symbols = array('₠', '₡', '₢', '₣', '₤', '₥', '₦', '₧', '₨', '₩', '₪', '₫', '€', '₭', '₮', '₯', '₰', '₱', '₲', '₳', '₴', '₵', '₶', '₷', '₸', '₹');
$GLOBALS['messages'] = array();
//print('$access_credentials: ');var_dump($access_credentials);
//print('$accounts->_(\'account_name=\' . $accounts->enc($accountname)): ');var_dump($accounts->_('account_name=' . $accounts->enc($accountname)));
$account = $accounts->_('.account_name=' . $accounts->enc(xml_enc($accountname)));
$found_an_account_to_use = false;
$created_new_account = false;
if($account === false) {
	if(get_by_request('password') === false && get_by_request('biometric') === false) {
		// first check if there is another account with the same IP without access credentials, then make a new account only if there isn't		
		$accounts_with_IP = $accounts->_('.account_IP=' . $IP);
		if(is_array($accounts_with_IP) && sizeof($accounts_with_IP) > 0) {
			foreach($accounts_with_IP as $account_with_IP) {
				if($accounts->_('password', $account_with_IP) === false && $accounts->_('biometric', $account_with_IP) === false) {
					$accountname = $accounts->_('name', $account_with_IP);
					$access_credentials['accountname'] = $accountname;
					$found_an_account_to_use = true;
					break;
				}
			}
		}
	}
	if(!$found_an_account_to_use) {
		if($accountname === false) {
			$accountname = guid($IP . time());
			$access_credentials['accountname'] = $accountname;
		}
		$accounts = new_account($access_credentials, $accounts);
		$created_new_account = true;
	}
}
$account = $accounts->_('.account_name=' . $accounts->enc(xml_enc($accountname)));
//print('$found_an_account_to_use, $created_new_account: ');var_dump($found_an_account_to_use, $created_new_account);
//print('$account, $accounts->to_string($accounts->LOM), $accounts->enc(xml_enc($accountname)): ');$accounts->var_dump_full($account, $accounts->to_string($accounts->LOM), $accounts->enc(xml_enc($accountname)));
if($account === false) {
	print('Could not properly identify account. Try <a href="interface.php">reloading the page</a>.');exit(0);
}
?>
<html>
<head>
<title><?php if($accountname == false) {
	print('currency interface');
} else {
	print($accountname . "'s currency");
}?></title>
<link rel="stylesheet" href="bootstrap.css">
<script src="bootstrap.js"></script>
<script src="jquery.min.js"></script> <!-- jquery 3.2.0 -->
<script type="text/javascript">
$(document).ready(function(){
	$('.DataTable').DataTable( {responsive: true} );
		
	$("#currency_system_information_toggle").click(function(){
        $("#currency_system_information").slideToggle("slow");
    });
	$("#login_toggle").click(function(){
        $("#login").slideToggle("slow");
    });
	$("#new_account_toggle").click(function(){
        $("#new_account").slideToggle("slow");
    });
    $("#account_details_toggle").click(function(){
        $("#account_details").slideToggle("slow");
    });
	$("#new_bounty_toggle").click(function(){
        $("#new_bounty").slideToggle("slow");
    });
	$("#new_auction_toggle").click(function(){
        $("#new_auction").slideToggle("slow");
    });
	$("#messages_toggle").click(function(){
        $("#messages").slideToggle("slow");
    });
});
function auction_endtime_preset(newvalue) {
    document.getElementById("new_auction_endtimeformatted").value = newvalue;
}
function bounty_endtime_preset(newvalue) {
    document.getElementById("new_bounty_endtimeformatted").value = newvalue;
}
</script>
<link rel="stylesheet" type="text/css" href="styles.css">
<link rel="stylesheet" type="text/css" href="DataTables-1.10.16/css/jquery.dataTables.css"/>
<link rel="stylesheet" type="text/css" href="Responsive-2.2.0/css/responsive.dataTables.css"/>
 
<script type="text/javascript" src="DataTables-1.10.16/js/jquery.dataTables.js"></script>
<script type="text/javascript" src="Responsive-2.2.0/js/dataTables.responsive.js"></script>
<script type="text/javascript" src="tabber.js"></script>
<script type="text/javascript">

/* Optional: Temporarily hide the "tabber" class so it does not "flash"
   on the page as plain HTML. After tabber runs, the class is changed
   to "tabberlive" and it will appear. */

document.write('<style type="text/css">.tabber{display:none;}<\/style>');
</script>

</head>
<body>


<div class="container-fluid">
  <div class="row">
    <div class="col-sm-5" style="height: 30px;"><h1 style="float: left; margin-top: -12px;">currency</h1>
<div style="float: left; margin: 0px 0 0 50px;">currency system integrity: 
<?php

// expand checks to system stability in general instead of merely currency number?
$calculated_total_currency = 0;
$all_bounties = $bounties->_('bounty');
if(is_array($all_bounties) && sizeof($all_bounties) > 0) {
	foreach($all_bounties as $bounty_index => $bounty_value) {
		//print('$bounty_value: ');var_dump($bounty_value);
		if(strpos($bounties->LOM_array_to_string($bounty_value), '<acceptedcompleter>') === false) {
			$calculated_total_currency += $bounties->_('reward', $bounty_value);
		}
	}
}
$all_auctions = $auctions->_('auction');
if(is_array($all_auctions) && sizeof($all_auctions) > 0) {
	foreach($all_auctions as $auction_index => $auction_value) {
		if(strpos($auctions->LOM_array_to_string($auction_value), '<acceptedbid>') === false) {
			$bids = $auctions->_('bids_bid', $auction_value);
			$highest_account_bids = array();
			if($bids !== false) {
				foreach($bids as $bid) {
					$bidder = $auctions->_('bidder', $bid);
					$amount = $auctions->_('amount', $bid);
					$highest_account_bids[$bidder] = $amount;
				}
			}
			foreach($highest_account_bids as $bidder => $amount) {
				$calculated_total_currency += $amount;
			}
		}
	}
}
$all_accounts = $accounts->_('account');
foreach($all_accounts as $account_index => $account_value) {
	$currency = $accounts->_('currency', $account_value);
	if($currency) {
		$calculated_total_currency += $currency;
	}
	$unavailablecurrency = $accounts->_('unavailablecurrency', $account_value);
	if($unavailablecurrency) {
		$calculated_total_currency += $unavailablecurrency;
	}
}
$expected_total_currency = sizeof($all_accounts) * 10000;
//print('$expected_total_currency, $calculated_total_currency: ');var_dump($expected_total_currency, $calculated_total_currency);
$expected_total_currency = (string)$expected_total_currency;
$calculated_total_currency = (string)$calculated_total_currency;
//print('Expected total currency is: ' . $expected_total_currency . '.<br>');
//print('Calculated total currency is: ' . $calculated_total_currency . '.<br>');
//print('$expected_total_currency, $calculated_total_currency: ');var_dump($expected_total_currency, $calculated_total_currency);
if($expected_total_currency !== $calculated_total_currency) {
	print('<span id="currency_system_integrity_indicator" style="background-color: #d33333;" title="Expected total currency is not equal to calculated total currency!
expected total currency is: ' . $expected_total_currency . '.
calculated total currency is: ' . $calculated_total_currency . '."></span>');
} else {
	print('<span id="currency_system_integrity_indicator" style="background-color: #31b131;" title="Expected total currency equals calculated total currency!
expected total currency is: ' . $expected_total_currency . '.
calculated total currency is: ' . $calculated_total_currency . '."></span>');
}

?>
<span style="margin-left: 20px;">source code: <a href="https://github.com/flaurora-sonora/currency"><img src="github.png" alt="link to github source code" /></a></span></div></div>
    <div class="col-sm-5"><div id="currency_system_information_toggle">currency system information</div>
<div id="currency_system_information">
<h2 style="font-size: 100%;">general</h2>
<p>A currency system should facilitate not obstruct exchange. There is no mining in this currency system since having mining prefers those with mining ability. There is also the metaphysical aspect that mining transforms things
into mere currency, which should be avoided. Instead of mining as a way for users to contribute to the currency system, 
the currency system is policed by its users who are disincentivized to abuse the reporting system and are incentivized to come to a concensus (thus establishing standards) by rewarding the decision by majority.</p>
<!--p>An intention is to have no interaction with other tainted currencies.</p-->
<p>Crucial social infrastructure must use modern technologies and avoid corruption. Financial systems do make use of the internet but have migrated the corrupt financial systems onto it. Voting systems are starting to modernize but are facilitating new
problems by opting for secretive implementations. Legal systems are stuck at the printing press level of technology and so consistency checking of the legislature is an extremely onerous, costly, corruption-welcoming and error-prone
process. The ability of computers to do simple operations very quickly and store large amounts of information in non-degrading formats combined with the the ability of the internet to provide access to this information in
many locations and formats is a great toolset for tackling these infrastructure problems, if done properly.</p>
<h2 style="font-size: 100%;">grammar used</h2>
<p>Capitalization in this currency system interface is used for the beginnings of sentences only.</p>
<h2 style="font-size: 100%;">currency system technical details</h2>
<p>Do not lose your password because it cannot be recovered. Be cautious of accounts with low reputation. Text in a &lt;private&gt; tag will only be visible when you are logged in with a password (currently not working). 
Reporting a bounty/auction costs one reputation to prevent abuse of the reporting system. If the community agrees with the report then you will have a net gain of one reputation as a reward for regulating the currency system and the 
bounty/auction creator will lose 4 reputation. When a bounty or auction is reported it is suspended for up to a day during which time users may vote to agree or disagree with the report. Bounties or auctions in which you are involved 
show for a week after they are ended. Bounties or auctions cannot be completed in less than 20 minutes to prevent abuse.</p>
<!-- have to consider whether authoritarian access to modification of the code on github is the way to go -->
</div></div>
	<div class="col-sm-2"><div align="right"><a href="">choose theme</a></div></div>
  </div>
  <div class="row" style="height: 30px;">
    <div class="col-sm-5"><?php
//print('$account: ');var_dump($account);exit(0);
//print('$_REQUEST: ');var_dump($_REQUEST);
$existing_parameters = array();
$existing_parameters['password'] = $accounts->_('password', $account);
$existing_parameters['biometric'] = $accounts->_('biometric', $account);
//print('$existing_parameters: ');var_dump($existing_parameters);
$access_granted = true;
if($accountname == false) { // until IP or tokens are implemented
	
} else {
	if($password === false) {
		
	} elseif(password_verify($password, $existing_parameters['password'])) {
		
	} /*elseif(!isset($existing_parameters['password'])) { // then we have to add it to the account
		$password_hash = password_hash($password, PASSWORD_DEFAULT);
		$accounts->__('password', $password_hash, $accounts->_('.account_name=' . $accounts->enc(xml_enc($accountname))));
		$existing_parameters['password'] = $password_hash;
	} */else {
		warning('incorrect information entered for account: ' . $accountname);
		$access_granted = false;
	}
	if($biometric === false) {
		
	} elseif($biometric === $existing_parameters['biometric']) {
		
	} /*elseif(!isset($existing_parameters['biometric'])) { // then we have to add it to the account
		$accounts->__('biometric', $biometric, $accounts->_('.account_name=' . $accounts->enc(xml_enc($accountname))));
		$existing_parameters['biometric'] = $biometric;
	} */else {
		warning('incorrect information entered for account: ' . $accountname);
		$access_granted = false;
	}
}
//print('$access_granted: ');var_dump($access_granted);
if(!$access_granted) {
	unset($accountname);
	unset($password);
	unset($biometric);
	unset($IP);
} else {
	$created_new_account = false;
	if(!$created_new_account) { // update the IP login count
		//print('$account: ');$accounts->var_dump_full($account);
		//print('$accounts->_(\'IP=\' . $IP, $account): ');$accounts->var_dump_full($accounts->_('IP=' . $IP, $account));
		if($accounts->_('IP=' . $IP, $account)) {
			$accountIP_indices = $accounts->get_indices('IP=' . $IP, $account);
			$accounts->increment_attribute('logincount', $accountIP_indices[0] - 1); // -1 to get the tag instead of the text
		} else {
			$accounts->new_('<IP logincount="1">' . $IP . '</IP>
', $account);
		}
		//print('$accounts->LOM: ');$accounts->var_dump_full($accounts->LOM);exit(0);
	}
	$existing_parameters['IP'] = get_account_IP(xml_enc($accountname), $accounts);
	$account_score = get_account_score($access_credentials, $accounts);
	$account_currency = get_account_currency(xml_enc($accountname), $accounts);
	$account_unavailablecurrency = get_account_unavailablecurrency(xml_enc($accountname), $accounts);
	// redistribute account currency according to the current access level determined by the account score
	$currency_sum_for_balancing = $account_currency + $account_unavailablecurrency;
	if($currency_sum_for_balancing < 10000) {
		$potential_balanced_currency = 10000 * $account_score;
		$potential_balanced_unavailablecurrency = 10000 - $potential_balanced_currency;
		if($potential_balanced_unavailablecurrency > $currency_sum_for_balancing) {
			$balanced_unavailablecurrency = $currency_sum_for_balancing;
			$balanced_currency = 0;
		} else {
			$balanced_unavailablecurrency = $potential_balanced_unavailablecurrency;
			$balanced_currency = $currency_sum_for_balancing - $potential_balanced_unavailablecurrency;
		}
	} else {
		$balanced_currency = $currency_sum_for_balancing * $account_score;
		$balanced_unavailablecurrency = $currency_sum_for_balancing - $balanced_currency;
	}
	$accounts->__('currency', $balanced_currency, $accounts->_('.account_name=' . $accounts->enc(xml_enc($accountname))));
	$accounts->__('unavailablecurrency', $balanced_unavailablecurrency, $accounts->_('.account_name=' . $accounts->enc(xml_enc($accountname))));
}
//print('$accountname, $password, $biometric, $IP2: ');var_dump($accountname, $password, $biometric, $IP);

$accept_bounty_id = get_by_request('accept_bounty_id');
if($accept_bounty_id !== false) {
	$accept_bounty = $bounties->_('.bounty_id=' . $accept_bounty_id);
	if($accept_bounty === false) {
		print('$accountname, $accept_bounty_id: ');var_dump($accountname, $accept_bounty_id);
		add_warning_message('Bounty to offer as accepted was not properly identified.');
	} else {
		$bounty_string = $bounties->LOM_array_to_string($accept_bounty);
		if(strpos($bounty_string, '<acceptedname>' . xml_enc($accountname) . '</acceptedname>') === false) {
			$bounties->new_('<acceptedname>' . xml_enc($accountname) . '</acceptedname>
', $bounties->_('accepted', $accept_bounty));
		} else {
			add_warning_message('You have already accepted this bounty.');
		}
	}
}

$report_auction_id = get_by_request('report_auction_id');
if($report_auction_id !== false) {
	$report_auction_reason = get_by_request('report_auction_reason');
	if($report_auction_reason === false) {
		add_warning_message('You must include a reason when reporting an auction.');
	} else {
		$report_auction = $auctions->_('.auction_id=' . $report_auction_id);
		if($report_auction === false) {
			print('$accountname, $report_auction_id: ');var_dump($accountname, $report_auction_id);
			add_warning_message('Auction to report was not properly identified.');
		} else {
			$auction_string = $auctions->LOM_array_to_string($report_auction);
			if(strpos($auction_string, '<reported>') === false) {
				//print('$accountname, get_by_request(\'report_auction_reason\'), time(), $_REQUEST: ');var_dump($accountname, get_by_request('report_auction_reason'), time(), $_REQUEST);exit(0);
				$auctions->new_('<reported><reporter>' . xml_enc($accountname) . '</reporter><reason>' . xml_enc($report_auction_reason) . '</reason><reportedtime>' . time() . '</reportedtime></reported>
', $report_auction);
				$accounts = add_reputation(-1, xml_enc($accountname), $accounts);
			} else {
				add_warning_message('You have already reported this auction.');
			}
		}
	}
}

$report_bounty_id = get_by_request('report_bounty_id');
if($report_bounty_id !== false) {
	$report_bounty_reason = get_by_request('report_bounty_reason');
	if($report_bounty_reason === false) {
		add_warning_message('You must include a reason when reporting a bounty.');
	} else {
		$report_bounty = $bounties->_('.bounty_id=' . $report_bounty_id);
		if($report_bounty === false) {
			print('$accountname, $report_bounty_id: ');var_dump($accountname, $report_bounty_id);
			add_warning_message('Bounty to report was not properly identified.');
		} else {
			$bounty_string = $bounties->LOM_array_to_string($report_bounty);
			if(strpos($bounty_string, '<reported>') === false) {
				$bounties->new_('<reported><reporter>' . xml_enc($accountname) . '</reporter><reason>' . xml_enc($report_bounty_reason) . '</reason><reportedtime>' . time() . '</reportedtime></reported>
', $report_bounty);
				$accounts = add_reputation(-1, xml_enc($accountname), $accounts);
			} else {
				add_warning_message('You have already reported this bounty.');
			}
		}
	}
}

$reported_auction_id = get_by_request('reported_auction_id');
if($reported_auction_id !== false) {
	$reported_auction = $auctions->_('.auction_id=' . $reported_auction_id);
	if($reported_auction === false) {
		print('$accountname, $reported_auction_id: ');var_dump($accountname, $reported_auction_id);
		add_warning_message('Reported auction to vote on was not properly identified.');
	} elseif($auctions->_('reporter', $reported_auction) === xml_enc($accountname)) {
		add_warning_message('You cannot vote on an auction you reported.');
	} else {
		$auction_string = $auctions->LOM_array_to_string($reported_auction);
		if(strpos($auction_string, '<voter>' . xml_enc($accountname) . '</voter>') === false) {
			$auctions->new_('<vote><voter>' . xml_enc($accountname) . '</voter><choice>' . get_by_request('reported_auction_vote') . '</choice></vote>
', $auctions->_('reported', $reported_auction));
			$accounts = add_reputation(-1, xml_enc($accountname), $accounts);
			$agree_count = 1;
			$disagree_count = 0;
			$choices = $auctions->_('reported_vote_choice', $reported_auction);
			if(is_array($choices) && sizeof($choices) > 0) {
				foreach($choices as $choice) {
					if($choice === 'agree') {
						$agree_count++;
					} else {
						$disagree_count++;
					}
				}
			}
			if($agree_count > sizeof($accounts->_('accounts_account')) / 2 || $disagree_count > sizeof($accounts->_('accounts_account')) / 2) {
				$results_array = complete_reported_auction($auctions->_('.auction_id=' . $reported_auction_id), $accounts, $auctions); // refresh since the auction since we added a vote to it
				$accounts = $results_array[0];
				$auctions = $results_array[1];
			}
		} else {
			add_warning_message('You have already voted on this reported auction.');
		}
	}
}

$reported_bounty_id = get_by_request('reported_bounty_id');
if($reported_bounty_id !== false) {
	$reported_bounty = $bounties->_('.bounty_id=' . $reported_bounty_id);
	if($reported_bounty === false) {
		print('$accountname, $reported_bounty_id: ');var_dump($accountname, $reported_bounty_id);
		add_warning_message('Reported bounty to vote on was not properly identified.');
	} elseif($bounties->_('reporter', $reported_bounty) === xml_enc($accountname)) {
		add_warning_message('You cannot vote on a bounty you reported.');
	} else {
		$bounty_string = $bounties->LOM_array_to_string($reported_bounty);
		if(strpos($bounty_string, '<voter>' . xml_enc($accountname) . '</voter>') === false) {
			$bounties->new_('<vote><voter>' . xml_enc($accountname) . '</voter><choice>' . get_by_request('reported_bounty_vote') . '</choice></vote>
', $bounties->_('reported', $reported_bounty));
			$accounts = add_reputation(-1, xml_enc($accountname), $accounts);
			$agree_count = 1;
			$disagree_count = 0;
			$choices = $bounties->_('reported_vote_choice', $reported_bounty);
			if(is_array($choices) && sizeof($choices) > 0) {
				foreach($choices as $choice) {
					if($choice === 'agree') {
						$agree_count++;
					} else {
						$disagree_count++;
					}
				}
			}
			if($agree_count > sizeof($accounts->_('accounts_account')) / 2 || $disagree_count > sizeof($accounts->_('accounts_account')) / 2) {
				$results_array = complete_reported_bounty($bounties->_('.bounty_id=' . $reported_bounty_id), $accounts, $bounties); // refresh since the bounty since we added a vote to it
				$accounts = $results_array[0];
				$bounties = $results_array[1];
			}
		} else {
			add_warning_message('You have already voted on this reported bounty.');
		}
	}
}

$offer_bounty_as_complete_id = get_by_request('offer_bounty_as_complete_id');
if($offer_bounty_as_complete_id !== false) {
	$completion_details = get_by_request('completion_details');
	if($completion_details !== false && $completion_details !== NULL && strlen($completion_details) > 0) {
		$offer_bounty_as_complete = $bounties->_('.bounty_id=' . $offer_bounty_as_complete_id);
		if($offer_bounty_as_complete === false) {
			print('$accountname, $offer_bounty_as_complete_id: ');var_dump($accountname, $offer_bounty_as_complete_id);
			add_warning_message('Bounty to offer as complete was not properly identified.');
		} else {
			$existing_completion_details = $bounties->_('.completiondetails_name=' . $bounties->enc(xml_enc($accountname)), $bounties->_('.bounty_id=' . $offer_bounty_as_complete_id));
			if($existing_completion_details !== false) {
				$bounties->delete($existing_completion_details);
			}
			//print('$completion_details, $existing_completion_details: ');var_dump($completion_details, $existing_completion_details);
			$bounties->new_('<completiondetails><name>' . xml_enc($accountname)  . '</name><comment>' . xml_enc($completion_details)  . '</comment></completiondetails>
', $bounties->_('completed', $bounties->_('.bounty_id=' . $offer_bounty_as_complete_id)));
		}
	} else {
		add_warning_message('You must provide completing details when offering a bounty as complete.');
	}
}

$completed_bounty_id = get_by_request('completed_bounty_id');
if($completed_bounty_id !== false) {
	//print('herer34869708-0<br>');
	$completer = get_by_request('completer');
	$completed_bounty = $bounties->_('.bounty_id=' . $completed_bounty_id);
	$accepted_completer = $bounties->_('acceptedcompleter', $completed_bounty);
	if(is_string($accepted_completer) && strlen($accepted_completer) > 0) {
		add_warning_message('This bounty was already accepted as complete.');
	} else {
		$results_array = complete_bounty($completed_bounty_id, $completer, $accounts, $bounties, $completedbounties);
		$accounts = $results_array[0];
		$bounties = $results_array[1];
		$completedbounties = $results_array[2];
	}
}
//print('$completed_bounty_id, $completer: ');var_dump($completed_bounty_id, $completer);
//print('$accept_bounty_id, $offer_bounty_as_complete_id, $completed_bounty_id: ');var_dump($accept_bounty_id, $offer_bounty_as_complete_id, $completed_bounty_id);

if($accountname == false) {
	print('<div id="login_toggle">login</div>');
} else {
	print('<div id="login_toggle">change account</div>');
}

print('<div id="login">');

?>
<h2 style="margin: 0;">account login</h2>
<p>There are no required fields.</p>
<form action="interface.php" method="post">
account name: <input name="accountname" type="text" length="100" /><br>
password: <input name="password" type="password" length="100" /><br>
<!--biometric: <input name="biometric" type="text" length="100" /><br>-->
<input type="submit" value="login" class="good_button" />
</form>
<?php
print('<div id="new_account_toggle">create new account</div>');
print('<div id="new_account">');
?>
<h3>create new account</h3>
<form action="interface.php" method="post">
account name: <input name="accountname" type="text" length="100" /><br>
password: <input name="password" type="password" length="100" /><br>
<!-- other account verification parameters such as biometric, authenticator, etc. -->
<input type="submit" value="create account" class="good_button" />
</form>
</div>
</div></div>
    <div class="col-sm-5"><?php
if($accountname == false) {
	exit(0);
} else {
	//print('get_by_request(\'tab_settings\'): ');var_dump(get_by_request('tab_settings'));
	if(get_by_request('tab_settings')) {
		$accounts->__('settings', get_by_request('tab_settings'), $accounts->_('accounts_.account_name=' . xml_enc($accountname)));
	}
	$tab_settings = $accounts->_('settings', $accounts->_('accounts_.account_name=' . xml_enc($accountname))); // seven characters for whether a tab is selected in the first tab set, seven characters for whether a tab is selected in the second tab set, and a character for the theme
	//print('$tab_settings: ');var_dump($tab_settings);
	print('<div id="account_details_toggle">account details</div>');
	print('<div id="account_details">');
	$account_score_percent = round(100 * $account_score, 2);
	print('<div>account identity score formula:<br>((0.3 * reputation / 100): ');
	$account_activity_score = get_account_activity_score(xml_enc($accountname), $accounts, true);
	if($account_activity_score < 0) { 
		print('<span style="color: red;">' . ($account_activity_score * 0.3) . '</span>');
	} else {
		print('<span style="color: green;">' . ($account_activity_score * 0.3) . '</span>');
	}
	print(' + account lifetime: ');
	$account_lifetime_score = get_account_lifetime_score(xml_enc($accountname), $accounts);
	print('<span style="color: green;">' . ($account_lifetime_score * 0.1) . '</span>');
	print(') * (1 + password: ');
	if(password_verify($password, $existing_parameters['password'])){
		print('<span style="color: green;">0.5</span>');
	} else {
		print('0');
	}
	print(' + IP: ');
	$account_IP_score = get_account_IP_score($access_credentials, $accounts);
	print('<span style="color: green;">' . ($account_IP_score * 0.3) . '</span>');
	print(') = ' . $account_score_percent . '%.</div>	
<p>This means ' . $account_score_percent . '% of your account\'s currency is available for use. The unavailable portion due to a non-perfect 
account identity score can be regained when the account identity score becomes perfect.</p>');

//print('$accountname, $biometric, $existing_parameters[\'biometric\'], $password, $existing_parameters[\'password\']: ');var_dump($accountname, $biometric, $existing_parameters['biometric'], $password, $existing_parameters['password']);
// is the disencentive of making every new account only ~10% effective (by IP verification) enough to stop person from making multiple accounts? persons could still make multiple accounts with multiple passwords... of course the
?>
<p>unimplemented identity verification techniques:</p>
<ul>
<li>biometric: 0.9 <?php if($biometric != false && $biometric === $existing_parameters['biometric']){good_news('(confirmed: 0.9/0.9)');} ?></li>
<li>e-mail: 0.5</li>
<li>phone: 0.5</li>
<li>voluntary identity verification</li>
<li>test knowledge that a legitimate account user would know: ? (a sort of implosion: turning outside in or inside out)</li>
<li>PGP or other securitization once decentralized network exists</li>
<li>screen that shows account overview with buttons "Confirm this is my account" "Not my account": less abused accounts are more verifiable?</li>
<li>authenticator (sms code receiving device)?</li>
<li>mobile alerts to cell phone</li>
<li>skill testing question: would have to be in skills that are uncommon so that the test contributes to testing identity</li>
</ul>

</div></div>
	
  </div>
  <div class="row">
    <div class="col-sm-5"><?php print('<div id="new_bounty_toggle">create new bounty</div>');
	print('<div id="new_bounty">');
	//$create_or_inform_bounty = get_by_request('create_or_inform_bounty');
	//if($create_or_inform_bounty === 'create') {
	//$new_bounty_id = get_by_request('new_bounty_id');
	$new_bounty_reward = get_by_request('new_bounty_reward');
	if($new_bounty_reward !== false) {
		// form validation...
		if($new_bounty_reward <= 0 || !is_numeric($new_bounty_reward)) { // nip this stuff in the bud
			//print('$new_bounty_reward: ');var_dump($new_bounty_reward);
			add_warning_message('Reward must be a positive number.');
		} else {
			$new_bounty_description = get_by_request('new_bounty_description');
			if($new_bounty_description === NULL || $new_bounty_description === false || strlen($new_bounty_description) === 0) {
				add_warning_message('New bounties must have a description.');
			} else {
				$other_currency_mentioned = false;
				foreach($other_currency_symbols as $other_currency_symbol) {
					if(strpos($new_bounty_description, $other_currency_symbol) !== false) {
						add_warning_message('Other currencies cannot be involved (' . $other_currency_symbol . ').');
						$other_currency_mentioned = true;
						break;
					}
				}
				if(!$other_currency_mentioned) {
					//$format = '%Y/%m/%d %H:%M:%S';
					$new_bounty_starttime = get_by_request('new_bounty_starttime');
					$new_bounty_endtimeformatted = get_by_request('new_bounty_endtimeformatted');
					//print('$new_bounty_starttime, $new_bounty_endtimeformatted: ');var_dump($new_bounty_starttime, $new_bounty_endtimeformatted);
					$new_bounty_endtime = time_from_formatted_date($new_bounty_endtimeformatted);
					$new_bounty_multiple = get_by_request('new_bounty_multiple');
					// check if the bounty already exists
					//$preexisting_bounty_copies = $bounties->_('.bounty_starttime=' . $new_bounty_starttime . '&endtime=' . $new_bounty_endtime . '&reward=' . $new_bounty_reward . '&accountname=' . $bounties->enc(xml_enc($accountname)) . '&description=' . $bounties->enc(xml_enc($new_bounty_description)));
					$preexisting_bounty_copies = $bounties->_('.bounty_starttime=' . $new_bounty_starttime . '&endtime=' . $new_bounty_endtime . '&reward=' . $new_bounty_reward . '&accountname=' . $bounties->enc(xml_enc($accountname)));
					if($preexisting_bounty_copies !== false) {
						add_warning_message('This bounty already exists.');
					} else {
						$multiple = 0;
						if(is_numeric($new_bounty_multiple)) {
							if($new_bounty_multiple > 10) {
								add_warning_message('creating ' . $new_bounty_multiple . ' multiples of the same bounty at once is too many');
							} else {
								$multiple = $new_bounty_multiple;
							}
						} else {
							$multiple = 1;
						}
						while($multiple > 0) {
							$results_array = add_bounty($new_bounty_reward, $new_bounty_starttime, $new_bounty_endtime, xml_enc($accountname), xml_enc($new_bounty_description), $accounts, $bounties);
							$add_bounty_result = $results_array[0];
							$accounts = $results_array[1];
							$bounties = $results_array[2];
							if($add_bounty_result) {
								$id = file_get_contents('bounty_id_counter.txt') - 1;
								$timeleft = $new_bounty_endtime - time();
								if($timeleft > 0) {
									print(countdown_timer_script($id, $timeleft, 'bounty'));
								}
								$new_bounty_string = '<p>Your bounty has been created:</p>
<table cellspacing="0" cellpadding="4" border="1" style="width: 50%;">
<thead>
<tr>
<th scope="col">time left</th>
<th scope="col">bounty offerer</th>
<th scope="col">accepted by</th>
<th scope="col">reward</th>
<th scope="col">description</th>
<th scope="col">completing details</th>
</tr>
</thead>
<tr>
';
								if($timeleft > 0) {
									$new_bounty_string .= '<td class="bountytimer' . $id . '" style="text-align: right;"><span style="display: none;">' . ($timeleft) . '</span></td>
';
								} else {
									$new_bounty_string .= '<td style="text-align: right;"><span style="display: none;">' . ($timeleft) . '</span>ended</td>
';
								}
								$new_bounty_string .= '<td>' . $accountname . '&nbsp;(' . get_account_reputation(xml_enc($accountname), $accounts) . ')</td>
<td></td>
<td class="reward">' . $new_bounty_reward . '</td>
<td>' . format_for_printing($new_bounty_description, $access_credentials, xml_enc($accountname), false, $accounts) . '</td>
<td></td>
</tr>
</table>
';
								add_message($new_bounty_string);
							}
							$multiple--;
						}
					}
				}
			}
		}
	}
	
	//print('<form action="interface.php#new_bounty_details" method="post">
	//$format = '%Y/%m/%d %H:%M:%S';
	$format = 'Y/m/d H:i:s';
	print('<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
reward: <input type="text" name="new_bounty_reward" length="100" /><br>
your account: ' . $accountname . '<br>
start time: now<input type="hidden" name="new_bounty_starttime" value="' . time() . '" /><br>
end time: <input type="text" id="new_bounty_endtimeformatted" name="new_bounty_endtimeformatted" value="');
	print(date($format, time() + (7 * 86400)));
	// <textarea name="new_bounty_description" rows="10" cols="50"></textarea>
	// too powerful for now <input type="text" name="new_bounty_multiple" length="10" /><br> ability to create multiple bounty or auction copies at once. should multiples be displayed individually? ability to modify bounties or auctions if you are the creator. notify accounts who accepted when you modify?
	print('" /> (year/month/day hours:minutes:seconds) <span class="time_preset_button" onclick="bounty_endtime_preset(\'' . date($format, time() + (3600)) . '\')">1 hour</span><span class="time_preset_button" onclick="bounty_endtime_preset(\'' . date($format, time() + (86400)) . '\')">1 day</span><span class="time_preset_button" onclick="bounty_endtime_preset(\'' . date($format, time() + (604800)) . '\')">1 week</span><span class="time_preset_button" onclick="bounty_endtime_preset(\'' . date($format, time() + (2592000)) . '\')">1 month</span><span class="time_preset_button" onclick="bounty_endtime_preset(\'' . date($format, time() + (31536000)) . '\')">1 year</span><br>
description:<br><textarea name="new_bounty_description" rows="10" cols="50" style="width: 100%;"></textarea><br>
<input type="hidden" name="new_bounty_multiple" value="1" />
<input type="submit" value="create bounty" class="good_button" />
</form>
</div>'); ?></div>
    <div class="col-sm-5"><?php
	

	print('<div id="new_auction_toggle">create new auction</div>');
	print('<div id="new_auction">');
	

	//$create_or_inform_auction = get_by_request('create_or_inform_auction');
	//if($create_or_inform_auction === 'create') {
	//$new_auction_id = get_by_request('new_auction_id');
	$new_auction_starting_bid = get_by_request('new_auction_starting_bid');
	$new_auction_buyout = get_by_request('new_auction_buyout');
	$new_auction_description = get_by_request('new_auction_description');
	if($new_auction_starting_bid !== false) {
		if($new_auction_starting_bid <= 0 || !is_numeric($new_auction_starting_bid)) { // nip this stuff in the bud
			//print('$new_auction_starting_bid: ');var_dump($new_auction_starting_bid);
			add_warning_message('Starting bid must be a positive number.');
		} elseif($new_auction_buyout <= 0 || !is_numeric($new_auction_buyout)) { // nip this stuff in the bud
			//print('$new_auction_buyout: ');var_dump($new_auction_buyout);
			add_warning_message('Buyout must be a positive number.');
		} elseif($new_auction_buyout < $new_auction_starting_bid) { // nip this stuff in the bud
			//print('$new_auction_buyout: ');var_dump($new_auction_buyout);
			add_warning_message('Buyout cannot be less than starting bid.');
		} else {
			if($new_auction_description === NULL || $new_auction_description === false || strlen($new_auction_description) === 0) {
				add_warning_message('New auctions must have a description.');
			} else {
				$other_currency_mentioned = false;
				foreach($other_currency_symbols as $other_currency_symbol) {
					if(strpos($new_auction_description, $other_currency_symbol) !== false) {
						add_warning_message('Other currencies cannot be involved (' . $other_currency_symbol . ').');
						$other_currency_mentioned = true;
						break;
					}
				}
				if(!$other_currency_mentioned) {
					//$format = '%Y/%m/%d %H:%M:%S';
					$new_auction_starttime = get_by_request('new_auction_starttime');
					$new_auction_endtimeformatted = get_by_request('new_auction_endtimeformatted');
					//print('$new_auction_starttime, $new_auction_endtimeformatted: ');var_dump($new_auction_starttime, $new_auction_endtimeformatted);
					$new_auction_endtime = time_from_formatted_date($new_auction_endtimeformatted);
					$new_auction_multiple = get_by_request('new_auction_multiple');
					//$preexisting_auction_copies = $auctions->_('.auction_starttime=' . $new_auction_starttime . '&endtime=' . $new_auction_endtime . '&startingbid=' . $new_auction_starting_bid . '&buyout=' . $new_auction_buyout . '&accountname=' . $auctions->enc(xml_enc($accountname)) . '&description=' . $auctions->enc(xml_enc($new_auction_description)));
					$preexisting_auction_copies = $auctions->_('.auction_starttime=' . $new_auction_starttime . '&endtime=' . $new_auction_endtime . '&startingbid=' . $new_auction_starting_bid . '&buyout=' . $new_auction_buyout . '&accountname=' . $auctions->enc(xml_enc($accountname)));
					if($preexisting_auction_copies !== false) {
						add_warning_message('This auction already exists.');
					} else {
						$multiple = 0;
						if(is_numeric($new_auction_multiple)) {
							if($new_auction_multiple > 10) {
								add_warning_message('creating ' . $new_auction_multiple . ' multiples of the same auction at once is too many');
							} else {
								$multiple = $new_auction_multiple;
							}
						} else {
							$multiple = 1;
						}
						while($multiple > 0) {
							$results_array = add_auction($new_auction_starting_bid, $new_auction_buyout, $new_auction_starttime, $new_auction_endtime, xml_enc($accountname), xml_enc($new_auction_description), $accounts, $auctions);
							$add_auction_result = $results_array[0];
							$accounts = $results_array[1];
							$auctions = $results_array[2];
							if($add_auction_result) {
								$id = file_get_contents('auction_id_counter.txt') - 1;
								$timeleft = $new_auction_endtime - time();
								if($timeleft > 0) {
									print(countdown_timer_script($id, $timeleft, 'auction'));
								}
								$new_auction_string = '<p>Your auction has been created:</p>
<table cellspacing="0" cellpadding="4" border="1" style="width: 50%;">
<thead>
<tr>
<th scope="col">time left</th>
<th scope="col">auction offerer</th>
<th scope="col">starting bid</th>
<th scope="col">buyout</th>
<th scope="col">description</th>
</tr>
</thead>
<tr>
';
								if($timeleft > 0) {
									$new_auction_string .= '<td class="auctiontimer' . $id . '" style="text-align: right;"><span style="display: none;">' . ($timeleft) . '</span></td>
';
								} else {
									$new_auction_string .= '<td style="text-align: right;"><span style="display: none;">' . ($timeleft) . '</span>ended</td>
';
								}
								$new_auction_string .= '<td>' . $accountname . '&nbsp;(' . get_account_reputation(xml_enc($accountname), $accounts) . ')</td>
<td class="reward">' . $new_auction_starting_bid . '</td>
<td class="reward">' . $new_auction_buyout . '</td>
<td>' . format_for_printing($new_auction_description, $access_credentials, xml_enc($accountname), false, $accounts) . '</td>
</tr>
</table>
';
								add_message($new_auction_string);
							}
							$multiple--;
						}
					}
				}
			}
		}
	} elseif($new_auction_buyout !== false || $new_auction_description !== false) {
		add_warning_message('New auctions must have a starting bid.');
	}
	
	//print('<form action="interface.php#new_auction_details" method="post">
	//$format = '%Y/%m/%d %H:%M:%S';
	$format = 'Y/m/d H:i:s';
	print('<form action="interface.php" method="post">
' . hidden_form_inputs($access_credentials, $tab_settings) . '
starting bid: <input type="text" name="new_auction_starting_bid" length="100" /><br>
buyout: <input type="text" name="new_auction_buyout" length="100" /><br>
your account: ' . $accountname . '<br>
start time: now<input type="hidden" name="new_auction_starttime" value="' . time() . '" /><br>
end time: <input type="text" id="new_auction_endtimeformatted" name="new_auction_endtimeformatted" value="');
	print(date($format, time() + (7 * 86400)));
	// <textarea name="new_auction_description" rows="10" cols="50"></textarea>
	// too powerful for now multiple: <input type="text" name="new_auction_multiple" length="10" /><br> ability to create multiple auction or auction copies at once. should multiples be displayed individually? ability to modifiy auctions or auctions if you are the creator. notify accounts who accepted when you modify?
	print('" /> (year/month/day hours:minutes:seconds) <span class="time_preset_button" onclick="auction_endtime_preset(\'' . date($format, time() + (3600)) . '\')">1 hour</span><span class="time_preset_button" onclick="auction_endtime_preset(\'' . date($format, time() + (86400)) . '\')">1 day</span><span class="time_preset_button" onclick="auction_endtime_preset(\'' . date($format, time() + (604800)) . '\')">1 week</span><span class="time_preset_button" onclick="auction_endtime_preset(\'' . date($format, time() + (2592000)) . '\')">1 month</span><span class="time_preset_button" onclick="auction_endtime_preset(\'' . date($format, time() + (31536000)) . '\')">1 year</span><br>
description:<br><textarea name="new_auction_description" rows="10" cols="50" style="width: 100%;"></textarea><br>
<input type="hidden" name="new_auction_multiple" value="1" />
<input type="submit" value="create auction" class="good_button" />
</form>
</div>
'); ?></div>
  </div>
  <div class="row">
    <div class="col-sm-10"><?php // messages
	$auction_bid = get_by_request('auction_bid');
	if($auction_bid !== false) {
		$auction_id = get_by_request('auction_id');
		$results_array = bid_on_auction($auction_id, $auction_bid, xml_enc($accountname), $accounts, $auctions);
		$bid_on_auction_result = $results_array[0];
		$accounts = $results_array[1];
		$auctions = $results_array[2];
		if($bid_on_auction_result) {
			$auction = $auctions->_('auctions_.auction_id=' . $auction_id);
			$timeleft = $auctions->_('endtime') - time();
			$auction_offerer = $auctions->_('accountname');
			if($timeleft > 0) {
				print(countdown_timer_script($auction_id, $timeleft, 'auction'));
			}
			$successful_bid_string = '<p>successful bid:</p>
<table cellspacing="0" cellpadding="4" border="1" style="width: 50%;">
<thead>
<tr>
<th scope="col">time left</th>
<th scope="col">auction offerer</th>
<th scope="col">your bid</th>
<th scope="col">buyout</th>
<th scope="col">description</th>
</tr>
</thead>
<tr>
';
			if($timeleft > 0) {
				$successful_bid_string .= '<td class="auctiontimer' . $auction_id . '" style="text-align: right;"><span style="display: none;">' . ($timeleft) . '</span></td>
';
			} else {
				$successful_bid_string .= '<td style="text-align: right;"><span style="display: none;">' . ($timeleft) . '</span>ended</td>
';
			}
			$successful_bid_string .= '<td>' . $auction_offerer . '&nbsp;(' . get_account_reputation($auction_offerer, $accounts) . ')</td>
<td class="reward">' . $auction_bid . '</td>
<td class="reward">' . $auctions->_('buyout') . '</td>
<td>' . format_for_printing($auctions->_('description'), $access_credentials, $auction_offerer, false, $accounts) . '</td>
</tr>
</table>
';
			add_message($successful_bid_string);
		}
	}

	$auction_buyout = get_by_request('auction_buyout');
	if($auction_buyout !== false) {
		$auction_id = get_by_request('auction_id');
		$results_array = buyout_auction($auction_id, $auction_buyout, xml_enc($accountname), $accounts, $auctions);
		$buyout_on_auction_result = $results_array[0];
		$accounts = $results_array[1];
		$auctions = $results_array[2];
		if($buyout_on_auction_result) {
			$auction = $auctions->_('auctions_.auction_id=' . $auction_id);
			$auction_offerer = $auctions->_('accountname');
			$successful_buyout_string = '<p>successful buyout:</p>
<table cellspacing="0" cellpadding="4" border="1" style="width: 50%;">
<thead>
<tr>
<th scope="col">time left</th>
<th scope="col">auction offerer</th>
<th scope="col">your bid</th>
<th scope="col">buyout</th>
<th scope="col">description</th>
</tr>
</thead>
<tr>
<td class="auctiontimer' . $auction_id . '" style="text-align: right;">bought out</td>
<td>' . $auction_offerer . '&nbsp;(' . get_account_reputation($auction_offerer, $accounts) . ')</td>
<td class="reward">' . $auction_buyout . '</td>
<td class="reward">' . $auction_buyout . '</td>
<td>' . format_for_printing($auctions->_('description'), $access_credentials, xml_dec($auction_offerer), $accountname, $accounts) . '</td>
</tr>
</table>
';
			add_message($successful_buyout_string);
		}
	}

	//print('$GLOBALS[\'messages\']: ');var_dump($GLOBALS['messages']);
	if(sizeof($GLOBALS['messages']) === 0) {
		print('<div id="messages_toggle" style="clear: left;">messages</div>');
		print('<div id="messages">');
		print('There are no new messages.');
	} else {
		print('<div id="messages_toggle">messages <span class="mid_complement">(' . sizeof($messages) . ' new)</span></div>');
		print('<div id="messages">');
		foreach($GLOBALS['messages'] as $message) {
			print($message);
		}
	}
	print('</div>');

} ?></div>
  
  <div class="col-sm-2" style="margin-top: -60px;"><?php // current account information
print('<div align="right">current account: ' . xml_enc($accountname) . '</div>');
$account_currency = get_account_currency(xml_enc($accountname), $accounts);
print('<div align="right">currency: ' . $account_currency . '</div>');
$account_unavailablecurrency = get_account_unavailablecurrency(xml_enc($accountname), $accounts);
if($account_unavailablecurrency > 0) {
	print('<div align="right">unavailable currency: ' . $account_unavailablecurrency . '</div>');
}
// reputation would be split among these so it's up to reputation to disencentivize I guess. need a complex formula for reputation
$account_reputation = get_account_reputation(xml_enc($accountname), $accounts, true);
print('<div align="right">reputation: (' . $account_reputation . ')</div>');
//print('<div align="right">IP: ' . $IP . '</div>');
print('<div align="right">account identity score: ' . $account_score_percent . '%</div>'); ?></div>
</div>
</div>

<?php
//print('$accountname: ');var_dump($accountname);
if($accountname != false) {
	// side-by-side tab sets for comparison
	$GLOBALS['delayed_print_string'] = '';
		delayed_print('<div class="tabber" style="width: 50%; float: left;">
');
		// <div class="tabbertab">
		//  <h2>Tab 1</h2>
		//  <p>Tab 1 content.</p>
		// </div>
		// all bounties
		delayed_print('<section class="tabbertab">
<h2>all bounties</h2>
<table id="available_bounties_table" border="1" class="DataTable" style="width: 100%;">
<thead>
<tr>
<!--th scope="col" style="display: none;">id</th-->
');
//<th scope="col">start date</th>
//<th scope="col">end date</th>
delayed_print('<th scope="col">time left</th>
<th scope="col">bounty offerer</th>
<th scope="col">accepted by</th>
<th scope="col">reward</th>
<th scope="col">description</th>
<th scope="col">completing details</th>
<th scope="col">actions</th>
</tr>
</thead>
');
		//delayed_print('$bounties: ');var_dump($bounties);
		$available_bounties = $bounties->_('bounties_bounty'); // have to be careful not to get contextual results since we are doing this for each tab set
		//print('$available_bounties: ');var_dump($available_bounties);exit(0);
		if(is_array($available_bounties) && sizeof($available_bounties) > 0) {
			foreach($available_bounties as $available_bounty) {
				//$bounty_string = $bounties->generate_code_from_LOM($available_bounty);
				$bounty_offerer = $bounties->_('accountname', $available_bounty);
				if(($bounties->_('acceptedcompleter', $available_bounty) === false || $bounties->_('acceptedcompleter', $available_bounty) === xml_enc($accountname) || $bounty_offerer === xml_enc($accountname)) && $bounties->_('reported', $available_bounty) === false) {
					$format = '%Y/%m/%d %H:%M:%S';
					//$format = 'Y/m/d H:i:s';
					$id = $bounties->_('id', $available_bounty);
					//print('$id: ');var_dump($id);
					$reward = $bounties->_('reward', $available_bounty);
					$starttime = $bounties->_('starttime', $available_bounty);
					$endtime = $bounties->_('endtime', $available_bounty);
					//print('$endtime, time(): ');var_dump($endtime, time());
					$timeleft = $endtime - time();
					if($timeleft > 0) {
						delayed_print(countdown_timer_script($id, $timeleft, 'bounty'));
					}
					$description = $bounties->_('description', $available_bounty);
					$acceptednames = $bounties->_('acceptedname', $available_bounty);
					$result_array = accepted_string($bounties, $available_bounty);
					$bounties = $result_array[0];
					$accepted_string = $result_array[1];
					$result_array = completion_details_to_string($bounties, $available_bounty, $access_credentials, $bounty_offerer, $accounts);
					$bounties = $result_array[0];
					$completion_details_string = $result_array[1];
					if($bounties->_('acceptedcompleter', $available_bounty) === xml_enc($accountname)) {
						$actions_string = 'Your offer for this bounty was accepted.';
					} elseif(($bounties->_('acceptedcompleter', $available_bounty) !== false && $bounties->_('acceptedcompleter', $available_bounty) !== xml_enc($accountname)) && $bounty_offerer === xml_enc($accountname)) {
						$actions_string = 'Your bounty was completed.';
					} elseif($bounties->_('acceptedname', $available_bounty) === xml_enc($accountname)) {
						$actions_string = 'You accepted this bounty.';
					} else {
						$actions_string = available_bounties_actions_string($id, $access_credentials, $tab_settings, $accounts);
					}
					delayed_print('<tr>
<!--th scope="row" style="display: none;">' . $id . '</th-->
');
//<td>' . strftime($format, $starttime) . '</td>
//<td>' . strftime($format, $endtime) . '</td>
					if($timeleft > 0) {
						delayed_print('<td class="bountytimer' . $id . '" style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span></td>
	');
					} else {
						delayed_print('<td style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span>ended</td>
	');
					}
					delayed_print('<td>' . $bounty_offerer . '&nbsp;(' . get_account_reputation($bounty_offerer, $accounts) . ')</td>
<td>' . $accepted_string . '</td>
<td class="reward">' . $reward . '</td>
<td>' . format_for_printing($description, $access_credentials, xml_dec($bounty_offerer), xml_dec($bounties->_('acceptedcompleter', $available_bounty)), $accounts) . '</td>
<td>' . $completion_details_string . '</td>
<td>' . $actions_string . '</td>
</tr>');
				}
			}
		}
		delayed_print('</table>
</section>
');
		//delayed_print('$accepted: ');var_dump($accepted);
		// should the accept button be neutral or good?

		// bounties you accepted
		delayed_print('<section class="tabbertab">
<h2>bounties you accepted</h2>
<table id="bounties_you_accepted_table" border="1" class="DataTable" style="width: 100%;">
<thead>
<tr>
<!--th scope="col" style="display: none;">id</th-->
');
//<th scope="col">start date</th>
//<th scope="col">end date</th>
delayed_print('<th scope="col">time left</th>
<th scope="col">bounty offerer</th>
<th scope="col">accepted by</th>
<th scope="col">reward</th>
<th scope="col">description</th>
<th scope="col">completing details</th>
<th scope="col">actions</th>
</tr>
</thead>
');
		$bounties_you_accepted = $bounties->_('bounties_.bounty_accepted_acceptedname=' . $bounties->enc(xml_enc($accountname))); // have to be careful not to get contextual results since we are doing this for each tab set
		if(is_array($bounties_you_accepted) && sizeof($bounties_you_accepted) > 0) {
			foreach($bounties_you_accepted as $bounty_you_accepted) {
				//$bounty_string = $bounties->generate_code_from_LOM($bounty_you_accepted);
				//if(strpos($bounty_string, '<acceptedcompleter>') === false && strpos($bounty_string, '<name>' . $accountname . '</name>') === false && strpos($bounty_string, '<reported>') === false) {
				//if(strpos($bounty_string, '<acceptedcompleter>') === false && strpos($bounty_string, '<reported>') === false) {
				$bounty_offerer = $bounties->_('accountname', $bounty_you_accepted);
				if(($bounties->_('acceptedcompleter', $bounty_you_accepted) === false || $bounties->_('acceptedcompleter', $bounty_you_accepted) === xml_enc($accountname) || $bounty_offerer === xml_enc($accountname)) && $bounties->_('reported', $bounty_you_accepted) === false) {
					$format = '%Y/%m/%d %H:%M:%S';
					//$format = 'Y/m/d H:i:s';
					$id = $bounties->_('id', $bounty_you_accepted);
					//print('$id: ');var_dump($id);
					$reward = $bounties->_('reward', $bounty_you_accepted);
					$starttime = $bounties->_('starttime', $bounty_you_accepted);
					$endtime = $bounties->_('endtime', $bounty_you_accepted);
					$timeleft = $endtime - time();
					if($timeleft > 0) {
						delayed_print(countdown_timer_script($id, $timeleft, 'bounty'));
					}
					$description = $bounties->_('description', $bounty_you_accepted);
					$acceptednames = $bounties->_('acceptedname', $bounty_you_accepted);
					$result_array = accepted_string($bounties, $bounty_you_accepted);
					$bounties = $result_array[0];
					$accepted_string = $result_array[1];
					$result_array = completion_details_to_string($bounties, $bounty_you_accepted, $access_credentials, $bounty_offerer, $accounts);
					$bounties = $result_array[0];
					$completion_details_string = $result_array[1];
					if($bounties->_('acceptedcompleter', $bounty_you_accepted) === xml_enc($accountname)) {
						$actions_string = 'Your offer for this bounty was accepted.';
					} elseif(($bounties->_('acceptedcompleter', $bounty_you_accepted) !== false && $bounties->_('acceptedcompleter', $bounty_you_accepted) !== xml_enc($accountname)) && $bounty_offerer === xml_enc($accountname)) {
						$actions_string = 'Your bounty was completed.';
					} else {
						// <completiondetails><name>the_dev</name><comment>.</comment></completiondetails>
						//$existing_comment = $bounties->_('comment', $bounties->_('.completiondetails_name=' . $bounties->enc(xml_enc($accountname)), $bounties->_('bounties_.bounty_id=' . $id)));
						$existing_comment = get_existing_comment($bounties, $bounty_you_accepted, xml_enc($accountname));
						//print('$existing_comment: ');var_dump($existing_comment);
						$actions_string = bounties_you_accepted_actions_string($id, $access_credentials, $tab_settings, $existing_comment, $bounty_offerer, xml_enc($accountname), $accounts);
					}
					delayed_print('<tr>
<!--th scope="row" style="display: none;">' . $id . '</th-->
');
//<td>' . strftime($format, $starttime) . '</td>
//<td>' . strftime($format, $endtime) . '</td>
					if($timeleft > 0) {
						delayed_print('<td class="bountytimer' . $id . '" style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span></td>
');
					} else {
						delayed_print('<td style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span>ended</td>
');
					}
					delayed_print('<td>' . $bounty_offerer . '&nbsp;(' . get_account_reputation($bounty_offerer, $accounts) . ')</td>
<td>' . $accepted_string . '</td>
<td class="reward">' . $reward . '</td>
<td>' . format_for_printing($description, $access_credentials, xml_dec($bounty_offerer), xml_dec($bounties->_('acceptedcompleter', $bounty_you_accepted)), $accounts) . '</td>
<td>' . $completion_details_string . '</td>
<td>' . $actions_string . '</td>
</tr>');
				}
			}
		}
		delayed_print('</table>
</section>
');
		
		// your bounties
		delayed_print('<section class="tabbertab">
<h2>your bounties</h2>
<table id="your_bounties_table" border="1" class="DataTable" style="width: 100%;">
<thead>
<tr>
<!--th scope="col" style="display: none;">id</th-->
');
//<th scope="col">start date</th>
//<th scope="col">end date</th>
		delayed_print('<th scope="col">time left</th>
<th scope="col">bounty offerer</th>
<th scope="col">accepted by</th>
<th scope="col">reward</th>
<th scope="col">description</th>
<th scope="col">completing details</th>
<th scope="col">actions</th>
</tr>
</thead>
');
		$bounties_you_issued = $bounties->_('bounties_.bounty_accountname=' . $bounties->enc(xml_enc($accountname))); // have to be careful not to get contextual results since we are doing this for each tab set
		//print('$bounties_you_issued: ');var_dump($bounties_you_issued);
		if(is_array($bounties_you_issued) && sizeof($bounties_you_issued) > 0) {
			//print('here2375860<br>');
			//delayed_print('$accountname, $bounties_you_issued: ');var_dump($accountname, $bounties_you_issued);
			foreach($bounties_you_issued as $bounty_you_issued) {
				//print('here2375861<br>');
				//$bounty_string = $bounties->generate_code_from_LOM($bounty_you_issued);
				//if(strpos($bounty_string, '<acceptedcompleter>') === false && strpos($bounty_string, '<reported>') === false) {
				//if(($bounties->_('acceptedcompleter', $bounty_you_issued) === false || $bounties->_('acceptedcompleter', $bounty_you_issued) === xml_enc($accountname)) && $bounties->_('reported', $bounty_you_issued) === false) {
				if($bounties->_('reported', $bounty_you_issued) === false) {
					//print('here2375862<br>');
					// with table sorting it makes more sense to not have a full tab just to see which bounties are being offered as complete
					//$completiondetails = $bounties->_('completiondetails', $bounty_you_issued);
					//delayed_print('$completiondetails: ');var_dump($completiondetails);
					//if($completiondetails) {
					$format = '%Y/%m/%d %H:%M:%S';
					//$format = 'Y/m/d H:i:s';
					$id = $bounties->_('id', $bounty_you_issued);
					$reward = $bounties->_('reward', $bounty_you_issued);
					$starttime = $bounties->_('starttime', $bounty_you_issued);
					$endtime = $bounties->_('endtime', $bounty_you_issued);
					$timeleft = $endtime - time();
					if($timeleft > 0) {
						delayed_print(countdown_timer_script($id, $timeleft, 'bounty'));
					}
					$description = $bounties->_('description', $bounty_you_issued);
					$acceptednames = $bounties->_('acceptedname', $bounty_you_issued);
					$result_array = accepted_string($bounties, $bounty_you_issued);
					$bounties = $result_array[0];
					$accepted_string = $result_array[1];
					$result_array = completion_details_to_string($bounties, $bounty_you_issued, $access_credentials, $accountname, $accounts);
					$bounties = $result_array[0];
					$completion_details_string = $result_array[1];
					if($bounties->_('acceptedcompleter', $bounty_you_issued) === xml_enc($accountname)) {
						$actions_string = 'Your offer for this bounty was accepted.';
					} elseif(($bounties->_('acceptedcompleter', $bounty_you_issued) !== false && $bounties->_('acceptedcompleter', $bounty_you_issued) !== xml_enc($accountname))) {
						$actions_string = 'Your bounty was completed.';
					} else {
						$result_array = accept_as_complete_actions_string($id, $access_credentials, $bounties, $bounty_you_issued, $tab_settings);
						$bounties = $result_array[0];
						$accept_as_complete_actions_string = $result_array[1];
						$actions_string = $accept_as_complete_actions_string;
					}
					delayed_print('<tr>
<!--th scope="row" style="display: none;">' . $id . '</th-->
');
//<td>' . strftime($format, $starttime) . '</td>
//<td>' . strftime($format, $endtime) . '</td>
					if($timeleft > 0) {
						delayed_print('<td class="bountytimer' . $id . '" style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span></td>
');
					} else {
						delayed_print('<td style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span>ended</td>
');
					}
					delayed_print('<td>' . $accountname . '&nbsp;(' . get_account_reputation(xml_enc($accountname), $accounts) . ')</td>
<td>' . $accepted_string . '</td>
<td class="reward">' . $reward . '</td>
<td>' . format_for_printing($description, $access_credentials, $accountname, xml_dec($bounties->_('acceptedcompleter', $bounty_you_issued)), $accounts) . '</td>
<td>' . $completion_details_string . '</td>
<td>' . $actions_string . '</td>
</tr>');
				}
			}
		}
		delayed_print('</table>
</section>
');
		
		// all auctions
		delayed_print('<section class="tabbertab">
<h2>all auctions</h2>
<table id="available_auctions_table" border="1" class="DataTable" style="width: 100%;">
<thead>
<tr>
<!--th scope="col" style="display: none;">id</th-->
');
//<th scope="col">start date</th>
//<th scope="col">end date</th>
delayed_print('<th scope="col">time left</th>
<th scope="col">auction offerer</th>
');
//<th scope="col">bidders</th> // breach of privacy?
delayed_print('<th scope="col">current bid</th>
<th scope="col">buyout</th>
<th scope="col">description</th>
<th scope="col">actions</th>
</tr>
</thead>
');
		//delayed_print('$auctions: ');var_dump($auctions);
		$available_auctions = $auctions->_('auctions_auction'); // have to be careful not to get contextual results since we are doing this for each tab set
		if(is_array($available_auctions) && sizeof($available_auctions) > 0) {
			foreach($available_auctions as $available_auction) {
				//$auction_string = $auctions->generate_code_from_LOM($available_auction);
				//if(strpos($auction_string, '<acceptedbid>') === false && strpos($auction_string, '<reported>') === false) {
				$auction_offerer = $auctions->_('accountname', $available_auction);
				//if($auctions->_('acceptedbid', $available_auction) === false && $auctions->_('reported', $available_auction) === false) {
				if(($auctions->_('acceptedbid_bidder', $available_auction) === false || $auctions->_('acceptedbid_bidder', $available_auction) === xml_enc($accountname) || $auction_offerer === xml_enc($accountname)) && $auctions->_('reported', $available_auction) === false) {
					$format = '%Y/%m/%d %H:%M:%S';
					//$format = 'Y/m/d H:i:s';
					$id = $auctions->_('id', $available_auction);
					$bids = $auctions->_('bid_amount', $available_auction);
					//delayed_print('$bids: ');var_dump($bids);exit(0);
					$startingbid = $auctions->_('startingbid', $available_auction);
					if(is_string($bids)) {
						$last_bid = $bids;
						$next_bid = ceil($last_bid * 1.10);
					} elseif($bids === false) {
						$last_bid = '';
						$next_bid = $startingbid;
					} else {
						foreach($bids as $last_bid_index => $last_bid) {  }
						$next_bid = ceil($last_bid * 1.10);
					}
					$buyout = $auctions->_('buyout', $available_auction);
					$starttime = $auctions->_('starttime', $available_auction);
					$endtime = $auctions->_('endtime', $available_auction);
					$timeleft = $endtime - time();
					if($timeleft > 0) {
						delayed_print(countdown_timer_script($id, $timeleft, 'auction'));
					}
					$description = $auctions->_('description', $available_auction);
					if($auctions->_('acceptedbid_bidder', $available_auction) === xml_enc($accountname)) {
						$actions_string = 'You won this auction.';
					} elseif(($auctions->_('acceptedbid_bidder', $available_auction) !== false && $auctions->_('acceptedbid_bidder', $available_auction) !== xml_enc($accountname)) && $auction_offerer === xml_enc($accountname)) {
						$actions_string = 'Your auction was completed.';
					} else {
						$actions_string = available_auctions_actions_string($id, $access_credentials, $next_bid, $buyout, $tab_settings, $auctions, $accounts);
					}
					delayed_print('<tr>
<!--th scope="row" style="display: none;">' . $id . '</th-->
');
//<td>' . strftime($format, $starttime) . '</td>
//<td>' . strftime($format, $endtime) . '</td>
					if($timeleft > 0) {
						delayed_print('<td class="auctiontimer' . $id . '" style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span></td>
	');
					} else {
						delayed_print('<td style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span>ended</td>
	');
					}
					delayed_print('<td>' . $auction_offerer . '&nbsp;(' . get_account_reputation($auction_offerer, $accounts) . ')</td>
<td class="reward">' . $last_bid . '</td>
<td class="reward">' . $buyout . '</td>
<td>' . format_for_printing($description, $access_credentials, xml_dec($auction_offerer), xml_dec($auctions->_('acceptedbid_bidder', $available_auction)), $accounts) . '</td>
<td>' . $actions_string . '</td>
</tr>'); // might want to round instead of ceil at least in cases where the amount of currency being considered is less than 1 (which may be few)
				}
			}
		}
		delayed_print('</table>
</section>
');
		//delayed_print('$accepted: ');var_dump($accepted);
		// should the bid buttons be neutral or good?

		// auctions you bid on
		// would like to show the amount of your bid
		delayed_print('<section class="tabbertab">
<h2>auctions you bid on</h2>
<table id="auctions_you_bid_on_table" border="1" class="DataTable" style="width: 100%;">
<thead>
<tr>
<!--th scope="col" style="display: none;">id</th-->
');
//<th scope="col">start date</th>
//<th scope="col">end date</th>
delayed_print('<th scope="col">time left</th>
<th scope="col">auction offerer</th>
');
//<th scope="col">bidders</th> // breach of privacy?
delayed_print('<th scope="col">current bid</th>
<th scope="col">buyout</th>
<th scope="col">description</th>
<th scope="col">actions</th>
</tr>
</thead>
');
		$auctions_you_bid_on = $auctions->_('auctions_.auction_bids_bid_bidder=' . $auctions->enc(xml_enc($accountname))); // have to be careful not to get contextual results since we are doing this for each tab set
		if(is_array($auctions_you_bid_on) && sizeof($auctions_you_bid_on) > 0) {
			foreach($auctions_you_bid_on as $auction_you_bid_on) {
				//$auction_string = $auctions->generate_code_from_LOM($auction_you_bid_on);
				//if(strpos($auction_string, '<acceptedbid>') === false && strpos($auction_string, '<reported>') === false) {
				//if($auctions->_('acceptedbid', $auction_you_bid_on) === false && $auctions->_('reported', $auction_you_bid_on) === false) {
				$auction_offerer = $auctions->_('accountname', $auction_you_bid_on);
				if(($auctions->_('acceptedbid_bidder', $auction_you_bid_on) === false || $auctions->_('acceptedbid_bidder', $auction_you_bid_on) === xml_enc($accountname) || $auction_offerer === xml_enc($accountname)) && $auctions->_('reported', $auction_you_bid_on) === false) {
					$format = '%Y/%m/%d %H:%M:%S';
					//$format = 'Y/m/d H:i:s';
					$id = $auctions->_('id', $auction_you_bid_on);
					$bids = $auctions->_('bid_amount', $auction_you_bid_on);
					$startingbid = $auctions->_('startingbid', $auction_you_bid_on);
					if(is_string($bids)) {
						$last_bid = $bids;
						$next_bid = ceil($last_bid * 1.10);
					} elseif($bids === false) {
						$last_bid = '';
						$next_bid = $startingbid;
					} else {
						foreach($bids as $last_bid_index => $last_bid) {  }
						$next_bid = ceil($last_bid * 1.10);
					}
					$buyout = $auctions->_('buyout', $auction_you_bid_on);
					$starttime = $auctions->_('starttime', $auction_you_bid_on);
					$endtime = $auctions->_('endtime', $auction_you_bid_on);
					$timeleft = $endtime - time();
					if($timeleft > 0) {
						delayed_print(countdown_timer_script($id, $timeleft, 'auction'));
					}
					$description = $auctions->_('description', $auction_you_bid_on);
					if($auctions->_('acceptedbid_bidder', $auction_you_bid_on) === xml_enc($accountname)) {
						$actions_string = 'You won this auction.';
					} elseif(($auctions->_('acceptedbid_bidder', $auction_you_bid_on) !== false && $auctions->_('acceptedbid_bidder', $auction_you_bid_on) !== xml_enc($accountname)) && $auction_offerer === xml_enc($accountname)) {
						$actions_string = 'Your auction was completed.';
					} else {
						$actions_string = available_auctions_actions_string($id, $access_credentials, $next_bid, $buyout, $tab_settings, $auctions, $accounts);
					}
					delayed_print('<tr>
<!--th scope="row" style="display: none;">' . $id . '</th-->
');
//<td>' . strftime($format, $starttime) . '</td>
//<td>' . strftime($format, $endtime) . '</td>
					if($timeleft > 0) {
						delayed_print('<td class="auctiontimer' . $id . '" style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span></td>
');
					} else {
						delayed_print('<td style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span>ended</td>
');
					}
					delayed_print('<td>' . $auction_offerer . '&nbsp;(' . get_account_reputation($auction_offerer, $accounts) . ')</td>
<td class="reward">' . $last_bid . '</td>
<td class="reward">' . $buyout . '</td>
<td>' . format_for_printing($description, $access_credentials, xml_dec($auction_offerer), xml_dec($auctions->_('acceptedbid_bidder', $auction_you_bid_on)), $accounts) . '</td>
<td>' . $actions_string . '</td>
</tr>'); // might want to round instead of ceil at least in cases where the amount of currency being considered is less than 1 (which may be few)
				}
			}
		}
		delayed_print('</table>
</section>
');
		
		// your auctions
		delayed_print('<section class="tabbertab">
<h2>your auctions</h2>
<table id="your_auctions_table" border="1" class="DataTable" style="width: 100%;">
<thead>
<tr>
<!--th scope="col" style="display: none;">id</th-->
');
//<th scope="col">start date</th>
//<th scope="col">end date</th>
delayed_print('<th scope="col">time left</th>
<th scope="col">auction offerer</th>
');
//<th scope="col">bidders</th> // breach of privacy?
delayed_print('<th scope="col">current bid</th>
<th scope="col">buyout</th>
<th scope="col">description</th>
<th scope="col">actions</th>
</tr>
</thead>
');
		$auctions_you_issued = $auctions->_('auctions_.auction_accountname=' . $auctions->enc(xml_enc($accountname))); // have to be careful not to get contextual results since we are doing this for each tab set
		if(is_array($auctions_you_issued) && sizeof($auctions_you_issued) > 0) {
			//delayed_print('$accountname, $auctions_you_issued: ');var_dump($accountname, $auctions_you_issued);
			foreach($auctions_you_issued as $auction_you_issued) {
				//$auction_string = $auctions->generate_code_from_LOM($auction_you_issued);
				//if(strpos($auction_string, '<acceptedbid>') === false && strpos($auction_string, '<reported>') === false) {
				//if($auctions->_('acceptedbid', $auction_you_issued) === false && $auctions->_('reported', $auction_you_issued) === false) {
				//if(($auctions->_('acceptedbid_bidder', $auction_you_issued) === false || $auctions->_('acceptedbid_bidder', $auction_you_issued) === xml_enc($accountname)) && $auctions->_('reported', $auction_you_issued) === false) {
				if($auctions->_('reported', $auction_you_issued) === false) {
					// with table sorting it makes more sense to not have a full tab just to see which auctions are being offered as complete
					//$completiondetails = $auctions->_('completiondetails', $auction_you_issued);
					//delayed_print('$completiondetails: ');var_dump($completiondetails);
					//if($completiondetails) {
					$format = '%Y/%m/%d %H:%M:%S';
					//$format = 'Y/m/d H:i:s';
					$id = $auctions->_('id', $auction_you_issued);
					$bids = $auctions->_('bid_amount', $auction_you_issued);
					$startingbid = $auctions->_('startingbid', $auction_you_issued);
					if(is_string($bids)) {
						$last_bid = $bids;
						$next_bid = ceil($last_bid * 1.10);
					} elseif($bids === false) {
						$last_bid = '';
						$next_bid = $startingbid;
					} else {
						foreach($bids as $last_bid_index => $last_bid) {  }
						$next_bid = ceil($last_bid * 1.10);
					}
					$buyout = $auctions->_('buyout', $auction_you_issued);
					$starttime = $auctions->_('starttime', $auction_you_issued);
					$endtime = $auctions->_('endtime', $auction_you_issued);
					$timeleft = $endtime - time();
					if($timeleft > 0) {
						delayed_print(countdown_timer_script($id, $timeleft, 'auction'));
					}
					//$auction_offerer = $auctions->_('accountname', $auction_you_issued);
					$description = $auctions->_('description', $auction_you_issued);
					if($auctions->_('acceptedbid_bidder', $auction_you_issued) === xml_enc($accountname)) {
						$actions_string = 'You won this auction.';
					} elseif(($auctions->_('acceptedbid_bidder', $auction_you_issued) !== false && $auctions->_('acceptedbid_bidder', $auction_you_issued)) !== xml_enc($accountname)) {
						$actions_string = 'Your auction was completed.';
					} else {
						$actions_string = available_auctions_actions_string($id, $access_credentials, $next_bid, $buyout, $tab_settings, $auctions, $accounts);
					}
					delayed_print('<tr>
<!--th scope="row" style="display: none;">' . $id . '</th-->
');
//<td>' . strftime($format, $starttime) . '</td>
//<td>' . strftime($format, $endtime) . '</td>
					if($timeleft > 0) {
						delayed_print('<td class="auctiontimer' . $id . '" style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span></td>
');
					} else {
						delayed_print('<td style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span>ended</td>
');
					}
					delayed_print('<td>' . $accountname . '&nbsp;(' . get_account_reputation(xml_enc($accountname), $accounts) . ')</td>
<td class="reward">' . $last_bid . '</td>
<td class="reward">' . $buyout . '</td>
<td>' . format_for_printing($description, $access_credentials, $accountname, xml_dec($auctions->_('acceptedbid_bidder', $auction_you_issued)), $accounts) . '</td>
<td>' . $actions_string . '</td>
</tr>'); // might want to round instead of ceil at least in cases where the amount of currency being considered is less than 1 (which may be few)
				}
			}
		}
		delayed_print('</table>
</section>
');
		
		// tribunal
		delayed_print('<section class="tabbertab">
<h2>tribunal</h2>
<h3>reported bounties</h3>
<table id="reported_bounties_table" border="1" class="DataTable" style="width: 100%;">
<thead>
<tr>
<!--th scope="col" style="display: none;">id</th-->
');
//<th scope="col">start date</th>
//<th scope="col">end date</th>
delayed_print('<th scope="col">time left</th>
<th scope="col">bounty offerer</th>
<th scope="col">accepted by</th>
<th scope="col">reward</th>
<th scope="col">description</th>
<th scope="col">completing details</th>
<th scope="col">reporting reason</th>
<th scope="col">actions</th>
</tr>
</thead>
');
		//delayed_print('$bounties: ');var_dump($bounties);
		$available_bounties = $bounties->_('bounties_bounty'); // have to be careful not to get contextual results since we are doing this for each tab set
		if(is_array($available_bounties) && sizeof($available_bounties) > 0) {
			foreach($available_bounties as $available_bounty) {
				//$bounty_string = $bounties->generate_code_from_LOM($available_bounty);
				//if(strpos($bounty_string, '<acceptedcompleter>') === false && strpos($bounty_string, '<reported>') !== false) {
				if($bounties->_('acceptedcompleter', $available_bounty) === false && $bounties->_('reported', $available_bounty) !== false) {
					$id = $bounties->_('id', $available_bounty);
					$reward = $bounties->_('reward', $available_bounty);
					$starttime = $bounties->_('starttime', $available_bounty);
					$endtime = $bounties->_('endtime', $available_bounty);
					//print('$endtime - time(): ');var_dump($endtime - time());
					$timeleft = $endtime - time();
					if($timeleft > 0) {
						delayed_print(countdown_timer_script($id, $timeleft, 'bounty'));
					}
					$bounty_offerer = $bounties->_('accountname', $available_bounty);
					$description = $bounties->_('description', $available_bounty);
					$acceptednames = $bounties->_('acceptedname', $available_bounty);
					$result_array = accepted_string($bounties, $available_bounty);
					$bounties = $result_array[0];
					$accepted_string = $result_array[1];
					$result_array = completion_details_to_string($bounties, $available_bounty, $access_credentials, $bounty_offerer, $accounts);
					$bounties = $result_array[0];
					$completion_details_string = $result_array[1];
					$result_array = bounty_reported_string($bounties, $available_bounty);
					$bounties = $result_array[0];
					$bounty_reported_string = $result_array[1];
					if($bounties->_('reporter', $available_bounty) === xml_enc($accountname)) {
						$reported_bounty_actions_string = 'You reported this bounty.';
					} elseif($bounties->_('voter=' . $bounties->enc(xml_enc($accountname)), $available_bounty) !== false) {
						$reported_bounty_actions_string = 'You ' . $bounties->_('choice', '.vote_voter=' . $bounties->enc(xml_enc($accountname))) . 'd with this report.';
					} else {
						$reported_bounty_actions_string = reported_bounty_actions_string($id, $access_credentials, $tab_settings);
					}
					delayed_print('<tr>
<!--th scope="row" style="display: none;">' . $id . '</th-->
');
//<td>' . strftime($format, $starttime) . '</td>
//<td>' . strftime($format, $endtime) . '</td>
					if($timeleft > 0) {
						delayed_print('<td class="bountytimer' . $id . '" style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span></td>
	');
					} else {
						delayed_print('<td style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span>ended</td>
	');
					}
					delayed_print('<td>' . $bounty_offerer . '&nbsp;(' . get_account_reputation($bounty_offerer, $accounts) . ')</td>
<td>' . $accepted_string . '</td>
<td class="reward">' . $reward . '</td>
<td>' . format_for_printing($description, $access_credentials, xml_dec($bounty_offerer), xml_dec($bounties->_('acceptedcompleter', $available_bounty)), $accounts) . '</td>
<td>' . $completion_details_string . '</td>
<td>' . $bounty_reported_string . '</td>
<td>' . $reported_bounty_actions_string . '</td>
</tr>');
				}
			}
		}
		delayed_print('</table>
');		
		delayed_print('<h3>reported auctions</h3>
<table id="reported_auctions_table" border="1" class="DataTable" style="width: 100%;">
<thead>
<tr>
<!--th scope="col" style="display: none;">id</th-->
');
//<th scope="col">start date</th>
//<th scope="col">end date</th>
delayed_print('<th scope="col">time left</th>
<th scope="col">auction offerer</th>
');
//<th scope="col">bidders</th> // breach of privacy?
delayed_print('<th scope="col">current bid</th>
<th scope="col">buyout</th>
<th scope="col">description</th>
<th scope="col">reporting reason</th>
<th scope="col">actions</th>
</tr>
</thead>
');
		//delayed_print('$auctions: ');var_dump($auctions);
		$available_auctions = $auctions->_('auctions_auction'); // have to be careful not to get contextual results since we are doing this for each tab set
		if(is_array($available_auctions) && sizeof($available_auctions) > 0) {
			foreach($available_auctions as $available_auction) {
				//$auction_string = $auctions->generate_code_from_LOM($available_auction);
				//if(strpos($auction_string, '<acceptedbid>') === false && strpos($auction_string, '<reported>') !== false) {
				if($auctions->_('acceptedbid', $available_auction) === false && $auctions->_('reported', $available_auction) !== false) {
					$id = $auctions->_('id', $available_auction);
					$bids = $auctions->_('bid_amount', $available_auction);
					//delayed_print('$bids: ');var_dump($bids);exit(0);
					$startingbid = $auctions->_('startingbid', $available_auction);
					if(is_string($bids)) {
						$last_bid = $bids;
						$next_bid = ceil($last_bid * 1.10);
					} elseif($bids === false) {
						$last_bid = '';
						$next_bid = $startingbid;
					} else {
						foreach($bids as $last_bid_index => $last_bid) {  }
						$next_bid = ceil($last_bid * 1.10);
					}
					$buyout = $auctions->_('buyout', $available_auction);
					$starttime = $auctions->_('starttime', $available_auction);
					$endtime = $auctions->_('endtime', $available_auction);
					$timeleft = $endtime - time();
					if($timeleft > 0) {
						delayed_print(countdown_timer_script($id, $timeleft, 'auction'));
					}
					$auction_offerer = $auctions->_('accountname', $available_auction);
					$description = $auctions->_('description', $available_auction);
					$result_array = auction_reported_string($auctions, $available_auction);
					$auctions = $result_array[0];
					$auction_reported_string = $result_array[1];
					if($auctions->_('reporter', $available_auction) === xml_enc($accountname)) {
						$reported_auction_actions_string = 'You reported this auction.';
					} elseif($auctions->_('voter=' . $auctions->enc(xml_enc($accountname)), $available_auction) !== false) {
						$reported_auction_actions_string = 'You ' . $auctions->_('choice', '.vote_voter=' . $auctions->enc(xml_enc($accountname))) . 'd with this report.';
					} else {
						$reported_auction_actions_string = reported_auction_actions_string($id, $access_credentials, $tab_settings);
					}
					delayed_print('<tr>
<!--th scope="row" style="display: none;">' . $id . '</th-->
');
//<td>' . strftime($format, $starttime) . '</td>
//<td>' . strftime($format, $endtime) . '</td>
					if($timeleft > 0) {
						delayed_print('<td class="auctiontimer' . $id . '" style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span></td>
	');
					} else {
						delayed_print('<td style="text-align: right;"><span style="display: none;">' . ($endtime) . '</span>ended</td>
	');
					}
					delayed_print('<td>' . $auction_offerer . '&nbsp;(' . get_account_reputation($auction_offerer, $accounts) . ')</td>
<td class="reward">' . $last_bid . '</td>
<td class="reward">' . $buyout . '</td>
<td>' . format_for_printing($description, $access_credentials, xml_dec($auction_offerer), xml_dec($auctions->_('acceptedbid_bidder', $available_auction)), $accounts) . '</td>
<td>' . $auction_reported_string . '</td>
<td>' . $reported_auction_actions_string . '</td>
</tr>'); // might want to round instead of ceil at least in cases where the amount of currency being considered is less than 1 (which may be few)
				}
			}
		}
		delayed_print('</table>		
</section>
');
		
		delayed_print('</div>
');
	$tab_set_counter = 0;
	while($tab_set_counter < 2) {
		if($tab_set_counter === 0) {
			$tab_settings_counter = 6;
		} else {
			$tab_settings_counter = 13;
		}
		$tabset_string = str_replace('<div class="tabber"', '<div id="tabset' . ($tab_set_counter + 1) . '" class="tabber"', $GLOBALS['delayed_print_string']);
		preg_match_all('/ class="tabbertab"/is', $tabset_string, $tabber_tabs, PREG_OFFSET_CAPTURE);
		$counter58 = sizeof($tabber_tabs[0]) - 1;
		//print('$counter58: ');var_dump($counter58);exit(0);
		while($counter58 > -1) {
			if($tab_settings[$tab_settings_counter] === '1') {
				$tabset_string = substr($tabset_string, 0, $tabber_tabs[0][$counter58][1]) . ' class="tabbertab tabbertabdefault"' . substr($tabset_string, $tabber_tabs[0][$counter58][1] + strlen(' class="tabbertab"'));
			}// else {
			//	$tabset_string = substr($tabset_string, 0, $tabber_tabs[0][$counter58][1]) . ' class="tabbertab"' . substr($tabset_string, $tabber_tabs[0][$counter58][1] + strlen(' class="tabbertab"'));
			//}
			$tab_settings_counter--;
			$counter58--;
		}
		print($tabset_string);
		$tab_set_counter++;
	}
	$GLOBALS['delayed_print_string'] = '';
	//clean_up($auctions); // could be run separately from the user interface
	// very important to save the LOMs after all these contextual changes ;p
	$accounts->save_LOM_to_file('accounts.xml');
	$auctions->save_LOM_to_file('auctions.xml');
	$bounties->save_LOM_to_file('bounties.xml');
	$completedauctions->save_LOM_to_file('completedauctions.xml');
	$expiredauctions->save_LOM_to_file('expiredauctions.xml');
	$completedbounties->save_LOM_to_file('completedbounties.xml');
	$expiredbounties->save_LOM_to_file('expiredbounties.xml');
	//dump_total_time_taken();
}
?>

</body>
</html>