#!/bin/sh
#
# [Shell] git Webhooks Auto Deployment
# Server Deployment Script v4.0.1
# ----------
# By Katz Ueno

# INSTRUCTION:
# ----------
# Read https://github.com/katzueno/git-Webhooks-Auto-Deploy-PHP-Script for the detail

# USE IT AT YOUR OWN RISK!

# ----------
# COMMAND Options
# ----------
# sh setting-vhost-route53.sh [SUBDOMAIN] [Git Clone URL] [BRANCH NAME] [BASIC AUTH USERNAME] [PASSWORD] [DEPLOY KEY]
# e.g.) sh setting-vhost-route53.sh subdomain git@github.com:katzueno/example.git test master coding 123456 ABCDEFG123456

# $1 [SUBDOMAIN]
# $2 [GIT SSH]
# $3 [GIT BRANCH]
# $4 [BASIC AUTH USERNAME]
# $5 [PASSWORD]
# $6 [DEPLOY KEY]

# --------------------
# GET PARAMETERS
# --------------------
SUBDOMAIN=$1
GIT_SSH=$2
GIT_BRANCH=$3
BASICAUTH_USERNAME=$4
BASICAUTH_PASSWORD=$5
DEPLOY_KEY=$6


# --------------------
# SET PARAMETERS
# --------------------

# Domains & Basic Auth
MAIN_DOMAIN="EXAMPLE.COM"
MAIN_BASICAUTH_ID="username"
MAIN_BASICAUTH_PASS="password"
DIR_VHOST="/var/www/vhosts/"
DIR_CURRENT="/var/www/vhosts/EXAMPLE.COM/"
DIR_NGINX_CONF="/etc/nginx/conf.d/"
DIR_OWNER="nginx:nginx"
WEB_USER="nginx"

# AWS Related Info
AWS_HOSTED_ZONE="XXXXXXXXXXX"
AWS_EIP="XXX.XXX.XXX.XXX"

# --------------------
# SET PARAMETERS: (Option)
# --------------------

# Main Domain Git & Deployment
GIT_DEPLOY_URL="https://github.com/katzueno/example/tree/develop/${SUBDOMAIN}.php"


# --------------------------------------------------------------------------------
# DO NOT TOUCH BELOW THIS LINE UNLESS YOU KNOW WHAT YOU ARE DOING
# --------------------------------------------------------------------------------

echo "===================================="
echo "=      GIT DEPLOY SERVER SETUP     ="
echo "===================================="

# --------------------
# SET PARAMETERS FROM PARAMETERS
# --------------------

GIT_DEPLOY_WEBHOOK="https://${MAIN_BASICAUTH_ID}:${MAIN_BASICAUTH_PASS}@${MAIN_DOMAIN}/${SUBDOMAIN}.php?key=${DEPLOY_KEY}"


# --------------------
# Function: Main Menu
# --------------------

show_main_menu()
{
  
  echo "-- Parameter Check --"
  echo "# Subdomain"
  echo "Subdomain:   ${SUBDOMAIN}.${MAIN_DOMAIN}"
  echo "# Git"
  echo "Git SSH:     ${GIT_SSH}"
  echo "Git Branch:  ${GIT_BRANCH}"
  echo "# Basic Auth"
  echo "Username:    ${BASICAUTH_USERNAME}"
  echo "Password:    ${BASICAUTH_PASSWORD}"
  echo "# PHP Deployment"
  echo "Deploy Git:  ${GIT_DEPLOY_URL}"
  echo "Deploy Key:  ${DEPLOY_KEY}"
  echo " -- -- -- -- -- -- -- -- -- -- --"
  echo "[y]. Proceed?"
  echo "[q]. Quit?"
}


# --------------------
# Function: Process Main Menu
# --------------------

do_main_menu()
{
    show_main_menu
    read -p "Enter your selection:  (y/q): " yesno
    case "$yesno" in [yY]*) ;; *) echo "Sorry, see you soon!" ; exit ;; esac
    do_create
    do_route53
    show_wiki
    echo "---------------------------"
    echo "---      Complete!      ---"
    echo "---------------------------"
    exit 0
}


# --------------------
# Function: Create vhosts directory area, git clone, Nginx config change
# --------------------

do_create() {

    # STEP 1: Make a directory in vhosts    
    echo "**NOW** Making vhost directory"
    echo "${DIR_VHOST}${SUBDOMAIN}.${MAIN_DOMAIN}"
    cd ${DIR_VHOST}
    mkdir ${DIR_VHOST}${SUBDOMAIN}.${MAIN_DOMAIN}
    sudo chown -R ${DIR_OWNER} ${DIR_VHOST}${SUBDOMAIN}.${MAIN_DOMAIN}
    sudo chmod -R 775 ${DIR_VHOST}${SUBDOMAIN}.${MAIN_DOMAIN}
    
    # STEP 2: Clone git
    echo "**NOW** Cloning git"
    echo "sudo -u ${WEB_USER} git clone ${GIT_SSH} ./"
    cd ${DIR_VHOST}${SUBDOMAIN}.${MAIN_DOMAIN}/
    sudo -u ${WEB_USER} git clone ${GIT_SSH} ./
    sudo -u ${WEB_USER} git checkout ${GIT_BRANCH}
    
    # STEP 3: Copy ${WEB_USER} config
    echo "**NOW** Copying ${WEB_USER} config"
    echo "${DIR_NGINX_CONF}$(date "+%Y%m%d")_vhost_${SUBDOMAIN}.${MAIN_DOMAIN}.conf"
    cd ${DIR_NGINX_CONF}
    sudo cp ${DIR_NGINX_CONF}00000000_vhost_sample.conf.template ${DIR_NGINX_CONF}$(date "+%Y%m%d")_vhost_${SUBDOMAIN}.${MAIN_DOMAIN}.conf
    
    # STEP 4: Setting up Nginx Config
    echo "**NOW** Setting up Nginx Config"
    sudo sed -i "s/SUBDOMAIN.${MAIN_DOMAIN}/${SUBDOMAIN}.${MAIN_DOMAIN}/g" ${DIR_NGINX_CONF}$(date "+%Y%m%d")_vhost_${SUBDOMAIN}.${MAIN_DOMAIN}.conf
    
    # STEP 5: Restarting Nginx
    echo "**NOW** Restarting Nginx"
    sudo nginx -t
    sudo systemctl restart nginx
    
    # STEP 6: Setting up Basic Auth
    echo "**NOW** Setting up Basic Auth"
    echo "Username: ${BASICAUTH_USERNAME}"
    echo "Password: ${BASICAUTH_PASSWORD}"
    sudo echo "${BASICAUTH_USERNAME}:$(openssl passwd -apr1 ${BASICAUTH_PASSWORD})" >> ${DIR_VHOST}${SUBDOMAIN}.${MAIN_DOMAIN}/.htpasswd
    
    # STEP 7: Copying auto deploy php from template
    echo "**NOW** Copying deployment php file from template"
    echo "${DIR_VHOST}${MAIN_DOMAIN}/${SUBDOMAIN}.php"
    sudo -u ${WEB_USER} cp ${DIR_VHOST}${MAIN_DOMAIN}/deploy.php.sample ${DIR_VHOST}${MAIN_DOMAIN}/${SUBDOMAIN}.php
    
    # STEP 8: Setting up auto deploy php
    echo "**NOW** Setting up auto-deploy php"
    sudo sed -i "s/EnterYourSecretKeyHere/${DEPLOY_KEY}/g" ${DIR_VHOST}${MAIN_DOMAIN}/${SUBDOMAIN}.php
    sudo sed -i "s/SUBDOMAIN/${SUBDOMAIN}/g" ${DIR_VHOST}${MAIN_DOMAIN}/${SUBDOMAIN}.php
    echo "Webhook URL:"
    echo "${GIT_DEPLOY_WEBHOOK}"

    # STEP 9: Committing the git deploy changes to Backlog
    cd ${DIR_VHOST}${MAIN_DOMAIN}/
    sudo -u ${WEB_USER} git add .
    sudo -u ${WEB_USER} git commit -m "${SUBDOMAIN}.${MAIN_DOMAIN} added"
    sudo -u ${WEB_USER} git push
}


# --------------------
# Function: Register subdomain to DNS zone via Route 53
# --------------------
do_route53() {
ROUTE53_JSON=$(cat << EOS
{
    "Comment": "CREATE/DELETE/UPSERT a record ",
    "Changes": [{
        "Action": "CREATE",
        "ResourceRecordSet": {
            "Name": "${SUBDOMAIN}.${MAIN_DOMAIN}",
            "Type": "A",
            "TTL": 300,
            "ResourceRecords": [{ "Value": "${AWS_EIP}"}]
    }}]
}
EOS
)
cd ${DIR_CURRENT}
echo ${ROUTE53_JSON} > route53.json
aws route53 change-resource-record-sets --hosted-zone-id ${AWS_HOSTED_ZONE} --change-batch file://route53.json
}


# --------------------
# Function: Create Markdown for Wiki
# --------------------
show_wiki() {
cat << EOS
==============================
Wiki Document Server/Coding
==============================
[toc]

# Basic Auth

| ID | Password
|:- | :- |
| ${BASICAUTH_USERNAME} | ${BASICAUTH_PASSWORD} |

https://${SUBDOMAIN}.${MAIN_DOMAIN}/

# Git Info

----|------
Git | ${GIT_SSH}
Branch | ${GIT_BRANCH}
reset hard | Yes
Deploy script | ${GIT_DEPLOY_URL}

* deploy script does not change branch, you must git checkout on the server directly
* サーバー上でブランチを変更したい場合は git checkout コマンドを直接サーバー上で実行すること

# Server Info

## Document Root

${DIR_VHOST}${SUBDOMAIN}.${MAIN_DOMAIN}

==============================
EOS

echo "# Webhook URL:"
echo "${GIT_DEPLOY_WEBHOOK}"

}

# --------------------
# Bootstrap
# --------------------
do_main_menu
