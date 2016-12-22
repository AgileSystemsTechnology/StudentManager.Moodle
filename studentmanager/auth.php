<?php

/**
 * Plugin to authenticate against Student Manager
 *
 * @package auth_studentmanager
 * @author Michael Esteves
 * @license The MIT License (MIT)
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/authlib.php');

/**
 * Plugin for no authentication.
 */
class auth_plugin_studentmanager extends auth_plugin_base {

    /**
     * Constructor.
     */


    public function __construct() {
        global $CFG;
        global $token;
        
        require_once($CFG->libdir.'/adodb/adodb.inc.php');

        $this->config = get_config('auth/studentmanager');
        if (empty($this->config->extencoding)) {
            $this->config->extencoding = 'utf-8';
        }
        $this->authtype = 'studentmanager';
        $this->config = get_config('auth/studentmanager');
    }

    public function getToken ($username, $password) {
        $institutionId = $this->config->institutionId; 
        $subdomain = $this->config->subdomain; 
    
        $curl = curl_init();

        curl_setopt_array($curl, array(
		//CURLOPT_PORT => "8205",
        CURLOPT_URL => "https://$subdomain.studentmanager.co.za/api/token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "grant_type=password&username=$username&password=$password&institutionId=$institutionId&moodle=true",
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded",
            "postman-token: 093121a2-2666-5666-e67d-ade38b8244c2"
        ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
             return $err;
        } else {
            return $response;
        }

    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function auth_plugin_studentmanager() {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct();
    }

    /**
     * Returns true if the username and password work or don't exist and false
     * if the user exists and the password is wrong.
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool Authentication success or failure.
     */
    function user_login ($username, $password) {
        
        $response = $this-> getToken($username, $password);
        
        if (strpos($response, 'access_token') !== false) {
            return true;
        }

        return false;
    }



    function prevent_local_passwords() {
        return !$this->is_internal();
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return false;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    function can_change_password() {
        $this->is_internal();
    }

    /**
     * Returns the URL for changing the user's pw, or empty if the default can
     * be used.
     *
     * @return moodle_url
     */
    function change_password_url() {
        return null;
    }

    /**
     * Returns true if plugin allows resetting of internal password.
     *
     * @return bool
     */
    function can_reset_password() {
        return true;
    }

    /**
     * Returns true if plugin can be manually set.
     *
     * @return bool
     */
    function can_be_manually_set() {
        return true;
    }

    /**
     * Prints a form for configuring this authentication plugin.
     *
     * This function is called from admin/auth.php, and outputs a full page with
     * a form for configuring this plugin.
     *
     * @param array $page An object containing all the data for this page.
     */
    function config_form($config, $err, $user_fields) {
        include "config.html";
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     */
    function process_config($config) {

         // set to defaults if undefined
        if (!isset($config->email)) {
            $config->email = '';
        }

        // set to defaults if undefined
        if (!isset($config->password)) {
            $config->password = '';
        }

        // set to defaults if undefined
        if (!isset($config->institutionId)) {
            $config->institutionId = '';
        }

        // Save settings.
        set_config('email', $config->email, 'auth/studentmanager');
        set_config('password', $config->password, 'auth/studentmanager');
        set_config('institutionId', $config->institutionId, 'auth/studentmanager');
        set_config('subdomain', $config->subdomain, 'auth/studentmanager');

        return true;
    }

        /**
     * Indicates if moodle should automatically update internal user
     * records with data from external sources using the information
     * from auth_plugin_base::get_userinfo().
     *
     * @return bool true means automatically copy data from ext to user table
     */
    function is_synchronised_with_external() {
        return true;
    }

    /**
     * Reads any other information for a user from external database,
     * then returns it in an array.
     *
     * @param string $username
     * @return array
     */
    function get_userinfo($username) {
        $email = $this->config->email; 
        $password = $this->config->password; 

        $tokenResponse = $this-> getToken($email, $password);

        $key = "access_token";
        
        if (strpos($tokenResponse, $key) !== false) {
           
            $jsonObj = json_decode($tokenResponse);
            $access_token = $jsonObj->$key;

            $curl = curl_init();

            curl_setopt_array($curl, array(
            //CURLOPT_PORT => "8205",
            CURLOPT_URL => "https://$subdomain.studentmanager.co.za/api/User/GetUser?email$username",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "authorization: Bearer $access_token",
                "cache-control: no-cache",
                "postman-token: d2d5971f-3e02-ec52-b1e7-eaac5c8f5e2c"
            ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                error_log('Error: ' . print_r($err, true));
            } else {
                error_log('Response: ' . print_r($response, true));
            }

            $obj = json_decode($response);
            
            $countries = get_string_manager()->get_list_of_countries();

            $countrykey = array_search($obj->{'CountryName'}, $countries);

            $result =  array(
                "firstname" => $obj->{'firstName'},
                "lastname" => $obj->{'lastName'},
                "middlename" => $obj->{'middleName'},
                "email" =>  $obj->{'email'},
                "username" => $obj->{'email'}, 
                "country" =>  $countrykey,
                "idnumber" =>  $obj->{'nationalId'}, 
            );
                      
            return $result;
        }
    }

     function user_update($olduser, $newuser) {
        return true;
     }
}


