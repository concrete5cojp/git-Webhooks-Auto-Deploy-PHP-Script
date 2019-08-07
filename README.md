git Webhooks Auto Deployment PHP Sample Script
===================================

This is a sample PHP script to auto deploy PHP script using GitHub and Bitbucket hook function.

Please read the comments inside of deployments.php for the option and how to set-it up.

This deployment script requires certain level of git and server knowledge.

This script is intended for single server use.


# How to set it up

## 1. Set up git

Prepare your git repo

## 2. Set-up server

It is highly recommended to prepare two different domain or subdomains within the same server.
Set-up where you set git deployment script and public area where your actual git deployment.

You must make sure git deploy script is protected with basic auth and configured as SSL.

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
- 'GIT_WORK_TREE=[www_path] git checkout -f [your desired branch]'  

## 3. Set your config

- Set all necessary config setting inside of deployement.php
    - Read carefully from line 1-45. You won't need to change below `Main Section`

## 4. Set-up a webhook and test drive

Your web hook URL will be like this.
Set it up as webhook of GitHub, Bitbucket, Gitlab or whatever other git services which supports webhook.

```
https://[Basic Auth ID]:[Basic Auth Pass]@example.com/deployments.php?key=YourSecretKeyHere
```
and enjoy the rest of auto deployment.

# Version History

Date | Version | Release note
----|---|-----
2019/8/7 | 3.0beta | - Bug fixes<br>- new reset option<br>- new submodule option (not tested, so it's beta) <br>- Comments to describe more detail

# Credit

http://brandonsummers.name/blog/2012/02/10/using-bitbucket-for-automated-deployments/
http://jonathannicol.com/blog/2013/11/19/automated-git-deployments-from-bitbucket/


# Japanese Instruction / 日本語での設定方法
If you're Japanese, I've added the Japanese instructions in my blog

日本語での設定方法はこちらから
http://ja.katzueno.com/2015/01/3390/
