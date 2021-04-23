<?php
ob_start();
define('SITE_URL',site_url());
require_once( ABSPATH . 'wp-admin/includes/image.php' );
require_once( ABSPATH . 'wp-admin/includes/file.php' );
require_once( ABSPATH . 'wp-admin/includes/media.php' );

/**
 *
 * @wordpress-plugin
 * Plugin Name:       Comments
 * Description:       This Plugin contain all rest api.
 * Version:           1.0
 * Author:            Test Api
 */
 
 use Firebase\JWT\JWT;
 class CRC_REST_API extends WP_REST_Controller 
{
   	private $api_namespace;
	private $api_version;
	private $required_capability;
	public  $user_token;
	public  $user_id;
	public function __construct()
    {
		$this->api_namespace = 'api/v';
		$this->api_version = '1';
		$this->required_capability = 'read';  
		$this->init();

		/*------- Start: Validate Token Section -------*/
        
		$headers = getallheaders(); 
		if (isset($headers['Authorization']))
        { 
        	if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches))
            { 
            	$this->user_token =  $matches[1]; 
        	} 
        }

        /*------- End: Validate Token Section -------*/
	}
	 
	private function successResponse($message='',$data=array())
    {
        $response =array();
        $response['status'] = "success";
        $response['message'] =$message;
        $response['data'] = $data;
        return new WP_REST_Response($response, 200); 
    }
    private function errorResponse($message='',$type='ERROR' , $statusCode=200)
    {
        $response = array();
        $response['status'] = "error";
        $response['error_type'] = $type;
        $response['message'] =$message;
        return new WP_REST_Response($response, $statusCode); 
    }
    
    public function register_routes()
    {
		$namespace = $this->api_namespace . $this->api_version;
	    
	    $publicItems = array('insertComments','getComments');
		
		foreach($publicItems as $Item)
        {
		  	register_rest_route( $namespace, '/'.$Item, array(
			   array( 
			       'methods' => 'POST', 
			       'callback' => array( $this, $Item )
			       ),
	    	    )  
	    	);  
		}
	}
	
	public function init()
    {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'rest_api_init', function()
        {
			remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
			add_filter( 'rest_pre_serve_request', function( $value )
            {
				header( 'Access-Control-Allow-Origin: *' );
				header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE' );
				header( 'Access-Control-Allow-Credentials: true' );
				return $value;
			});
		}, 15 );
	}
	
	public function isUserExists($user)
    {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->users WHERE ID = %d", $user));
        if ($count == 1)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
        
	    public function getUserIdByToken($token)
	    {
        $decoded_array = array();
        $user_id = 0;
        if ($token)
        {
            try
            {
                $decoded = JWT::decode($token, JWT_AUTH_SECRET_KEY, array('HS256'));
                $decoded_array = (array) $decoded;
            }
            catch(\Firebase\JWT\ExpiredException $e)
            {

                return false;
            }
        } 
        if (!empty($decoded_array['data']->user->id)>0)
        {
            $user_id = $decoded_array['data']->user->id;
        }
        if ($this->isUserExists($user_id))
        {
            return $user_id;
        }
        else
        {
            return false;
        }
    }

    
    function jwt_auth($data, $user)
    {
        //print_r($data);
        unset($data['user_nicename']);
        unset($data['user_display_name']); 
        $site_url = site_url();
            $result['token'] =  $data['token'];
            return $this->successResponse('User Logged in successfully',$result);
    
    }

    private function isValidToken()
    {
    	$this->user_id  = $this->getUserIdByToken($this->user_token);
    }

    public function getProfileById($request){
      global $wpdb;
      $param = $request->get_params();
      print_r($param);
      
    }

    public function insertComments($request)
    {

	  global $wpdb;
      $param = $request->get_params();
	  $getComment = $param['get_comment'];
      $this->isValidToken();
        $user_id = !empty($this->user_id)?$this->user_id:$param['ID'];
       $data = wp_get_current_user();
       $agent = $_SERVER['HTTP_USER_AGENT'];
      	$comment_data = array(
		  'comment_post_ID' => 256,
		  'comment_author' => $data->user_login,
		  'comment_author_email' => $data->user_email,
		  'comment_content' => $getComment,
		  'comment_agent' => $agent,
		  'comment_type'  => '',
		  'comment_date' => date('Y-m-d H:i:s'),
		  'comment_date_gmt' => date('Y-m-d H:i:s'),
		  'comment_approved' => 1,
		  'user_id'=>$user_id 
		);

		if(wp_insert_comment($comment_data))
		{
			return $this->successResponse('Comment Inserted Successfully');
		}
		else
		{
			return $this->errorResponse('Comment not Inserted Successfully');
		}
    }
    public function getComments($request)
    {
    	global $wpdb;
    	$params = $request->get_params();
    	$comment_post_ID = $params['comment_post_ID'];
    	$data = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM `wp_comments` WHERE `comment_post_ID` =".$comment_post_ID."  ",""),ARRAY_A
		);

/*    	$data = get_comments(
    		array(
    			"comment_post_ID"=>$comment_post_ID
    		)
    	);
*/    	if($data)
    	{
    		return $this->successResponse('Success',$data);
    	}
    	else
    	{
    		return $this->errorResponse('Comment Id not Found');
    	}
 
    }

}

$serverApi = new CRC_REST_API();
$serverApi->init();
add_filter('jwt_auth_token_before_dispatch',array($serverApi,'jwt_auth'),10,2);

?>








