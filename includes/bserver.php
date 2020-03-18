<?php
  $debug = false;
  header('Content-type: application/json');
  header('Access-Control-Allow-Origin: *');
  $db = new DB();

  $data = $_REQUEST['data'];

  if (isset($data)) {
	echo $db->putData(urldecode($data));
  } else {
	echo "Sorry, No data found.<br>";
  }

class DB
{
	private $db;
	function __construct()
	{
    		$db = $this->connect();
	}

	function connect()
	{
	    if ($this->db == 0)
	    {
	        require_once("/var/www/html/includes/db2convars.php");
		try {
	        /* Establish database connection */
	        	$this->db = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpwd);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (Exception $e) {
			echo "Unable to connect: " . $e->getMessage() ."<p>";
			die();
		}
	    }
	    return $this->db ;
	}

  function putData($data) {
	  $localeerror = array("English"=>"Please Enter Your Location",
							"Spanish"=>"Ingrese su ubicación");
	  $nameerror   = array("English"=>"Please Enter Your Name",
							"Spanish"=>"Por favor, escriba su nombre");
	  $emailerror  = array("English"=>"Please Enter Your Email Address",
							"Spanish"=>"Por favor, introduzca su dirección de correo electrónico");
	  $streeterror = array("English"=>"Please Enter Your Street Address",
							"Spanish"=>"Por favor, introduzca su dirección");
	  $cityerror   = array("English"=>"Please Enter Your City",
							"Spanish"=>"Por favor ingrese su ciudad");
	  $ziperror    = array("English"=>"Please Enter Your Zip Code Correctly",
							"Spanish"=>"Por favor, introduzca su código postal correctamente");
	  $agreeerror  = array("English"=>"Please Check The Box That You Agree",
							"Spanish"=>"Por favor marque la casilla que está de acuerdo");
	$ndata = json_decode($data, true);
	$lang = $ndata['language'];
	$this->isEmpty($ndata['locale'],$localeerror[$lang] ) ;
	$this->isEmpty($ndata['name'],$nameerror[$lang] ) ;
	$this->isInvalidEmail($ndata['email'],$emailerror[$lang] ) ;
	$this->isEmpty($ndata['street'],$streeterror[$lang] ) ;
	$this->isEmpty($ndata['city'],$cityerror[$lang] ) ;
	$this->isNotZip($ndata['zip'],$ziperror[$lang] ) ;
	$agree = ($this->isChecked($ndata['agree'],$agreeerror[$lang] ) ? 
				"Yes": "No") ;
	if ($this->isError())
		$resp = $this->reportErrors($lang);
	else {
		$sql = "INSERT INTO `bottle_petition`
			(`name`, `email`,`street`,`city`,`zip`,`agree`,`language`,`locale`)
				VALUES(?, ? , ?, ?, ?, ?, ?, ?) ";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array($ndata['name'],$ndata['email'],$ndata['street'],
							 $ndata['city'],$ndata['zip'],$agree,
							 $ndata['language'],$ndata['locale']));

		$resp = ($lang == "English") ? 
					"<strong>Thank you for signing the petition.</strong><br>" :
					"<strong>Gracias por firmar la petición.</strong><br>";
	}
	return $resp;
  }
    /*--- Error tracking and reporting functions ---*/

    /**
     * Add errors to the error list
     *
     * @param $field The form field where the error occurred.
     * @param $value The value of the form field with the error.
     * @param $msg The error message presented to the user.
     */
    function addError($value, $msg) {
       $this->errorList[] = array(
           "value" => $value,
           "msg" => $msg);
    }

    /**
     * Returns true if there are any error on the list, 
	 * otherwise returns false.
	 *
	 * @return true or false if errorlist exists.
     */
    function isError() {
        if ($this->errorList) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns a default HTML message listing the errors found.
	 *
	 * @return html error header text
     */
    function reportErrors($lang) {
		$errortxt = array("English"=>"<strong>We found some error(s).</strong><br>Please try again after making these changes:",
							"Spanish"=>"<strong>Encontramos algunos errores.</strong><br>Intente nuevamente después de realizar estos cambios:");
        $html = "";
        if ($this->isError()) {
            $html = $errortxt[$lang];
            $html .= "<ul>";
            foreach ($this->errorList as $err) {
                $html .= '<li>'.$err['msg']."</li>\n";
            }
            $html .= "</ul><br>";
        }
        return $html;
    }

    /**
     * Adds a $msg to the list if the form control $field is empty.
     *
     * @param $field The form field to check.
     * @param $msg The error message presented to the user.
	 * @return true if field is empty,
	 * false if not empty and adds error message to errorlist
	 *
	 * @note: should be isNotEmpty
     */
    function isEmpty($value, $msg) {
        if (!is_array($value)) {
			if (trim($value) == "") {
	            $this->addError($value, $msg);
    	        return false;
			}
			elseif ( ($this->textSize > 0) &&
				  ($sz=strlen($value) > $this->textSize) ) {
				$this->addError($value,
				$msg .=" Input data is too long.");
				return false;
			}
        } elseif (is_array($value) and empty($value)) {
            $this->addError($value, $msg);
            return false;
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                if ($item == "") {
                    $this->addError($value, $msg);
                    return false;
                }
            }
        } else {
            return true;
        }
    }

    /**
     * Adds a $msg to the list if the form control $field is not
	 * numeric.
     *
     * @param $field The form field to check.
     * @param $msg The error message presented to the user.
	 * @return true if field is numeric,
	 * false if not numeric and adds error message to errorlist
	 *
	 * @note: should be isNumeric
     */
    function isNotNumeric($value, $msg) {
        if(!is_numeric($value)) {
            $this->addError($value, $msg);
            return false;
        } else {
            return true;
        }
    }

    /**
     * Adds a $msg to the list if the form control $field is not
	 * numeric Zip Code.
     *
     * @param $field The form field to check.
     * @param $msg The error message presented to the user.
	 * @return true if field is numeric Zip Code,
	 * false if not numeric and adds error message to errorlist
	 *
	 * @note: should be isZip
     */
    function isNotZip($value, $msg) {
        $value = explode("-",$value);
        foreach ($value as $val) {
			if(!is_numeric($val)) {
				$this->addError($value, $msg);
				return false;
			}
        }
        return true;
    }

     /**
     * Adds a $msg to the list if the form control $field is less than
     * zero.
     *
     * @param $field The form field to check.
     * @param $msg The error message presented to the user.
	 * @return true if field is greater than 0
	 * false if not and adds error message to errorlist
	 *
     */
    function isLessThan0($value, $msg) {
        if(!is_numeric($value) OR $value <= 0) {
            $this->addError($value, $msg);
            return false;
        } else {
            return true;
        }
    }

   /**
     * Adds a $msg to the list if the form control $field is not a valid
     * email address.
     *
     * @param $msg The error message presented to the user.
	 * @return true if field is valid email,
	 * false if not and adds error message to errorlist
	 *
	 * @note: should be isValidEmail
     */
    function isInvalidEmail($value, $msg) {
        $pattern = "/^([a-zA-Z0-9])+([\.a-zA-Z0-9_-])*";
        $pattern .= "@([a-zA-Z0-9_-])+(\.[a-zA-Z0-9_-]+)+/";
        if(preg_match($pattern, $value)) {
            return true;
        } else {
            $this->addError($value, $msg);
            return false;
        }
    }

   /**
     * Adds a $msg to the list if the $value is not checked
     *
     * @param $msg The error message presented to the user.
	 * @return true if field is checked,
	 * false if not and adds error message to errorlist
	 *
     */
    function isChecked($value, $msg) {
        if($value) {
            return true;
        } else {
            $this->addError($value, $msg);
            return false;
        }
    }
}
