# e-PT #

Welcome to the Open Source repository of the e-Proficiency Testing (e-PT) software

#### Pre-requisites
* Apache2
* MySQL 5+
* PHP 7+

#### Supported PT Schemes
* HIV Serology
* HIV Viral Load 
* Early Infant Diagnosis
* HIV Recency
* Covid-19

### How do I get set up? ###

* [Download the e-PT Source Code](https://github.com/deforay/ept/releases) and put it into your server's root folder (www or htdocs). 
* Create a blank database and [import the sql file that you can find in the downloads section of this repository](https://github.com/deforay/ept/releases)
* Rename the config file application/configs/application.dist.ini to application/configs/applicationt.ini and update the database and other settings
* Next we will set up virtual host for this application. You can find many guides online on this topic. For example to set up on Ubuntu you can follow this guide : https://www.digitalocean.com/community/tutorials/how-to-set-up-apache-virtual-hosts-on-ubuntu-18-04
* Before we set up the virtual host, ensure that the apache rewrite module is enabled in your Apache webserver settings
* Edit your computer's hosts file to make an entry for this virtual host name
* Next we create a virtual host pointing to the root folder of the source code. You can see an example below (assuming your ept is located in ```/var/www/ept``` directory) : 

```apache
<VirtualHost *:80>
   DocumentRoot "/var/www/ept/public"
   ServerName ept.example.org
   AddDefaultCharset UTF-8

   <Directory "/var/www/ept/public">
       Options Indexes MultiViews FollowSymLinks
       AllowOverride All
       Order allow,deny
       Allow from all
   </Directory>
</VirtualHost>
```

You also need to add a scheduled job. Following is an example on a Linux system

```bash

* * * * * cd /var/www/ept/ && ./vendor/bin/crunz schedule:run


```


### Next Steps ###

* Once you have the software set up, you can visit the admin panel http://ept.example.org/admin and log in with the credentials eptmanager@gmail.com and 123
* Now you can start adding Participants, Participant logins, PT Surveys, Shipments etc.

### Who do I talk to? ###

* You can reach us at amit (at) deforay (dot) com
