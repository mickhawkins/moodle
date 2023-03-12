<?php

//namespace core\moodlenet;
include('../config.php');
// require_once('/var/www/html/stable_master/lib/guzzlehttp/guzzle/src/ClientInterface.php');
// require_once('/var/www/html/stable_master/lib/guzzlehttp/guzzle/src/ClientTrait.php');
// require_once('/var/www/html/stable_master/lib/guzzlehttp/guzzle/src/Client.php');
// require_once('/var/www/html/stable_master/lib/classes/http_client.php');

//use GuzzleHttp\Middleware;

$test = new testclass();
$test->run();

class testclass {

    public static function run() {

/*******START - placeholder variables ********/
        $httpclient = new \core\http_client();
        //$apiurl = 'http://localhost:5000/.pkg/@moodlenet/ed-resource/basic/v1/create'; // Use this if listening with netcat on 5000 to see request
        $apiurl = 'https://moodlenet.test/.pkg/@moodlenet/ed-resource/basic/v1/create';

        $accesstoken = 't0k3n'; //Hardcoded in mock
        $courseid = 17;
        $cmid = 502;
        $userid = 2;
        $resourceinfo = new \core\moodlenet\activity_resource($courseid, $cmid, $userid);

        $resourcedata = [
            'filecontents' => 'TODO contents',
            'filename' => 'TODO filename',
            'filesize' => '1',
            'filehash' => 'TODO hash',
            'contenttype' => 'Application/zip',
        ];

/*******END - placeholder variables ********/

/*******START - REQUEST ********/

        $requestdata = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accesstoken,
            ],
            'multipart' => [
                [
                    'name' => 'metadata',
                    'contents' => json_encode([
                        'name' => $resourceinfo->get_name(),
                        'description' => $resourceinfo->get_description(),
                    ]),
                    'headers' => [
                        'Content-Disposition' => 'form-data; name="."',
                    ],
                ],
                [
                    'name' => 'filecontents',
                    'contents' => $resourcedata['filecontents'],
                    'headers' => [
                        'Content-Disposition' => 'form-data; name=".resource"; filename="'. $resourcedata['filename'] . '"',
                        'Content-Type' => $resourcedata['contenttype'],
                        'Content-Transfer-Encoding' => 'binary',
                    ],
                ],
            ],
        ];

echo '<pre>' . var_export($requestdata, true) . '</pre><br>###################################<br>';
        try {
            $response = $httpclient->request('POST', $apiurl, $requestdata);
            //$responsecode = $response->getStatusCode();
            echo "Response code: {$response->getStatusCode()}";
            echo '<pre>' . var_export($response, true) . '</pre>';
        } catch(\Exception $e) {
            // Something went wrong - set a known fail response.
            //$responsecode = 401;
            echo "<pre>" . var_export($e, true) . "</pre>";
        };

/*******END - REQUEST ********/
    }
}
