<?php

/** deps : php5 php5-curl php5-mysql **/

include 'config.php';

class kipptBackup {
        var $userName;
        var $apiToken;

        var $dbName;
        var $dbUser;
        var $dbPass;

        public function setUsername($userName) {
                $this->userName = $userName;
        }

        public function setApiToken($apiToken) {
                $this->apiToken = $apiToken;
        }

        public function setDatabaseCredentials($dbName, $dbUser, $dbPass) {
                $this->dbName = $dbName;
                $this->dbUser = $dbUser;
                $this->dbPass = $dbPass;
        }

        public function createBackup() {
                $this->connectToDatabase();

                /** get number of clips **/
                $ch = curl_init('https://kippt.com/api/clips/?limit=1&offset=0');

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt(
                        $ch,
                        CURLOPT_HTTPHEADER,
                        array(
                                'X-Kippt-Username: ' . $this->userName,
                                'X-Kippt-API-Token: ' . $this->apiToken
                        )
                );

                $apiResult = curl_exec($ch);
                curl_close($ch);

                // decode the JSON data
                $apiData = json_decode($apiResult);

                // See if the response contains some kind of a message - usually a hint that something
                // went horribly wrong
                if (isset ($apiData->message)) {
                        echo 'Something went wrong:' . "\n";
                        exit($apiData->message . "\n");
                }

                // $count = number of clips
                $count = $apiData->meta->total_count;

		/** starting fetching clips **/
                echo 'Backing up kippt.com ' . $count . ' bookmarks for user ' . $this->userName;
                echo "\n";

		/** API limit is 200 **/
                $limit = 100;
                $offset = 0;

		/** stats vars **/
                global $stat_up2date;
                global $stat_updated;
                global $stat_new;
                $stat_up2date = 0;
                $stat_updated = 0;
                $stat_new = 0;
                
                // loop through clips
                while ($offset <= $count) {
		
			/** progress displayed in % **/        
			$progresspc = round(($offset / $count * 100), 2);

                        // display progress in %
                        echo "Progress : " . $progresspc . "%\n";

                        $ch = curl_init('https://kippt.com/api/clips/?limit=' . $limit . '&offset=' . $offset);

                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_setopt(
                                $ch,
                                CURLOPT_HTTPHEADER,
                                array(
                                        'X-Kippt-Username: ' . $this->userName,
                                        'X-Kippt-API-Token: ' . $this->apiToken
                                )
                        );

                        $apiResult = curl_exec($ch);
                        curl_close($ch);

                        // decode the JSON data
                        $apiData = json_decode($apiResult);

                        // See if the response contains some kind of a message - usually a hint that something
                        // went horribly wrong
                        if (isset ($apiData->message)) {
                                echo 'Something went wrong:' . "\n";
                                exit($apiData->message . "\n");
                        }

                        if (isset($apiData->objects)) {
                                foreach ($apiData->objects as $clip) {
                                        $this->compareOrImportClip($clip);
                                }
                        }

                        $offset=$offset+$limit;
                        if ($offset >= $count) {
                                echo "Progress : 100%\n\n";
				echo "Clips stats\n";
				echo "-----------\n";
                                echo "New ... : " . $stat_new . "\n";
                                echo "Updated : " . $stat_updated . "\n";
                                echo "Up2date : " . $stat_up2date . "\n\n";
                        }
                }

        }

        private function compareOrImportClip($clip) {
                // echo 'Checking clip with ID=' . $clip->id ;

                $dbResult = mysql_query('SELECT id,updated FROM clips WHERE id=' . $clip->id);
                if (mysql_num_rows($dbResult) == 1) {
                        // echo "\n" . 'Clip exists already... ';
                        global $stat_up2date;
                        $stat_up2date=$stat_up2date+1;

                        $row = mysql_fetch_assoc($dbResult);
                        if ($row['updated'] < $clip->updated) {
                                global $stat_updated;
                                $stat_updated=$stat_updated+1;
                                echo '--> Updating clip ID ' . $clip->id . "\n";
                                $this->updateExistingClip($clip);
                        }

                } else {
                        echo '--> Inserting clip ID ' . $clip->id . "\n";
                        global $stat_new;
                        $stat_new=$stat_new+1;
                        $this->insertNewClip($clip);
                }

        }

        private function updateExistingClip($clip) {
                mysql_query('DELETE from clips WHERE id=' . $clip->id . ' LIMIT 1;');
                $this->insertNewClip($clip);
        }

        private function insertNewClip($clip) {
                $importResult = mysql_query('INSERT into clips SET id="' . $clip->id .
                                '", title="' . addslashes($clip->title) .'"' .
                                ', url="' . $clip->url .'"' .
                                ', list="' . $clip->list .'"' .
                                ', notes="' . addslashes($clip->notes) .'"' .
                                ', url_domain="' . $clip->url_domain .'"' .
                                ', created="' . $clip->created .'"' .
                                ', updated="' . $clip->updated .'"' .
                                ', resource_uri="' . $clip->resource_uri .'"'
                );

                if (!$importResult) {
                        echo 'INSERT error: ' . mysql_error() . "\n\n";
                }
        }

        private function connectToDatabase() {
                mysql_connect(
                        'localhost',
                        $this->dbUser,
                        $this->dbPass
                );
                mysql_select_db($this->dbName);
                mysql_query('SET NAMES utf8;');
        }

}

$backup = new kipptBackup();

// modify to suit your needs
$backup->setUsername("$kippt_username");
$backup->setApiToken("$kippt_apitoken");
$backup->setDatabaseCredentials(
        "$mysql_schema",
        "$mysql_username",
        "$mysql_password"
);

$backup->createBackup();

?>

