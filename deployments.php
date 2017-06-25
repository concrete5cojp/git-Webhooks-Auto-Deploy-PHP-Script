<?php
/*
 *	GitHub & Bitbucket Deployment Sample Script
 *	Originally found at
 *	http://brandonsummers.name/blog/2012/02/10/using-bitbucket-for-automated-deployments/
 *
 */

/*
 *    Webhook Sample
 *        For BitBucket, use 'POST HOOK'
 *        For GitHub, use 'Webhooks'
 *    URL Format
 *        https://[Basic Auth ID]:[Basic Auth Pass]@example.com/deployments.php?key=EnterYourSecretKeyHere
 *        (https access & Basic Auth makes it a bit more secure if your server supports it)
 */

/**
* The TimeZone format used for logging.
* @var Timezone
* @link    http://php.net/manual/en/timezones.php
*/
date_default_timezone_set('Asia/Tokyo');

/**
* The Secret Key
* @var string
*/
$secret_key = 'EnterYourSecretKeyHere';

/**
* The Options
* Only 'directory' is required.
* @var array
*/
$options = array(
    'directory'     => '/path/to/git/repo',
    'work_dir'      => '/path/to/work/dir',
    'log'           => 'deploy_log_filename.log',
    'branch'        => 'master',
    'remote'        => 'origin',
    'date_format'   => 'Y-m-d H:i:sP',
    'syncSubmodule' => false,
)

if ($_GET['key'] === $secret_key)  {
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
    * The name of the branch to pull from.
    * 
    * @var string
    */
    private $_branch = 'master';
    
    /**
    * The name of the remote to pull from.
    * 
    * @var string
    */
    private $_remote = 'origin';
    
    /**
    * If the git contains submodule and want this git auto deploy to sync submodule at the same time.
    * 
    * @var boolean
    */
    private $_syncSubmodule = false;
    
    /**
    * A callback function to call after the deploy has finished.
    * 
    * @var callback
    */
    public $post_deploy;
    
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
    private $_workdirectory;

    /**
    * Sets up defaults.
    * 
    * @param  string  $directory  Directory where your website is located
    * @param  array   $options    Options for default settings
    */
    public function __construct($options = array())
    {
    
        $available_options = array('directory', 'work_dir', 'log', 'date_format', 'branch', 'remote', 'syncSubmodule');
    
        foreach ($options as $option => $value){
            if (in_array($option, $available_options)) {
                $this->{'_'.$option} = $value;
                if ($option == 'directory' || $option == 'work_dir') {
                    // Determine the directory path
                    $this->{'_'.$option} = realpath($value).DIRECTORY_SEPARATOR;
                }
            }
        }
        if (empty($_workdirectory)){
            $_workdirectory = $_directory;
        }
    
        $this->log('Attempting deployment...');
        $this->log('Directory:' . $this->_directory);
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
    * Executes the necessary commands to deploy the website.
    */
    public function execute()
    {
        try {
            // Git Submodule - Measure the execution time
            $strtedAt = microtime(true);
            
            // Discard any changes to tracked files since our last deploy
            exec('git --git-dir='.$this->_directory.'/.git --work-tree='.$this->_workdirectory . ' reset --hard HEAD', $output);
            if (is_array($output)) {
                $output = implode(' ', $output);
            }
            $this->log('Reseting repository... '.$output);
            
            // Update the local repository
            $output = '';
            exec('git --git-dir='.$this->_directory.'/.git --work-tree='.$this->_workdirectory . ' pull', $output);
            if (is_array($output)) {
                $output = implode(' ', $output);
            }
            $this->log('Pulling in changes... '.$output);
            
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
            exec('git --git-dir='.$this->_directory.'/.git --work-tree='.$this->_workdirectory . ' submodule update --init --recursive --remote', $output);
            if (is_array($output)) {
                $output = implode(' ', $output);
            }
                $this->log('Updating submodules...'.$output);
            }
            
            // Secure the .git directory
            exec('chmod -R og-rx . ' .$this->_directory .'/.git');
            $this->log('Securing .git directory... ');
            
            if (is_callable($this->post_deploy)) {
                  call_user_func($this->post_deploy, $this->_data);
            }
            
            $this->log('Deployment successful.');
        }
        catch (Exception $e) {
            $this->log($e, 'ERROR');
        }
    }
    
}
