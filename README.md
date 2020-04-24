Git Webhooks Auto Deployment PHP Sample Script & Shell Script
===================================

This is a sample PHP & shell script to auto deploy PHP script using GitHub, Bitbucket and Backlog hook function.

Please read the comments inside of deployments.php for the option and how to set-it up.

This deployment script requires certain level of git and server knowledge.

This script is intended for single server use.

If you want to set-up multiple git deployment environments within a AWS Amazon Linux 2 Server. I've also added `setting-vhost-route53.sh` to automate setting it up within a min.

- creating virtual host directory
- git clone it to directory,
- Setup Nginx config & restart
- Generate web hook URL
- Registering subdomain to a Route53 hosted zone.

# How to set up the git script `deploy.php`

How to set-up `deploy.php`.

## 1. Set up git

Prepare your git repo

## 2. Set-up server

It is highly recommended to prepare two different domain or subdomains within the same server.
Set-up where you set git deployment script and public area where your actual git deployment.

You must make sure git deploy script is protected with basic auth and configured as SSL.

There is an option to setup tailwind CSS. You must install npm before running the script if you would like to use the option.

Obtain all necessary information such as server paths.

## 3. Git clone to the server

### Regular way

- Login via SSH
- cd to the path
- `git clone [GIT PATH]`
    - Make sure you git clone as the same user as web server. If you're using shared host, you may not have to worry about.
    - e.g.,) `sudo -u (nginx|apache) git clong [GIT PATH]`
- Make sure that /.git directory is NOT publicly visible to the web.
    - Apache: add this line to .htaccess: `RedirectMatch 404 /\.git` (mod_rewrite is required)
    - Nginx: add this line to your Nginx config `location ~ /\.git { return 404; }`

### Separate Git Directory and Work Directory (more secure)

Is is very secure way of place git repository outside of publicly visible www root.

- Login via SSH
- cd to the path
- `git clone --mirror [GIT PATH]` to the directory
- `GIT_WORK_TREE=[www_path] git checkout -f [your desired branch]`

## 3. Set your config & upload the file

- Set all necessary config setting inside of deployement.php
    - Read carefully from line 1-45. You won't need to change below `Main Section`
- Upload your `deploy.php` to where you stage your script
    - Make sure to upload in secure area.
    - You may rename filename.

## 4. Set-up a webhook and test drive

Your web hook URL will be like this.
Set it up as webhook of GitHub, Bitbucket, Gitlab or whatever other git services which supports webhook.

```
https://[Basic Auth ID]:[Basic Auth Pass]@example.com/deploy.php?key=YourSecretKeyHere
```
and enjoy the rest of auto deployment.

# How to set-up & use setting-vhost-route53.sh

I initially made this script to set-up coding preview server.

- Amazon Linux 2 (CentOS7) server with Nginx & php-fpm installed
- Have main domain.
- Automate subdomain creation, Nginx and DNS registration.

`setting-vhost-route53-backlog.sh` is alternative version modified for [Backlog](https://backlog.com/). I don't have readme ready. Please check the shell script to understand the variables.

Since Route53 requires a json file to set the domain, this shell script generates `route53.json` file.

## How to use

### Step 1: Assign necessary permission to git repo

Assign a necessary permission to your GitHub, GitLab, Bitbucket, Backlog or any other git service.
So that server's nginx user can properly git clone, git fetch from remote git repo.

### Step 2: login to server and run a command

Login to the server via SSH, then run the following command.

```
$ sh setting-vhost-route53.sh [SUBDOMAIN] [GIT CLONE URL] [BRANCH NAME] [BASIC AUTH USERNAME] [BASIC AUTH PASSWORD] [DEPLOY KEY]
```

(If you've changed the filename of shell script as I advised, you must change the command accordingly.)

$   | Option Name     | Description | Example
----|-----------------|-------------|--------
$1  | [SUBDOMAIN]     | Set your desired subdomain   | `subdomain`
$2  | [Git Clone URL] | Enter URL to git clone | `git@github.com:katzueno/git-Webhooks-Auto-Deploy-PHP-Script.git`
$3  | [BRANCH NAME]   | Branch you want to check out initially | `master`
$4  | [BASIC AUTH USERNAME] | You deside the Basic Auth ID | `username`
$5  | [BASIC AUTH PASSWORD] | Generate password of Basic Auth | `password`
$6  | [DEPLOY KEY]    | Generate random key as additional security measure of deployment | `1234567890abcdefABCDEF`
$7  | [NPM OPTION]    | Setup and run the tailwind build post-merge | `tailwind`

```
Example
$ sh setting-vhost-route53.sh subdomain git@github.com:katzueno/git-Webhooks-Auto-Deploy-PHP-Script.git master username 1234567890abcdefABCDEF tailwind
```

### Step 3: Test URL & register it to your git webhook.

- If everything goes successfully, it will print out webhook URL.
- Take the webhook URL, open it in your browser. Check the log if the git deployment went successfully.
- Register the URL to your GitHub, Bitbucket, GitLab, Backlog and other services as a webhook URL.
- Copy the wiki at the end, and distribute it to yout team.

## How to set-it up


### STEP 1: Get your Route53 Zone ID

- If needed, create a Route53 zone with your main domain.
- Obtain Route53 Zone ID of your main domain.


### STEP 2: Create an IAM Role or IAM user

Create an IAM policy and place your zone ID under `Resources`. Assign to an new IAM role or IAM user.

```
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "route53:GetHostedZone",
                "route53:ListHostedZonesByName",
                "route53:ChangeResourceRecordSets",
                "route53:CreateHealthCheck",
                "route53:GetHealthCheck",
                "route53:DeleteHealthCheck",
                "route53:UpdateHealthCheck",
                "servicediscovery:Get*",
                "servicediscovery:List*",
                "servicediscovery:RegisterInstance",
                "servicediscovery:DeregisterInstance"
            ],
            "Resource": [
                "arn:aws:route53:::hostedzone/XXXXXXXXX"
            ]
        }
    ]
}
```

I've copied `AmazonRoute53AutoNamingRegistrantAccess` IAM policy and add Resource restriction to a particular hostedzone. If you want to tighten the security more. You may remove some allowed actions.


### STEP 3: Launch an instance and assign IAM role or IAM user to an EC Instance

- Launch an EC2 instance with Amazon Linux 2
    - I recommend to assign an Elastic IP.
- Assign the previously made IAM role or IAM user to the EC Instance.


### STEP 4: Setup your Amazon Linux 2 (CentOS)

- Set up your Amazon Linux 2 instance using Nginx, and PHP7.3.
    - Create a main domain virtual host.
        - Create a main domain vhost file
        - Create a log directory `/var/log/gitdeploy` & make it writable by nginx user.
        - Create a Basic Auth ID and Password.
    - Recommend to use my [Ansible script](https://github.com/katzueno/ansible-c5-ma) which does all job that I describe.
    - Create a SSH public key for Nginx user `sudo -u nginx ssh-keygen -t rsa -b 4096 -C nginx@example.com`
    - Get nginx user's SSH public key and register it to your GitHub / Bitbucket / Backlog user account.


### STEP 5: Setup SSL Certificate & initial vhost template file

- Set-up wildcard SSL certificate such as Let's Encrypt.
- Save common SSL config as `/etc/nginx/default.d/ssl.conf`.
- Prepare `server/00000000_vhost_example.com.conf.template` file.
    - Modify `example.com` to your main domain (file namme and its content)
    - Uncomment `# include      /etc/nginx/default.d/ssl.conf;` to include Let's Encrypt or other SSL wildcard certificate.
    - If any, add additional common Nginx config setting for your server needs.
- Upload the template nginx config to your server such as `/etc/nginx/conf.d/`.


### STEP 6: Setting up default deploy.php

- Setting up the `deploy.php` options around line 35 as the following.
- Make sure to replace `example.com` to your main domain and leave everything else as it is.

```
/**
* The Options
* Only 'directory' is required.
* @var array
*/
$options = array(
    'directory'     => '/var/www/vhosts/SUBDOMAIN.example.com', // Enter your server's git repo location
    'work_dir'      => '/var/www/vhosts/SUBDOMAIN.example.com',  // Enter your server's work directory. If you don't separate git and work directories, please leave it empty or false.
    'log'           => '/var/log/gitdeploy/SUBDOMAIN.log', // relative or absolute path where you save log file. Set it to false without quotation mark if you don't need to save log file.
    'branch'        => 'master', // Indicate which branch you want to checkout
    'remote'        => 'origin', // Indicate which remote repo you want to fetch
    'date_format'   => 'Y-m-d H:i:sP',  // Indicate date format of your log file
    'syncSubmodule' => false, // If your repo has submodule, set it true. (haven't tested it if this actually works)
    'reset'         => true, // If you want to git reset --hard every time you deploy, please set it true
    'git_bin_path'  => 'git',
);
```

### STEP 7: Place all necesary files onto main domain & set-up the main domain for webhook.

- Upload all files onto `/var/www/vhosts/example.com/` directory (replace example.com as your main domain)
    - .htpsswd for Basic Auth main domain.
    - Rename `gitignore.sample.txt` to `.gitignore` and upload.
    - Rename `deploy.php` to `deploy.php.sample` and upload.
    - Rename `setting-vhost-route53-backlog.sh` to any name that nobody can guess and upload.
- Login & cd to `/var/www/vhosts/example.com/` via SSH
- Run `git init` to make original git repo.
    - You could skip this part. If so, you must comment out STEP 9 in `setting-vhost-route53-backlog.sh`.
- Run `git remote add origin [Repository URL]` to add origin.
    - You could skip this part. If so, you must comment out STEP 9 in `setting-vhost-route53-backlog.sh`.

### STEP 8: Setting up parameters of setting-vhost-route53.sh

- Set parameters for your server (around line 49~)
    - `MAIN_DOMAIN`: change `EXAMPLE.COM` to your main domain
    - `MAIN_BASICAUTH_*`: Set your main domain's basic auth ID and password
    - `DIR_VHOST`: change if your vhosts direcrtory is different. This is where shell script will make subdomain directories.
    - `DIR_CURRENT`: This is the current path of the shell script.
    - `DIR_NGINX_CONF`: Change if Nginx vhosts config file are located in different directory.
    - `DIR_OWNER`: Change if you want to change besides nginx:nginx
    - `WEB_USER`: Change if nginx user is not nginx user. This will be use as the part of `sudo -u nginx`.
    - `AWS_HOSTED_ZONE`: Change it to your Route53 Hosted Zone ID that you obtained earlier. Make it blank if you don't need it.
    - `AWS_EIP`: Change it to your EC2 public IP. Make it blank if you don't need it.
    - `GIT_DEPLOY_URL`: This will print out your main git repo URL for your reference.

That's it. Now you should be ready to go.

# Version History

Date | Version | Release note
----|---|-----
2020/4/24 | 4.1.0    | - Tailwind CSS Build support added<br>- Route53 is now option if you leave AWS parameters blank and added a message
2020/4/22 | 4.0.1    | Fix wiki output to show git branch properly 
2020/3/13 | 4.0    | - New shell script <br> The shell script to setup web root document, Nginx config and route53 record<br>- Changed `deployments.php` to `deploy.php` to simplify.
2019/8/7 | 3.0beta | - Bug fixes<br>- new reset option<br>- new submodule option (not tested, so it's beta) <br>- Comments to describe more detail

# Credit

http://brandonsummers.name/blog/2012/02/10/using-bitbucket-for-automated-deployments/
http://jonathannicol.com/blog/2013/11/19/automated-git-deployments-from-bitbucket/


# Japanese Instruction / 日本語での設定方法
If you're Japanese, I've added the Japanese instructions in my blog

日本語での設定方法はこちらから (サーバーデプロイスクリプトの説明はまだ追加していません。)
http://ja.katzueno.com/2015/01/3390/
