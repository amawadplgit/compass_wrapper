# compass_wrapper

This wrapper class will communicate with IMIS database for IMIS authentication, Password resets etc

<h2><strong>Installation Steps</strong></h2>

add the following to your composer.json file of your project in the required section

<pre>"ampco/compass_wrapper": "dev-master"</pre>

Then add the following in the repositories section of your composer.json file. 
If you dont have a repository section just copy and paste the entire snippet below.

<pre>"repositories": {
        "ampco":{
            "type": "vcs",
            "url":  "https://github.com/AMPCo/compass_wrapper.git"
        }
    }
    </pre>
    
Then navigate to the location of your composer file and use the below command download the repo.

<pre>composer update ampco/compass_wrapper</pre>
 
<strong>NOTE : DO NOT RUN "COMPOSER UPDATE" WITHOUT THE PACKAGE NAME AS IT WILL UPDATE ALL THE PACKAGES</strong>

If you haven't already setup your git access token for the repo a prompt on the terminal will let you know how 

The namespace for the package is CompassWrapper. 
To add the extended functionality with password resets etc use AMACompassWrapperPlus
<pre>use CompassWrapper/AMACompassWrapperPlus;</pre>
If only limited functionality like authentication is required use AMACompassWrapperBase
<pre>use CompassWrapper/AMACompassWrapperBase;</pre>

Thats it. Instantiate the class and you are good to go !!

