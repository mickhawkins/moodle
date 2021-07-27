<?php

/**
 * Test script to redirect to a similar, remote test script.
 *
 * This script forms part of filelib testing.
 * See test_curl_redirect_security for an example.
 *
 */

// Number of redirect steps to take.
$steps = !empty($_GET['steps']) ? (int)$_GET['steps'] : 0;
// The URL of the remote page.
$remotehost = $_GET['remotehost'] ?? '';
// Count of redirects made so far.
$count = !empty($_GET['count']) ? (int) $_GET['count'] : 0;

// Only redirect again if we know the remote host and haven't reached the required steps.
if ($remotehost && $count < $steps) {

	// There is no localstep or localhost required anymore, now we have reached this file.
	$params = implode('&', [
		"steps={$steps}",
		"remotehost={$remotehost}",
		"count=" . $count + 1,
	]);

	header("Location: {$remotehost}?{$params}");
	die();
}

echo 'done';
die();
