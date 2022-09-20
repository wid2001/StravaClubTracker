<?php

declare(strict_types=1);

use picasticks\Strava\Club;
use picasticks\Strava\ClubException;
//use Strava\API\Client;
//use Strava\API\Service\REST;
use picasticks\Strava\Client;
use picasticks\Strava\REST;
use Strava\API\OAuth;

require_once '../lib/vendor/autoload.php';

// Define list of Strava Club IDs to track
$clubs = array(
	620498,
);

// Set a TZ for date calculations
date_default_timezone_set('America/Boise');

// Set start and end date for tracking
$startDate = '2022-10-01';
$endDate   = '2022-10-31';

// Replace with your Strava API credentials and the URI of this script
$oauth = new OAuth([
	'clientId'     => 52223,
	'clientSecret' => '19a68300f850f5781b7a6aee1e6bfe487e157d11',
	'redirectUri'  => 'postgres://neeoqzxkzrekzh:6f3c224fa8ce616b976e8fe7e5127cd6da725e806e214c1ebc561ef1febecb4f@ec2-3-219-19-205.compute-1.amazonaws.com:5432/dblur7pcln8gr1'
]);

if (!isset($_GET['code'])) {
	echo '<p><a href="'.$oauth->getAuthorizationUrl([
		'scope' => [
			'read',
		]
	]).'">Connect to Strava API and and download updates</a><p>';
} else {
	$token = $oauth->getAccessToken('authorization_code', ['code' => $_GET['code']]);
	$adapter = new \GuzzleHttp\Client(['base_uri' => 'https://www.strava.com/api/v3/']);
	$service = new REST($token->getToken(), $adapter);

	$club = new Club(dirname(__DIR__).'/json');
	$club->setClient(new Client($service));

	// Uncomment to override library's default Strava API request limit (default is 100)
	//$club->requestLimit = 42;

	// Uncomment to set logger to null to skip logging
	//$club->logger = null;

	// Compute start/end timestamps from start/end dates. Set end date to no later than yesterday.
	$start = strtotime($startDate);
	$end = min(strtotime($endDate), (strtotime(date('Y-m-d')) - 86400));
	$club->log('Updating using date range '.date('Y-m-d', $start).' to '.date('Y-m-d', $end));

	// Download data from Strava. Only downloads when local files aren't already present.
	try {
		foreach ($clubs as $clubId) {
			// Get club info
			$club->downloadClub($clubId);

			// Get club activities for each day between $start and $end
			$club->downloadClubActivities($clubId, $start, $end);
		}
		$club->log(sprintf("Done! Made %s API requests to Strava", $club->getRequestCount()));
	} catch (ClubException $e) {
		$club->log('Configured API limit reached: '.$e->getMessage());
	}
}

# vim: tabstop=4:shiftwidth=4:noexpandtab
