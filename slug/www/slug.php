<?php
/**
 * FILE slug.php
 *  RESTful Service API for executing commands at the Linux OS level
 *      - Defaults to bash unless otherwise specified
 *      - Provides namespaced URL routing to mask the OS file system
 *      - Mappingss from URL routes to commands is configured through an easy-to-read JSON file
 *      - Served over PHP port 9000 to whatever HTTP proxy you prefer, such as Nginx or Apache
 */

namespace Org;
namespace Org\SLUG;

libxml_use_internal_errors(true);

/*
 * CLASS SLUG 
 *  is not static-based. Please instantiate and access its properties directly.
 *      Object instantiation allows you to set multiple SLUG instances for 
 *      multiple slug.json files in your own apps
 * 
 *  Usecase: Create a PHP object manifest of an executable's response instead of outputting as a service:
 *      1. include slug.php
 *      2. create a new SLUG object: "$slug = new \Org\SLUG\Slug('org', $app_init_array, false);"
 *      3. execute the command: "$slug->exec();"
 *      4. see the command output: "echo '<pre>'; var_dump($slug->output); echo </pre>;"
 * 
 *  ARGUMENTS 
 *      @param  namespace   the root of slug.json.  
 *      @param  app_init    an array that must contain the values below.
 *          Think about each value as part of a URL, but is accessible via PHP.
 *          A few included apps are given as examples.
 *          @param app_init[
 *              'app_namespace': '[iot,vcumux]', // Defined under the root namespace
 *              'app_name': '[hdhr, stream]', // Defined under the app_namespace
 *              'action': '[channel, check_flag]', // The action to take for the app
 *              'args': array('value1', 'value2') // Whatever argument values to pass to the executable
 *              'flags': array('-flag1', '--flag2') // Whatever flags to pass to shell to the executable
 *              'exec_type': Accepts 'sh', 'bash', 'py', 'rb', or 'npm'
 *          ]
 * 
 *      NOTE 1: - the executable script MUST accept regular string values, 
 *          usually contained as "$1" and "$2"
 *      NOTE 2: - the executable script MAY include no arguments at all, 
 *          url-encoded argument values, or a combination of arguments and flags
 * 
 */

class Slug {
    protected $exec_type = 'bash';   
    
    /**
     * CONSTRUCTOR
     * @param type $namespace
     * @param type $app_init
     * @param type $response_yn
     * @return boolean|\Org\SLUG\Exception
     * @throws Exception
     */
    public function __construct($slugfile = '/slug.json', $namespace = "org", $app_init = array(), $response_yn = false){ 

        ## Properties
        $this->response_yn = $response_yn;
        $this->json_root = $namespace;
        $this->args = array();
        $this->flags = array();
        $this->exec_root = "/usr/local/bin/slug-scripts";
        $this->sh_root = "";
        $this->slug_response = "";
        $this->slug_root = "";
        $this->slug_response = "";
        $this->slug_namespaces = array();
        $this->slug_namespace = "";
        $this->slug_apps = new \stdClass();
        $this->slug_app_directives = array();
        $this->slug_app_name = "";
        $this->slug_app_web_root = "";
        $this->slug_app_action_directives = array();
        $this->slug_app_exec_dir = "";
        $this->path_sectors = array();
        $this->path = $_SERVER['REQUEST_URI'];
        $this->actions = new \stdClass();
        $this->action = new \stdClass();
        $this->error = 0;
        
        $slug_body = new \stdClass();
        
        ## Import slug.json
        if ( getenv("SLUG_CONFIG") == FALSE) {
            $this->slugfile = $slugfile;
        } else {
            $this->slugfile = getenv("SLUG_CONFIG");
        }
        $conf = file_get_contents($this->slugfile, FILE_USE_INCLUDE_PATH);
        $obj_conf = json_decode($conf);
        try{
           $this->{$namespace} = $obj_conf->{$namespace};
        } catch (Exception $ex) {
            $this->slug_response = "{\"slug\":{\"msg\":{\"error\":\"404 NOT FOUND\"}}}";
            $this->error = 1;
            $this->exec('', '404');
            return $ex;
        }
        
        ## PARSE URL PATH AS ARRAY or VALIDATE $app_init as OUR URL
        $this->path_sectors = explode("/", $this->path);
        # If we're proxying from another server, remove 'slug' as the entry point
        if($this->path_sectors[0] == 'slug') {
            array_shift($this->path_sectors); 
        }
        $sector_size = sizeof($this->path_sectors);
        $last_sector = explode("?", $this->path_sectors[$sector_size-1]);
        $this->path_sectors[$sector_size-1] = $last_sector[0];

        # Validate and use $app_init (if it has been passed) instead of url
        if ( ! empty($app_init) ) {
            $this->path_sectors = array();
            try{
                $this->path_sectors = array('slug', $app_init['app_namespace'], $app_init['app_name'], 'action', $app_init['action']);
            } catch (Exception $ex) {
                throw new Exception("SLUG __METHOD__: app_init was passed in, but Slug could not contstruct an executable path.");
            }
            
            # Accept a different type than 'bash' for execution
            if( is_string($app_init['exec_type']) && $app_init['exec_type'] != '' ) {
                $this->exec_type = $app_init['exec_type'];
            }

            # Args are normal descriptors for commands to carry out functions with variables
            if( ! empty($app_init['args']) ) {
                $i = 0;
                foreach ( $app_init['args'] as $arg ) {
                    $i++;
                    $this->addActionArg($i, $arg);
                }
            }
            
            # Flags are binary ways of establishing which functions to carry out
            if( ! empty($app_init['flags']) ) {
                $i = 0;
                foreach ( $app_init['flags'] as $flag ) {
                    $i++;
                    $this->addActionFlag($i, $flag);
                }
            }
            
            $this->path = implode('/', $this->path_sectors);
        }
        # Trim off .json at the end of url if it was requested
        if( strpos($this->path_sectors[4], ".json") !== FALSE ) {
            $json_ext = explode(".json", $this->path_sectors[4]);
            $this->path_sectors[4] = $json_ext[0];
        }
        
        ## Use getters and setters to parse JSON-PHP object
        ## Set shell executable root directory
        $this->sh_root = $this->{$namespace}->sh_root;

        ## Set temporary object body for traversing slug apps
        $slug_body = $this->getSlugBody( $namespace );

        ## Set slug local directory root
        $this->slug_root = $slug_body->web_root;

        ## Set the slug-specific namespaces for apps to prop slug_namespaces (i.e. [iot])
        $this->setSlugAppNamespaces( $slug_body );

        ## Verify if a namespace exists in the url
        if( ! $this->verifyAppNamespace( $this->slug_namespaces ) ) {
            $this->slug_response = (object)array('slug' => array('response' => array('error' => "\"" . $this->path_sectors[1] . "/ did not match any slug namespaces.\"")));
            $this->error = 1;
            $this->exec(true, '404');
            return false;
        }

        ## Set the validated slug namespace
        $this->setCurrentAppNamespace( $this->slug_namespaces );

        ## Set the webroot of the namespaced discovered App
        $this->setSlugNamespaceWebRoot( $slug_body, $this->slug_namespaces );

        ## Set the slug_apps object to the app listings under the slug namespace
        $this->setSlugAppsBody( $slug_body, $this->slug_namespace );

        ## Set the app directives 
        $this->setSlugAppDirectives( $this->slug_apps );

        ## Verify if an app name exists in the url
        if( ! $this->verifyAppPath( $this->slug_app_directives ) ) {
            $this->slug_response = (object)array('slug' => array('response' => array('error' => "\"" . $this->path_sectors[2] . "/ did not match any slug apps.\"")));
            $this->error = 1;
            $this->exec(true, '404');
            return false;
        }

        ## Set the verified app name
        $this->setSlugAppName( $this->slug_app_directives );

        ## Set the current app's web root
        $this->setSlugAppWebRoot( $this->slug_apps, $this->slug_app_name ); 

        ## Set the app execution directory
        $this->setSlugAppExecutionDir( $this->slug_apps, $this->slug_app_name );

        ## Set the app action directives
        $this->setSlugAppActionDirectives( $this->slug_apps, $this->slug_app_name );

        ## Set the current app action
        $this->setSlugAppActions( $this->slug_apps, $this->slug_app_name );

        ## Verify if an app action exists in the url
        if( ! $this->verifyAppActionPath( $this->slug_app_action_directives ) && $this->slug_response == "" ) {
            $this->error = 1;
            $this->slug_response = (object)array('slug' => array('response' => array('error' => "\"" . $this->path_sectors[2] . ": Your request did not match any slug app actions.\"")));
            $this->exec(true, '404');
            return false;    
        }

        ## Set the current action as object
        $this->setSlugAppAction( $this->slug_app_action_directives );
        
        ## Set the executable type
        $this->setExecType();
        
        if(empty((array)$this->action) && $this->slug_response == "") {
            $this->error = 1;
            $this->slug_response = (object)array('slug' => array('response' => array('error' => "No action has been set for this directive.")));
            $this->exec(true, '404');
            return false; 
        }
        
        ## STEP 4: Unset json that shouldn't be public
        unset( $this->{$namespace} );
        unset ( $this->slug_apps );
        unset ( $this->actions );
        
    }
    
    /**
     * METHOD exec
     * @param type $response    whether or not to output the execution or just set it to memory
     * @param type $status      the http status code to pass into the output header, if response=true
     * @param type $exec_type   an executable library type if not using a default 'bash' command
     * @return $this
     */
    public function exec($response = true, $status = '200', $exec_type = '') {
        
        if($response !== true && $response !== false) {
            $response = true;
        }
        
        if( $exec_type == '' ) {
            $exec_type = $this->action->type;
        }

        // Output early errors, typically 404, from __construct method
        if( ($this->response_yn || $response) && $this->slug_response != "") {
            $this->output($status);
            die();
        }
        
        $args = $this->getActionArgs();
        $flags = $this->getActionFlags();
        
        $args_str = "";
        
        for( $i=0; $i < sizeof($args); $i++ ) {
            if( isset($flag[$i]) ) {
                $args_str .= $args[$i] . '-' . strval($flags_str[$i]) . ' ';
            } else {
                $args_str .= strval($args[$i]) . ' ';
            }
        }

        $script = $this->slug_app_exec_dir . "/" . $this->action->file;
        
        $exec_resp = shell_exec( $exec_type . ' ' . $script . ' ' . $args_str );

        # If we receive a json format back from the script, we can include this within the msg body as more json
        try {
            $json_capable = json_decode($exec_resp);
            if( $json_capable !== NULL ) {
                $exec_resp = $json_capable;
            } else {
                $exec_resp = trim($exec_resp);
            }  
        } catch (Exception $ex) {
            $exec_resp == NULL;
        }
           
        # If we receive an xml body from the script, attempt to convert it to 
        #  an object and then print set json
        try {
            if( $json_capable == NULL) {
                $xml_capable = simplexml_load_string($exec_resp);
                if( $xml_capable !== FALSE ) {
                    $exec_resp = $xml_capable;
                } else {
                    $exec_resp = trim($exec_resp);
                }
            }
        } catch (Exception $ex) {
            $exec_resp == NULL;
        }

        /**
         * Failure block
         */
        if ( $this->action->log !== "false" ) {
            // If output is allowed ...
            if ( $exec_resp == NULL ) {
                if ($this->action->msg->failure == "@host") {
                    $this->slug_response = (object)array('slug' => array('response' => array('error' => 'There was an internal script error.')));
                } else {
                    $this->slug_response = (object)array('slug' => array('response' => array('error' => "'" . trim($this->action->msg->failure) . "'")));
                }
                
                if ( $response ) {
                    $this->output('', '500');
                }
                return $this;
            }
        } else {
            // If output is not allowed ...
            if ( $exec_resp == NULL ) {
                if ($this->action->msg->failure == "@host") {
                    $this->slug_response = (object)array('slug' => array('response' => array('error' => "The host says there was an error, but it\'s a private affair.")));
                } else {
                    $this->slug_response = (object)array('slug' => array('response' => array('error' => "A call was made, and something happened, but it was not what you might call \'success\'.")));
                }
                
                if ( $response ) {
                    $this->output('', '500');
                }
                return $this;
            }
        }
        
        // Success block
        if ( $this->action->log !== "false" ) {
            if ( $this->action->msg->success == "@host" ) {
                $this->slug_response = (object)array('slug' => array('response' => array($this->action->name => $exec_resp)));
            } else {
                $this->slug_response = (object)array('slug' => array('response' => array($this->action->name => trim($this->action->msg->success))));
            }
            
            if ( $response ) {
                $this->output('', '200');
            }
        } else {
            $this->slug_response = (object)array('slug' => array('response' => array($this->action->name => 'A successfull call was made, but the output is private.')));
            
            if ( $response ) {
                $this->output('', '200');
            }
        }
        
        if ( $response ) {
            $this->output();
        }
        
        return $this;   
    }
    
    /**
     * METHOD output
     * @param type $output_str  Pass in a custom output string instead of what 
     *      set through the constructor or exec methods
     * @param type $status_code The status code to set in the HTTP header
     */
    public function output( $output_str = "", $status_code = '200' ) {
        header_remove();
        header("Cache-Control: no-cache, must-revalidate");
	header("Expires: 0");
        header('Content-Type: application/json');
        header('Status: ' . $status_code);
        
        // if you are doing ajax with application-json headers
        if (empty($_POST)) {
            $_POST = json_decode(file_get_contents("php://input"), true) ? : [];
        }
        
        if ( $output == "") {
            $output = json_encode($this->slug_response, JSON_PRETTY_PRINT);
            echo $output;
        } else {
            $output = json_encode($output_str, JSON_PRETTY_PRINT);
            echo $output;
        }
        
        die();
    }
    
    ##
    #
    # GETTERS
    #
    ##
    
    /**
     * METHOD getSlugBody
     * @param type $namespace   The root namespace of the slug.json file
     * @return boolean          Throw exception and return false if we could not import the slug.json file
     */
    private function getSlugBody($namespace) {
        ## Import slug.json
        try{
           return $this->{$namespace}->slug;
        } catch (Exception $ex) {
            $this->slug_response = (object)array('slug' => array('response' => array('error' => "Fore 0 fore! Swing but no hit!")));
            return false;
        }
    }
    
    /**
     * METHOD getActionArgs
     * @return array    Single column array of arguments
     */
    private function getActionArgs( ) {
        $args = array();
        if( empty($this->args) ) {
            $this->args = $_GET;
        }

        foreach( $this->args as $arg => $val ) {
            if( strpos( $arg, "arg" ) !== FALSE ) {
                $arg = trim($arg);
                $arg = urlencode($arg); 
                array_push( $args, $val );
            }
        }        
        return $args;
    }
    
    /**
     * METHOD getActionFlags
     * @return array    Single column array of flags
     */
    private function getActionFlags( ) {
        $flags = array();
        if( empty($this->flags) ) {
            $this->args = $_GET;
        }

        foreach( $this->flags as $flag => $val ) {
            if( strpos( $flag, "flag" ) !== FALSE ) {
                $flag = trim($flag);
                $flag = urlencode($flag); 
                array_push( $flags, $val );
            }
        }        
        return $flags;
    }
    
    ##
    #
    # SETTERS
    #
    ##
    
    /**
     * METHOD setSlugAppNamespaces
     *  Sets the collections of apps into contained namespaces
     * @param type $slug_body
     * @return type
     */
    public function setSlugAppNamespaces( $slug_body ) {
        foreach ( $slug_body as $slug_namespace => $body) {
           if ( $slug_namespace == "web_root" ) {
               continue;
           }
           array_push($this->slug_namespaces, $slug_namespace);
        }
        return;
    }
    
    /**
     * METHOD setSlugNamespaceWebRoot
     *  Set the route configuration to the requested app namespace
     * @param type $body            JSON body under 'slug' of the root namespace
     * @param type $namespaces      array of the collections of apps
     * @return boolean              True if successful, False if unsuccessful
     */
    public function setSlugNamespaceWebRoot( $body, $namespaces ) {
        foreach ( $namespaces as $slug_namespace ) {
           if( $this->path_sectors[1] == $slug_namespace ) {
               $this->slug_app_web_root = $body->$slug_namespace->sh_root;
               return;
           } 
        }
        return false;
    }
    
    /**
     * METHOD setCurrentAppNamespace
     *  Set the collection name for the executable being called
     * @param type $namespaces      Array of app collections to search
     * @return boolean
     */
    public function setCurrentAppNamespace( $namespaces ) {
        foreach ( $namespaces as $slug_namespace ) {
           if( $this->path_sectors[1] == $slug_namespace ) {
               $this->slug_namespace = $this->path_sectors[1];
               return;
           }
        }
        return false;
    }
    
    /**
     * METHOD setSlugAppsBody
     *  Set the JSON body of the apps available inside of a collection
     * @param type $slug_body
     * @param type $namespace
     * @return type
     */
    public function setSlugAppsBody( $slug_body, $namespace ) {
        $this->slug_apps = $slug_body->{$namespace};
        return;
    }
    
    /**
     * METHOD setSlugAppDirectives
     *  Set the available paths for a collection of commands
     * @param type $slug_apps
     * @return boolean
     */
    public function setSlugAppDirectives( $slug_apps ) {
        $paths = array();
        foreach ( $slug_apps as $appName => $appValues ) {
            if ( $appName == 'sh_root' ) {
                continue;
            }
            $path = '/' . $this->slug_namespace . '/' . $appName;
            array_push($paths, $path);
        }
        if( empty($paths) ) {
            return false;
        }
        $this->slug_app_directives = $paths;
        return;
    }
    
    /**
     * METHOD setSlugAppName
     *  Set the current app inside of the directive array of the command collection
     * @param type $app_directives
     * @return boolean
     */
    public function setSlugAppName( $app_directives ) {
        foreach ( $app_directives as $path ) {
           if( '/' . $this->path_sectors[1] . '/' . $this->path_sectors[2] == $path ) {
               $app_name = explode("/", $path);
               $this->slug_app_name = $app_name[2];
               return;
           }
        }
        return false;
    }
    
    /**
     * METHOD setSlugAppWebRoot
     *  Set the local directory of the app as our executable directory
     * @param type $slug_apps
     * @param type $app_name
     * @return type
     */
    public function setSlugAppWebRoot( $slug_apps, $app_name ) {
        $this->slug_app_web_root = $this->exec_root . $slug_apps->{$app_name}->sh_root;
        return;
    }
    
    /**
     * METHOD setSlugAppActionDirectives
     *  Set the url router actions for the discovered slug app
     * @param type $slug_apps
     * @param type $slug_app_name
     * @return boolean
     */
    public function setSlugAppActionDirectives ( $slug_apps, $slug_app_name ) {
        $directives = array();   
        foreach ( $slug_apps->{$slug_app_name}->action as $action => $act_body ) {
            $path = '/' . $this->slug_namespace . '/' . $slug_app_name . '/action/' . $act_body->name;
            array_push( $directives, $path );
        }
        if( empty($directives) ) {
            return false;
        }
        $this->slug_app_action_directives = $directives;
        return true;
    }
    
    /**
     * METHOD setSlugAppActions
     *  Set the actions (commands) available within the app namespace
     * @param type $slug_apps
     * @param type $slug_app_name
     */
    public function setSlugAppActions ( $slug_apps, $slug_app_name ) {
        foreach ( $slug_apps->{$slug_app_name}->action as $action => $act_body ) {
           $this->actions->{$action} = $act_body;
        } 
    }
    
    /**
     * METHOD setSlugAppAction
     *  Set the actions (command)
     * @param type $action_directives
     */
    public function setSlugAppAction( $action_directives ) {
        foreach ( $action_directives as $directive ) {        
           if( strpos( $this->path, $directive ) !== FALSE ) {
               $dirs = explode( "/", $directive);
               $dir_count = sizeof($dirs);
               $actionPath = $dirs[$dir_count-1];           
               foreach ( $this->actions as $action ) {
                   if( $action->name == $actionPath ) {
                       $this->action = $action;
                       return;
                   }
               }
           }
        }
        return false;
    }
    
    /**
     * METHOD setSlugAppExecutionDir
     *  Set the directory of the actionable command to execute
     * @param type $slug_apps
     * @param type $app_name
     */
    public function setSlugAppExecutionDir ( $slug_apps, $app_name ) {
        $this->slug_app_exec_dir = $this->exec_root . $slug_apps->{$app_name}->sh_root;
    }
    
    /**
     * METHOD setExecType
     *  Set the execution type
     * @param type $exec_type  Accepts 'sh', 'bash', 'py', 'rb', 'npm'
     */
    public function setExecType ( $exec_type = 'bash') {
        if( isset($_GET['type']) ) {
            $exec_type = $_GET['type'];
        }
        $acceptable_types = array('sh', 'bash', 'py', 'rb');
        foreach ($acceptable_types as $type) {
            if ( $exec_type == $type ) {
                $this->exec_type = $exec_type;
            }
        }
    }
    
    ##
    #
    # VALIDATORS
    #
    ##
    
    /**
     * METHOD verifyAppNamespace
     *  Verify that the app namespace requested is contained in the slug.json file
     * @param type $namespaces
     * @return boolean|\Org\SLUG\Exception
     */
    public function verifyAppNamespace( $namespaces ) {
        try {
            foreach ( $namespaces as $slug_namespace ) {
                if( $this->path_sectors[1] == $slug_namespace ) {
                    return true;
                }
             }
        } catch (Exception $ex) {
            return $ex;
        }
        return false;
    }
    
    /**
     * METHOD verifyAppPath
     *  Verify that the app is contained inside the app url directives
     * @param type $app_directives
     * @return boolean|\Org\SLUG\Exception
     */
    public function verifyAppPath( $app_directives ) {
        foreach ( $app_directives as $path ) {
           try {
               if( '/' . $this->path_sectors[1] . '/' . $this->path_sectors[2] == $path ) {
                    return true;
               }
           } catch (Exception $ex) {
               return $ex;
           }
        }
        return false;
    }
    
    /**
     * METHOD verifyAppActionPath
     *  Verify that the path_sectors array translates to the url path requested
     * @param type $app_actions
     * @return boolean|\Org\SLUG\Exception
     */
    public function verifyAppActionPath ( $app_actions ) {
        foreach ( $app_actions as $path ) {
           try {
              if( '/' . $this->path_sectors[1] . '/' . $this->path_sectors[2] . '/action/' . $this->path_sectors[4] == $path ) {
                return true;
              } 
           } catch (Exception $ex) {
              return $ex;
           }
        }
        return false;
    }
    

    /**
     * 
     * ACTIONS
     * 
     */    
    
    /**
     * METHOD addActionArg
     *  Add a string-worthy argument to the command being executed
     * @param type $i       Indicates which value in the array to add
     * @param type $value   The value to add to the array item
     */
    public function addActionArg($i=0, $value) {
        $this->args['arg' . $i] = $value;
    }
    
    /**
     * 
     * FLAGS
     * 
     */    
    
    /**
     * METHOD addActionFlag
     *  Add a string-worthy flag to the command being executed (such as "-flagname")
     * @param type $i       Indicates which value in the array to add
     * @param type $value   The value to add to the array item
     */
    public function addActionFlag($i=0, $value) {
        $this->flags['flag' . $i] = $value;
    }
}

?>
