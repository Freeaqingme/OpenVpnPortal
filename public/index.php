<?php

/**
 * VPN Portal (http://www.enrise.com/)
 *
 * @link      http://github.com/enrise/VPN for the canonical source repository
 * @copyright Copyright (c) 2012 Enrise BV.
 * @license   FreeBSD <LICENSE.MD>
**/
/* Start session */
session_start();

/* Include Config */
require "inc/config.inc.php";

if (DEBUG) {
    error_reporting(-1);
    ini_set('display_errors', 1);
}

/* Include dependencies */
require "inc/embed_zf2.inc.php";
require "inc/sqlite.inc.php";
require "../vendor/adLDAP/lib/adLDAP/adLDAP.php";

use adLDAP\adLdap;
use adLDAP\adLDAPException;

/* Include the most simplistic templateparser & languageparser & bootstrap generator */
require "inc/templateParser.inc.php";
require "inc/bootStrapper.inc.php";
require "inc/languageParser.inc.php";

/* Catch the page */
$page = 'home';
if (isset($_GET['p'])) {
    $page = $_GET['p'];
}

/* Create the objects */
$BS = new BootStrapper();
$lang = new LanguageParser();
$TP = new SimpleTemplateParser();
$TP->setTemplate('base_template.phtml');
$DB = new DB;

// The config

if (is_file(CONFIG_FILE)) {
    require APP_PATH . '/vendor/zf2/library/Zend/Config/Reader/Ini.php';
    $config = new Zend\Config\Reader\Ini();
    $config = $config->fromFile(CONFIG_FILE);
} else {
    $config = array();
}

//Initialize adLDAP
try {
    $adldap = new adLDAP($adldapOptions);
}
catch (adLDAPException $e) {
    if (DEBUG) {
        $TP->appendContent($BS->errormessage('EXCEPTION:'.$e));
    }
    header("HTTP/1.0 503 Service Unavailable");
    $TP->setContent($BS->errormessage($lang->t('ldap_server_not_reachable')));
    $TP->appendContent($BS->row(
        $BS->block(12, $BS->loginForm($lang->t('username'), $lang->t('password'), $lang->t('signin'), 'login.html'))
        )
    );
    echo $TP->getOutput();
    die;
}

/*
***************************** TO INSTALL, RUN THESE 2 LINES.
*/
//$DB->install();
//die;


switch ($page) {
    /**
     * Default login page + login form.
     */
    case 'home':
        $TP->setTitle($lang->t('login'));
        $TP->setContent($BS->heroUnit($lang->t('hometitle'), $lang->t('hometext')));
        $TP->appendContent($BS->row(
            $BS->block(12,
                $BS->loginForm($lang->t('username'), $lang->t('password'), $lang->t('signin'), 'login.html'))
            )
        );
        break;

    /**
     * The user wants to log out.
     */
    case 'logout':
        $TP->setTitle($lang->t('logout'));
        $TP->setContent($BS->heroUnit($lang->t('logout'), $lang->t('loggedouttext')));
        session_destroy();
        session_regenerate_id(true); //Regen the sessionid
        break;

    /**
     * The user has submitted the login form.
     */
    case 'login':
        if (!isset($_POST["username"]) || !isset($_POST["password"])) {
            header('Location: index.php');
            die;
        }
        $_POST["username"] = preg_replace("/[^a-z]+/", "", $_POST['username']);
        $TP->setTitle($lang->t('login'));
        if ($DB->getLoginsSince(BRUTEFORCE_MINUTES)>BRUTEFORCE_ATTEMPTS) {
            echo 'Bruteforce detected';
            die;
        }
        
        //Prerequisites are good. Lets check the AD.
                
        $loginResult = $adldap->user()->authenticate($_POST["username"], $_POST["password"]);
            
        //Check if the user is logged in
        if (true !== $loginResult) {
            //Invalid credentials
            header("HTTP/1.0 401 Unauthorized");
            $DB->putLogin($_POST["username"]);
            $TP->setContent($BS->errormessage($lang->t('invalid_credentials')));
            $TP->appendContent($BS->row(
                $BS->block(12, $BS->loginForm($lang->t('username'), $lang->t('password'), $lang->t('signin'), 'login.html'))
                )
            );
            echo $TP->getOutput();
            die;
        }
        
        //Tell the user he / she's logged in
        $TP->appendContent($BS->successmessage($lang->t('loggedin')));
        
        //Fetch all the groups the users' a member of. 
        //You could use inGroup() with a GUID, but you'd have to fill in 
        //administrator passwords in the config.
        //Example: $allowed = $adldap->user()->inGroup($_POST["username"], VPN_GROUP_GUID, true, true);
        $allowed = $adldap->user()->inGroup($_POST["username"], VPN_GROUP_NAME, true, false);
        
        if (true === $allowed) { //Allowed to use VPN. Show the downloadbuttons!

            //Download.php generates everythin'.
            header("HTTP/1.0 200 OK");
            $_SESSION["username"] = $_POST['username'];
            $_SESSION["ip"] = $_SERVER["REMOTE_ADDR"]; //Session stealing security / logging

            $windowsSerial = '<span class="serial">Serial: ?</span>';
            $osxSerial = '<span class="serial">Serial: ?</span>';
            $linuxSerial = '<span class="serial">' . $lang->t('no_serial_needed') . '</span>';
            if (array_key_exists('serials', $config)) {
                $windowsSerial = '<span class="serial"><dl>'
                    . '<dt>Name</dt><dd>' . $config['serials']['windows']['name'] . '</dd>'
                    . '<dt>E-mail</dt><dd>' . $config['serials']['windows']['email'] . '</dd>'
                    . '<dt>Serial</dt><dd>' . $config['serials']['windows']['key'] . '</dd></dl>';

                $osxSerial = '<span class="serial"><dl>'
                    . '<dt>Name</dt><dd>' . $config['serials']['osx']['name'] . '</dd>'
                    . '<dt>E-mail</dt><dd>' . $config['serials']['osx']['email'] . '</dd>'
                    . '<dt>Serial</dt><dd>' . $config['serials']['osx']['key'] . '</dd></dl>';
            }

            $TP->appendContent($BS->row(
                $BS->block(3, '<H2>Alleen Config</H2><a href="download.php?kind=config">Download .zip</a>') .
                $BS->block(3, '<H2>Windows + Installer</H2><a href="download.php?kind=winexe">Download .zip</a>' . $windowsSerial) .
                $BS->block(3, '<H2>Linux</H2><a href="download.php?kind=linux">Download .zip</a>' . $linuxSerial) .
                $BS->block(3, '<H2>OSX + Installer</H2><a href="download.php?kind=mac">Download .zip</a>' . $osxSerial)
            ));
            
            $TP->appendContent($BS->row(
                $BS->block(12, '<br/><br/>' )) .
                $BS->row(
                    $BS->block(12, '<a href="http://wiki.enrise.com/wiki/VPN_instellen" target="_blank"> > Wiki page - More information on setting up your VPN.</a>' )
                )
            );
            
        } else { //Not allowed to use VPN
            header("HTTP/1.0 403 Forbidden");
            $TP->appendContent($BS->errormessage($lang->t('vpn_not_allowed')));
        }
        break;

    default: //404
        header("HTTP/1.0 404 Not Found");
        $TP->setContent( $BS->row( $BS->block(12, '<h2>' . $lang->t('404title') . '</h2><p>' . $lang->t('404text') . '</p>') ) );
        break;
}

echo $TP->getOutput();