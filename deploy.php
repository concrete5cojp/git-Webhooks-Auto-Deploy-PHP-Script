<?php
/**
 * [Job] git Webhooks Auto Deployment PHP Sample Script
 *
 * PHP script to work with webhook to deploy your git repo
 * Read https://github.com/katzueno/git-Webhooks-Auto-Deploy-PHP-Script for the detail
 *
 * @access public
 * @author Katz Ueno <katzueno.com>
 * @author Biplob Hossain <biplob.me>
 * @copyright Katz Ueno
 * @category Deployment
 * @version 4.2.0 - Docker Support
 */

/**
* The TimeZone format used for logging.
* @var Timezone
* @link    http://php.net/manual/en/timezones.php
*/
date_default_timezone_set('Asia/Tokyo');

/**
 * The Secret Key so that it's a bit more secure to run this script.
 * e.g.) https://example.com/deployments.php?key=EnterYourSecretKeyHere
 *
 * @var string
 */
$secret_key = 'EnterYourSecretKeyHere';

/**
* The Options
* Only 'directory' is required.
* @var array
*/
$options = array(
    'directory'     => '/path/to/git/repo', // Enter your server's git repo location
    'work_dir'      => '/path/to/www',  // Enter your server's work directory. If you don't separate git and work directories, please leave it empty or false.
    'log'           => 'deploy_log_filename.log', // relative or absolute path where you save log file. Set it to false without quotation mark if you don't need to save log file.
    'branch'        => 'master', // Indicate which branch you want to checkout
    'remote'        => 'origin', // Indicate which remote repo you want to fetch
    'date_format'   => 'Y-m-d H:i:sP',  // Indicate date format of your log file
    'syncSubmodule' => false, // If your repo has submodule, set it true. (haven't tested it if this actually works)
    'reset'         => false, // If you want to git reset --hard every time you deploy, please set it true
    'git_bin_path'  => 'git',
    // Docker-related options
    'docker_enabled' => false, // Enable Docker operations after deployment
    'docker_compose_profile' => 'dev', // Docker Compose profile to use (e.g., dev or prod)
);

/**
 * Main Section: No need to modify below this line
 */
if (isset($_GET['key']) && hash_equals($secret_key, (string)$_GET['key']))  {
    $deploy = new Deploy($options);
    $deploy->execute();
    /*
    $deploy->post_deploy = function() use ($deploy) {
      // hit the wp-admin page to update any db changes
       exec('curl http://example.com/wp-admin/upgrade.php?step=upgrade_db');
       $deploy->log('Updating wordpress database... ');
    };
    */
}

class Deploy {

    /**
    * A callback function to call after the deploy has finished.
    * 
    * @var callback
    */
    public $post_deploy;
    
    /**
    * The name of the file that will be used for logging deployments. Set to 
    * FALSE to disable logging.
    * 
    * @var string
    */
    private $_log = 'deploy.log';
    
    /**
    * The timestamp format used for logging.
    * 
    * @link    http://www.php.net/manual/en/function.date.php
    * @var     string
    */
    private $_date_format = 'Y-m-d H:i:sP';
    
    /**
    * The path to git
    * 
    * @var string
    */
    private $_git_bin_path = 'git';

    /**
    * The directory where your git repository is located, can be 
    * a relative or absolute path from this PHP script on server.
    * 
    * @var string
    */
    private $_directory;

    /**
    * The directory where your git work directory is located, can be 
    * a relative or absolute path from this PHP script on server.
    * 
    * @var string
    */
    private $_work_dir;

    /**
     * Determine if it will execute to git checkout to work directory,
     * or git pull.
     *
     * @var boolean
     */
    private $_topull = false;

    /**
     * Enable Docker operations after git pull
     *
     * @var boolean
     */
    private $_docker_enabled = false;

    /**
     * Docker Compose profile to use
     *
     * @var string
     */
    private $_docker_compose_profile = 'dev';

    /**
    * Sets up defaults.
    * 
    * @param  array   $option       Information about the deployment
    */
    public function __construct($options = array())
    {

        $available_options = array('directory', 'work_dir', 'log', 'date_format', 'branch', 'remote', 'syncSubmodule', 'reset', 'git_bin_path', 'docker_enabled', 'docker_compose_profile');
    
        foreach ($options as $option => $value){
            if (in_array($option, $available_options)) {
                $this->{'_'.$option} = $value;
                if (($option == 'directory') || ($option == 'work_dir' && $value)) {
                    // Determine the directory path
                    $this->{'_'.$option} = realpath($value).DIRECTORY_SEPARATOR;
                }
            }
        }

        $this->_topull = false;
        if (empty($this->_work_dir) || ($this->_work_dir == $this->_directory)) {
            $this->_work_dir = $this->_directory;
            $this->_directory = $this->_directory . '.git';
            $this->_topull = true;
        }
    
        $this->log('Attempting deployment...');
        $this->log('Git Directory:' . $this->_directory);
        $this->log('Work Directory:' . $this->_work_dir);
        if ($this->_docker_enabled) {
            $this->log('Docker Mode: Enabled (Profile: ' . $this->_docker_compose_profile . ')');
        }
    }

    /**
    * Writes a message to the log file.
    * 
    * @param  string  $message  The message to write
    * @param  string  $type     The type of log message (e.g. INFO, DEBUG, ERROR, etc.)
    */
    public function log($message, $type = 'INFO')
    {
        if ($this->_log) {
            // Set the name of the log file
            $filename = $this->_log;

            if ( ! file_exists($filename)) {
                // Create the log file
                file_put_contents($filename, '');

                // Allow anyone to write to log files
                chmod($filename, 0666);
            }

            // Write the message into the log file
            // Format: time --- type: message
            file_put_contents($filename, date($this->_date_format).' --- '.$type.': '.$message.PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * Handle Docker operations after git deployment
     */
    private function dockerOperations()
    {
        if (!$this->_docker_enabled) {
            return;
        }

        try {
            $this->log('Starting Docker operations...');

            // Stop existing containers
            $this->log('Stopping existing Docker containers...');
            exec('cd ' . $this->_work_dir . ' && docker-compose down 2>&1', $output, $return_var);
            if ($return_var === 0) {
                $this->log('Docker containers stopped: ' . implode(' ', $output));
            } else {
                $this->log('Warning: Error stopping containers: ' . implode(' ', $output), 'WARN');
            }

            // Rebuild containers (no-cache to ensure fresh build)
            $this->log('Rebuilding Docker containers...');
            exec('cd ' . $this->_work_dir . ' && docker-compose --profile ' . $this->_docker_compose_profile . ' build --no-cache 2>&1', $output, $return_var);
            if ($return_var === 0) {
                $this->log('Docker containers rebuilt successfully');
            } else {
                throw new Exception('Docker build failed: ' . implode(' ', $output));
            }

            // Start containers
            $this->log('Starting Docker containers...');
            exec('cd ' . $this->_work_dir . ' && docker-compose --profile ' . $this->_docker_compose_profile . ' up -d 2>&1', $output, $return_var);
            if ($return_var === 0) {
                $this->log('Docker containers started successfully: ' . implode(' ', $output));
            } else {
                throw new Exception('Docker startup failed: ' . implode(' ', $output));
            }

            // Wait a moment for containers to stabilize
            sleep(5);

            // Check container status
            exec('cd ' . $this->_work_dir . ' && docker ps --format "table {{.Names}}\t{{.Status}}" 2>&1', $output, $return_var);
            if ($return_var === 0) {
                $this->log('Docker container status: ' . implode(' | ', $output));
            }

            $this->log('Docker operations completed successfully');

        } catch (Exception $e) {
            $this->log('Docker operations failed: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
    * Executes the necessary commands to deploy the website.
    */
    public function execute()
    {
        try {
            // Git Submodule - Measure the execution time
            $strtedAt = microtime(true);

            // Discard any changes to tracked files since our last deploy
            if ($this->_reset) {
                exec($this->_git_bin_path . ' --git-dir=' . $this->_directory . ' --work-tree=' . $this->_work_dir . ' reset --hard HEAD 2>&1', $output);
                if (is_array($output)) {
                    $output = implode(' ', $output);
                }
                $this->log('Reseting repository... '.$output);
            }

            // Update the local repository
            exec($this->_git_bin_path . ' --git-dir=' . $this->_directory . ' --work-tree=' . $this->_work_dir . ' fetch', $output, $return_var);
            if ($return_var === 0) {
                $this->log('Fetching changes... '.implode(' ', $output));
            } else {
                throw new Exception(implode(' ', $output));
            }

            // Checking out to web directory
            if ($this->_topull) {
                exec('cd ' . $this->_directory . ' && GIT_WORK_TREE=' . $this->_work_dir . ' ' . $this->_git_bin_path . ' pull 2>&1', $output, $return_var);
                if ($return_var === 0) {
                    $this->log('Pulling changes to directory... ' . implode(' ', $output));
                } else {
                    throw new Exception(implode(' ', $output));
                }
            } else {
                exec('cd ' . $this->_directory . ' && GIT_WORK_TREE=' . $this->_work_dir . ' ' . $this->_git_bin_path . ' checkout -f', $output, $return_var);
                if ($return_var === 0) {
                    $this->log('Checking out changes to www directory... ' . implode(' ', $output));
                } else {
                    throw new Exception(implode(' ', $output));
                }

            }

            if ($this->_syncSubmodule) {
                // Wait 2 seconds if main git pull takes less than 2 seconds.
                $endedAt = microtime(true);
                $mDuration = $endedAt - $strtedAt;
                if ($mDuration < 2) {
                    $this->log('Waiting for 2 seconds to execute git submodule update.');
                    sleep(2);
                }
                // Update the submodule
                $output = '';
                exec($this->_git_bin_path . ' --git-dir=' . $this->_directory . ' --work-tree=' . $this->_work_dir . ' submodule update --init --recursive --remote', $output);
                if (is_array($output)) {
                    $output = implode(' ', $output);
                }
                $this->log('Updating submodules...'.$output);
            }

            // Handle Docker operations if enabled
            if ($this->_docker_enabled) {
                $this->dockerOperations();
            }

            if (is_callable($this->post_deploy)) {
                call_user_func($this->post_deploy, $this->_data);
            }

            $this->log('Deployment successful.');
        } catch (Exception $e) {
            $this->log($e, 'ERROR');
        }
    }
}
