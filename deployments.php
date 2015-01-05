<?php
/*
 *	GitHub & Bitbucket Deployment Sample Script
 *	Originally found at
 *	http://brandonsummers.name/blog/2012/02/10/using-bitbucket-for-automated-deployments/
 *  http://jonathannicol.com/blog/2013/11/19/automated-git-deployments-from-bitbucket/
 *  
 *  We assume you did a 'git clone -mirror' to your locao repo directory,
 *  And then, 'GIT_WORK_TREE=[www directory] git checkout -f [your desired branch]'  
 *  
  */

/**
* The Full Server Path to git repository and web location.
* Can be either relative or absolute path
*/
$git_serverpath = '/path/to/git/repo';
$www_serverpath = '/path/to/git/www';

/**
* The Secret Key
*/
$secret_key = 'EnterYourSecretKeyHere';

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
* @link    http://php.net/manual/en/timezones.php
*/
date_default_timezone_set('Asia/Tokyo');


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
  private $_log = 'deployments.log';

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
  * The path to git
  * 
  * @var string
  */
  private $_git_bin_path = 'git';

  /**
  * The directory where your website and git repository are located,
  * can be relative or absolute path
  * 
  * @var string
  */
  private $_git_dir;
  private $_www_dir;

  /**
  * Sets up defaults.
  * 
  * @param  string  $directory  Directory where your website is located
  * @param  array   $data       Information about the deployment
  */
  public function __construct($git_dir, $www_dir, $options = array())
  {
      // Determine the directory path
      $this->_git_dir = realpath($git_dir).DIRECTORY_SEPARATOR;
      $this->_www_dir = realpath($www_dir).DIRECTORY_SEPARATOR;

      $available_options = array('log', 'date_format', 'branch', 'remote', 'git_bin_path');

      foreach ($options as $option => $value)
      {
          if (in_array($option, $available_options))
          {
              $this->{'_'.$option} = $value;
          }
      }

      $this->log('Attempting deployment...');
  }

  /**
  * Writes a message to the log file.
  * 
  * @param  string  $message  The message to write
  * @param  string  $type     The type of log message (e.g. INFO, DEBUG, ERROR, etc.)
  */
  public function log($message, $type = 'INFO')
  {
      if ($this->_log)
      {
          // Set the name of the log file
          $filename = $this->_log;

          if ( ! file_exists($filename))
          {
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
      try
      {
          // Discard any changes to tracked files since our last deploy
          exec('cd ' . $this->_git_dir . ' && ' . $git_bin_path . ' reset --hard HEAD', $output);
          $this->log('Reseting repository... '.implode(' ', $output));

          // Update the local repository
          exec('cd ' . $this->_git_dir . ' && ' . $git_bin_path . ' fetch ', $output);
          $this->log('Pulling in changes... '.implode(' ', $output));

          // Secure the .git directory
          exec('cd ' . $this->_git_dir . ' && chmod -R og-rx .git');
          $this->log('Securing .git directory... ');

		  exec('cd ' . $this->_git_dir . ' && GIT_WORK_TREE=' . $this->_www_dir . ' ' . $git_bin_path  . ' checkout -f');

          if (is_callable($this->post_deploy))
          {
              call_user_func($this->post_deploy, $this->_data);
          }

          $this->log('Deployment successful.');
      }
      catch (Exception $e)
      {
          $this->log($e, 'ERROR');
      }
  }

}

$deploy = new Deploy($git_serverpath, $www_serverpath);

/*
$deploy->post_deploy = function() use ($deploy) {
  // hit the wp-admin page to update any db changes
   exec('curl http://example.com/wp-admin/upgrade.php?step=upgrade_db');
   $deploy->log('Updating wordpress database... ');
};
*/

if ($_GET[key] === $secret_key)  {
	$deploy->execute();
}
?>