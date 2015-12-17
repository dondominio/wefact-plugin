<?php

require_once( "3rdparty/domain/IRegistrar.php" );
require_once( "3rdparty/domain/standardfunctions.php" );

require_once( "countries.php" );
require_once( "lib/sdk/DonDominioAPI.php" );

/**
 * -------------------------------------------------------------------------------------
 * DonDominio - IRegistrar
 * 
 * Author		: Soluciones Corporativas IP SLU
 * Copyright	: 2015 Soluciones Corporativas IP SLU
 * Version 		: 1.0
 * 
 * CHANGE LOG:
 * -------------------------------------------------------------------------------------
 *  2015-09-15		Miky			Initial version
 * -------------------------------------------------------------------------------------
 */

class DonDominio implements IRegistrar
{
    public $User;
    public $Password;
    
    public $Error;
    public $Warning;
    public $Success;

    public $Period = 1;
    public $registrarHandles = array();
    
    private $ClassName;
    
    protected $dondominio = null;
    
    /**
     * Class constructor.
     */
	function __construct( )
	{
		$this->ClassName = __CLASS__;
		
		$this->Error = array();
		$this->Warning = array();
		$this->Success = array();
	}
	
	/**
	 * Initialize the DonDominio client.
	 */
	function init()
	{
		if( is_object( $this->dondominio )){
			return $this->dondominio;
		}
		
		//API initialization
		try{
			$this->dondominio = new DonDominioAPI( array(
				'apiuser' => $this->User,
				'apipasswd' => $this->Password,
				'endpoint' => 'https://simple-api.dondominio.net',
				'autoValidate' => true,
				'versionCheck' => true,
				'response' => array(
					'throwExceptions' => true
				),
				'userAgent' => array(
					'WeFactRegistrarPlugin' => dd_getVersion()
				)
			));
		}catch( DonDominioAPI_Error $e ){
			die( 'DonDominio: Invalid username or password' );
		}
		
		return $this->dondominio;
	}
	
	function dd_getVersion()
	{
		$versionFile = __DIR__ . '/version.json';
		
		if( !file_exists( $versionFile )){
			return 'unknown';
		}
		
		$json = @file_get_contents( $versionFile );
		
		if( empty( $json )){
			return 'unknown';
		}
		
		$versionInfo = json_decode( $json, true );
		
		if( !is_array( $versionInfo ) || !array_key_exists( 'version', $versionInfo )){
			return 'unknown';
		}
		
		return $versionInfo['version'];
	}

	/**
	 * Check whether a domain is already regestered or not. 
	 * 
	 * @param 	string	 $domain	The name of the domain that needs to be checked.
	 * @return 	boolean 			True if free, False if not free, False and $this->Error[] in case of error.
	 */
	function checkDomain( $domain )
	{
		$dondominio = $this->init();
		
		try{
			$my_result = $dondominio->domain_check( $domain );
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = $e->getMessage();
			
			return false;
		}
		
		$results = $my_result->get( 'domains' );
		
		$domain = $results[0];
		
		return $domain['available'];
	}

	/**
	 * Map the ID number to an organization type.
	 *
	 * @param string $identNumber ID Number
	 * @return string
	 */
	function mapOrgType( $identNumber )
	{
		$letter = substr($identNumber, 0, 1);
	
		if(is_numeric($letter)){
			return "1";
		}
		
		switch($letter){
		case 'A':
			return "524";
			break;
		case 'B':
			return "612";
			break;
		case 'C':
			return "560";
			break;
		case 'D':
			return "562";
			break;
		case 'E':
			return "150";
			break;
		case 'F':
			return "566";
			break;
		case 'G':
			return "47";
			break;
		case 'J':
			return "554";
			break;
		case 'P':
			return "747";
			break;
		case 'Q':
			return "746";
			break;
		case 'R':
			return "164";
			break;
		case 'S':
			return "436";
			break;
		case 'U':
			return "717";
			break;
		case 'V':
			return "877";
			break;
		case 'N':
		case 'W':
			return "713";
			break;
		case 'X':
		case 'Y':
		case 'Z':
			return "1";
		}
		
		return "877";
	}
	
	/**
	 * Return the type of contact based on the ID Number.
	 *
	 * @param string $identNumber ID Number
	 * @return string
	 */
	function mapContactType( $identNumber )
	{
		$letter = substr($identNumber, 0, 1);
	
		if( is_numeric( $letter )){
			return 'individual';
		}
		
		switch( $letter ){
		case 'X':
		case 'Y':
		case 'Z':
			return 'individual';
			break;
		default:
			return 'organization';
			break;
		}
		
		return 'individual';
	}
	
	/**
	 * Build contact array information from the WHOIS object.
	 *
	 * @param object $whois WHOIS object from WeFact
	 * @return array
	 */
	function buildContactData( $whois )
	{
		$contactData = array(
			'ownerContactType' => $this->mapContactType( $whois->ownercustom->vatnumber ),
			'ownerContactFirstName' => $whois->ownerInitials,
			'ownerContactLastName' => $whois->ownerSurName,
			'ownerContactIdentNumber' => $whois->ownercustom->vatnumber,
			'ownerContactOrgName' => $whois->ownerCompanyName,
			'ownerContactOrgType' => $this->mapOrgType( $whois->ownercustom->vatnumber ),
			'ownerContactEmail' => $whois->ownerEmailAddress,
			'ownerContactPhone' => $this->getPhone( $whois->ownerCountry, $whois->ownerPhoneNumber ),
			'ownerContactAddress' => $whois->ownerAddress,
			'ownerContactPostalCode' => $whois->ownerZipCode,
			'ownerContactCity' => $whois->ownerCity,
			'ownerContactState' => $whois->ownerState,
			'ownerContactCountry' => $whois->ownerCountry,
			
			'adminContactType' => $this->mapContactType( $whois->admincustom->vatnumber ),
			'adminContactFirstName' => $whois->adminInitials,
			'adminContactLastName' => $whois->adminSurName,
			'adminContactIdentNumber' => $whois->admincustom->vatnumber,
			'adminContactOrgName' => $whois->adminCompanyName,
			'adminContactOrgType' => $this->mapOrgType ( $whois->admincustom->vatnumber ),
			'adminContactEmail' => $whois->adminEmailAddress,
			'adminContactPhone' => $this->getPhone( $whois->adminCountry, $whois->adminPhoneNumber ),
			'adminContactAddress' => $whois->adminAddress,
			'adminContactPostalCode' => $whois->adminZipCode,
			'adminContactCity' => $whois->adminCity,
			'adminContactState' => $whois->adminState,
			'adminContactCountry' => $whois->adminCountry,
			
			'techContactType' => $this->mapContactType( $whois->techcustom->vatnumber ),
			'techContactFirstName' => $whois->techInitials,
			'techContactLastName' => $whois->techSurName,
			'techContactIdentNumber' => $whois->techcustom->vatnumber,
			'techContactOrgName' => $whois->techCompanyName,
			'techContactOrgType' => $this->mapOrgType( $whois->techcustom->vatnumber ),
			'techContactEmail' => $whois->techEmailAddress,
			'techContactPhone' => $this->getPhone( $whois->techCountry, $whois->techPhoneNumber ),
			'techContactAddress' => $whois->techAddress,
			'techContactPostalCode' => $whois->techZipCode,
			'techContactCity' => $whois->techCity,
			'techContactState' => $whois->techState,
			'techContactCountry' => $whois->techCountry
		);
		
		return $contactData;
	}
	
	/**
	 * Parse phone number to make it comply with API formats.
	 *
	 * @param	string	$country		Country 2-character code
	 * @param	string	$phone			Phone number
	 * @return	string					Formatted phone number
	 */
	function getPhone( $country, $phone )
	{
		$countries = CountriesArray::get( 'alpha2', 'isd' );
		
		$prefix = $countries[$country];
		
		if( empty( $prefix ) || strlen( $prefix ) == 0 ){
			return $phone;
		}
		
		$prepend = 0;
		
		if( substr( $phone, 0, 1 ) == '+' ){
			$prepend = 1;
		}
		
		if( substr( $phone, 0, 2 ) == '00' ){
			$prepend = 2;
		}
		
		if( substr( $phone, 0, 1 ) == '0' ){
			$prepend = 1;
		}
		
		$supposed_prefix = substr( $phone, $prepend, strlen( $prefix ));
		
		$new_phone = '+' . $prefix . '.';
		
		if( $supposed_prefix == $prefix ){
			$new_phone .= substr( $phone, strlen( $supposed_prefix ));
		}else{
			$new_phone .= substr( $phone, $prepend );
		}
		
		return $new_phone;
	}
	
	/**
	 * Register a new domain
	 * 
	 * @param 	string	$domain			The domainname that needs to be registered.
	 * @param 	array	$nameservers	The nameservers for the new domain.
	 * @param 	array	$whois			The customer information for the domain's whois information.
	 * @return 	bool					True on success; False otherwise.
	 */
	function registerDomain( $domain, $nameservers = array(), $whois = null )
	{
		$dondominio = $this->init();
						
		$contactData = $this->buildContactData( $whois );
				
		if( $contactData === false ){
			$this->Error[] = 'DonDominio: No valid contact information found.';
			
			return false;
		}
		
		if( empty( $contactData['ownerContactIdentNumber'] )){
			$this->Error[] = 'DonDominio: No VAT Number found for Owner contact. Please, make sure that you have created the required custom fields.';
			
			return false;
		}
		
		$nameservers_tmp = array();
		
		foreach( $nameservers as $key=>$nameserver ){
			if( !strpos( $key, 'ip') && !empty( $nameserver )){
				$nameservers_tmp[] = $nameserver;
			}
		}
		
		$nameservers_string = implode( ',', $nameservers_tmp );
		
		if( empty( $this->Period )){
			$this->Period = 1;
		}
				
		$domain_data = array_merge(
			array(
				'period' => $this->Period,
				'nameservers' => $nameservers_string,
				'aeroId' => ( $whois->ownercustom->aeroid ) ? $whois->ownercustom->aeroid : '',
				'aeroPass' => ( $whois->ownercustom->aeropass ) ? $whois->ownercustom->aeropass : '',
				'domainIntendedUse' => ( $whois->ownercustom->intendeduse ) ? $whois->ownercustom->intendeduse : '',
				'coopCVC' => ( $whois->ownercustom->cvc ) ? $whois->ownercustom->cvc : '',
				'ownerDateOfBirth' => ( $whois->ownercustom->dateofbirth ) ? $whois->ownercustom->dateofbirth : '',
				'ownerPlaceOfBirth' => ( $whois->ownercustom->placeofbirth ) ? $whois->ownercustom->placeofbirth : '',
				'lawaccid' => ( $whois->ownercustom->accid ) ? $whois->ownercustom->accid : '',
				'lawaccbody' => ( $whois->ownercustom->accbody ) ? $whois->ownercustom->accbody : '',
				'lawaccyear' => ( $whois->ownercustom->accyear ) ? $whois->ownercustom->accyear : '',
				'lawaccjurcc' => ( $whois->ownercustom->accjurcc ) ? $whois->ownercustom->accjurcc : '',
				'lawaccjurst' => ( $whois->ownercustom->accjurst ) ? $whois->ownercustom->accjurst : '',
				'jobsOwnerWebsite' => ( $whois->ownercustom->ownerwebsite ) ? $whois->ownercustom->ownerwebsite : '',
				'jobsAdminWebsite' => ( $whois->ownercustom->adminwebsite ) ? $whois->ownercustom->adminwebsite : '',
				'jobsTechWebsite' => ( $whois->ownercustom->techwebsite ) ? $whois->ownercustom->techwebsite : '',
				'jobsBillingWebsite' => ( $whois->ownercustom->billingwebsite ) ? $whois->ownercustom->billingwebsite : '',
				'coreContactInfo' => ( $whois->ownercustom->contactinfo ) ? $whois->ownercustom->contactinfo : '',
				'proProfession' => ( $whois->ownercustom->profession ) ? $whois->ownercustom->profession : '',
				'ruIssuer' => ( $whois->ownercustom->issuer ) ? $whois->ownercustom->issuer : '',
				'ruIssuerDate' => ( $whois->ownercustom->issuedate ) ? $whois->ownercustom->issuedate : '',
				'travelUIN' => ( $whois->ownercustom->uin ) ? $whois->ownercustom->uin : '',
				'xxxClass' => ( $whois->ownercustom->xxxclass ) ? $whois->ownercustom->xxxclass : '',
				'xxxId' => ( $whois->ownercustom->xxxid ) ? $whois->ownercustom->xxxid : ''
			),
			( is_array( $contactData )) ? $contactData : array()
		);
		
		try{
			$register = $dondominio->domain_create( $domain, $domain_data );
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Found error while registering domain ' . $domain . ': ' . $e->getMessage();
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * Transfer a domain to the given user.
	 * 
	 * @param 	string 	$domain			The demainname that needs to be transfered.
	 * @param 	array	$nameservers	The nameservers for the tranfered domain.
	 * @param 	array	$whois			The contact information for the new owner, admin, tech and billing contact.
	 * @return 	bool					True on success; False otherwise;
	 */
	function transferDomain( $domain, $nameservers = array(), $whois = null, $authcode = "" )
	{
		$dondominio = $this->init();
		
		$contactData = $this->buildContactData( $whois );
		
		if( $contactData === false ){
			$this->Error[] = 'DonDominio: No valid contact information found.';
			
			return false;
		}
		
		if( empty( $contactData['ownerContactIdentNumber'] )){
			$this->Error[] = 'DonDominio: No VAT Number found for Owner contact. Please, make sure that you have created the required custom fields.';
			
			return false;
		}
		
		$nameservers_string = '';
		
		foreach( $nameservers as $key=>$nameserver ){
			if( !strpos( $key, 'ip')){
				$nameservers_string .= $nameserver;
			}
		}
		
		$domain_data = array_merge(
			array(
				'authcode' => $authcode,
				'nameservers' => $nameservers_string,
				'aeroId' => ( $whois->ownercustom->aeroid ) ? $whois->ownercustom->aeroid : '',
				'aeroPass' => ( $whois->ownercustom->aeropass ) ? $whois->ownercustom->aeropass : '',
				'domainIntendedUse' => ( $whois->ownercustom->intendeduse ) ? $whois->ownercustom->intendeduse : '',
				'coopCVC' => ( $whois->ownercustom->cvc ) ? $whois->ownercustom->cvc : '',
				'ownerDateOfBirth' => ( $whois->ownercustom->dateofbirth ) ? $whois->ownercustom->dateofbirth : '',
				'ownerPlaceOfBirth' => ( $whois->ownercustom->placeofbirth ) ? $whois->ownercustom->placeofbirth : '',
				'lawaccid' => ( $whois->ownercustom->accid ) ? $whois->ownercustom->accid : '',
				'lawaccbody' => ( $whois->ownercustom->accbody ) ? $whois->ownercustom->accbody : '',
				'lawaccyear' => ( $whois->ownercustom->accyear ) ? $whois->ownercustom->accyear : '',
				'lawaccjurcc' => ( $whois->ownercustom->accjurcc ) ? $whois->ownercustom->accjurcc : '',
				'lawaccjurst' => ( $whois->ownercustom->accjurst ) ? $whois->ownercustom->accjurst : '',
				'jobsOwnerWebsite' => ( $whois->ownercustom->ownerwebsite ) ? $whois->ownercustom->ownerwebsite : '',
				'jobsAdminWebsite' => ( $whois->ownercustom->adminwebsite ) ? $whois->ownercustom->adminwebsite : '',
				'jobsTechWebsite' => ( $whois->ownercustom->techwebsite ) ? $whois->ownercustom->techwebsite : '',
				'jobsBillingWebsite' => ( $whois->ownercustom->billingwebsite ) ? $whois->ownercustom->billingwebsite : '',
				'coreContactInfo' => ( $whois->ownercustom->contactinfo ) ? $whois->ownercustom->contactinfo : '',
				'proProfession' => ( $whois->ownercustom->profession ) ? $whois->ownercustom->profession : '',
				'ruIssuer' => ( $whois->ownercustom->issuer ) ? $whois->ownercustom->issuer : '',
				'ruIssuerDate' => ( $whois->ownercustom->issuedate ) ? $whois->ownercustom->issuedate : '',
				'travelUIN' => ( $whois->ownercustom->uin ) ? $whois->ownercustom->uin : '',
				'xxxClass' => ( $whois->ownercustom->class ) ? $whois->ownercustom->class : ''
			),
			( is_array( $contactData )) ? $contactData : array()
		);
		
		try{
			$register = $dondominio->domain_create( $domain, $domain_data );
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Error while transferring domain ' . $domain . ': ' . $e->getMessage();
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * Delete a domain
	 * 
	 * @param 	string $domain		The name of the domain that you want to delete.
	 * @param 	string $delType     end|now
	 * @return	bool				True if the domain was succesfully removed; False otherwise; 
	 */
	function deleteDomain( $domain, $delType = 'end' )
	{
		$dondominio = $this->init();
		
		$this->Warning[] = "Deleting domains is not supported by the API.";
		
		return false;
	}
	

	/**
	 * Get all available information of the given domain
	 * 
	 * @param 	mixed 	$domain		The domain for which the information is requested.
	 * @return	array				The array containing all information about the given domain
	 */
	function getDomainInformation( $domain )
	{
		$dondominio = $this->init();
		
		//Domain Status information
		try{
			$domain_status = $dondominio->domain_getInfo( $domain, array( 'infoType' => 'status' ));
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = "DonDominio: Error while requesting domain status information.";
			
			return false;
		}
		
		//Nameserver information
		try{
			$domain_nameservers = $dondominio->domain_getInfo( $domain, array( 'infoType' => 'nameservers' ));
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Error while requesting domain nameservers information for ' . $domain . ': ' . $e->getMessage();
			
			return false;
		}
		
		//Contact information
		try{
			$domain_contacts = $dondominio->domain_getInfo( $domain, array( 'infoType' => 'contacts' ));
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Error while requesting domain contacts information for ' . $domain . ': ' . $e->getMessage();
		}
		
		//Authcode information
		try{
			$domain_authcode = $dondominio->domain_getInfo( $domain, array( 'infoType' => 'authcode' ));
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Error while requesting domain authcode information for ' . $domain . ': ' . $e->getMessage();
			
			return false;
		}
		
		if( is_array( $domain_nameservers->get( "nameservers" ))){
			foreach( $domain_nameservers->get( "nameservers" ) as $nameserver ){
				$nameservers_array[] = $nameserver['name'];
			}
		}
		
		$whois = new whois();
		$whois->ownerHandle = $domain_contacts->get( "contactOwner" )['contactID'];
		$whois->adminHandle = $domain_contacts->get( "contactAdmin" )['contactID'];
		$whois->techHandle = $domain_contacts->get( "contactTech" )['contactID'];
		
		$response = array(
			'Domain' => $domain,
			'Information' => array(
				'nameservers' => $nameservers_array,
				'whois' => $whois,
				'expires' => rewrite_date_db2site( $domain_status->get( "tsExpir" )),
				'regdate' => rewrite_date_db2site( $domain_status->get( "tsCreate" )),
				'authkey' => $domain_authcode->get( "authcode" )
			)
		);
		
		return $response;
	}
	
	/**
	 * Get a list of all the domains.
	 * 
	 * @param 	string 	$contactHandle		The handle of a contact, so the list could be filtered (usefull for updating domain whois data)
	 * @return	array						A list of all domains available in the system.
	 */
	function getDomainList( $contactHandle = "" )
	{
		$dondominio = $this->init();
		
		$domain_array = array();
		
		try{
			do{
				$domain_list = $dondominio->domain_getList( array(
					'pageLength' => 1000,
					'page' => $page
				));
				
				$queryInfo = $domain_list->get( 'queryInfo ');
				
				$domain_array = array_merge(
					$domain_array,
					$domain_list->get( 'domains' )
				);
				
				$page++;
			}while( $queryInfo['total'] > count( $domain_array ));
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Error found while retrieving domain list: ' . $e->getMessage();
			
			return false;
		}
		
		$final_domain_array = array();
		
		foreach( $domain_array as $domain ){
			try{
				$domain_info = $dondominio->domain_getInfo( $domain['name'], array( 'infoType' => 'status' ));
				$domain_contacts = $dondominio->domain_getInfo( $domain['name'], array( 'infoType' => 'contact' ));
				$domain_nameservers = $dondominio->domain_getInfo( $domain['name'], array( 'infoType' => 'nameservers' ));
				$domain_authcode = $dondominio->domain_getInfo( $domain['name'], array( 'infoType' => 'authcode' ));
			}catch( DonDominioAPI_Error $e ){
				$this->Warning[] = 'DonDominio: Error found while retrieving domain information for ' . $domain['name'] . ': ' . $e->getMessage();
			}
			
			$nameservers_array = array();
			
			foreach( $domain_nameservers->get( 'nameservers') as $nameserver ){
				$nameservers_array[] = $nameserver['name'];
			}
			
			$contactOwner = $domain_contacts->get( 'contactOwner' );
			$contactAdmin = $domain_contacts->get( 'contactADmin' );
			$contactTech = $domain_contacts->get( 'contactTech' );
			
			$whois = new whois;
			$whois->ownerHandle = $contactOwner['contactID'];
			$whois->ownerInitials = $contactOWnert['firstName'];
			$whois->adminHandle = $contactAdmin['contactID'];
			$whois->techHandle = $contactTech['contactID'];
			
			$final_domain_array[] = array(
				'Domain' => $domain['name'],
				'Information' => array(
					'nameservers' => $nameservers_array,
					'whois' => $whois,
					'expires' => rewrite_date_db2site( $domain_info->get( 'tsExpir' )),
					'regdate' => rewrite_date_db2site( $domain_info->get( 'tsCreate' )),
					'authkey' => $domain_authcode->get( 'authcode ')
				)
			);
		}
		
		return $final_domain_array;
	}
	
	/**
	 * Change the lock status of the specified domain.
	 * 
	 * @param 	string 	$domain		The domain to change the lock state for
	 * @param 	bool 	$lock		The new lock state (True|False)
	 * @return	bool				True is the lock state was changed succesfully
	 */
	function lockDomain( $domain, $lock = true )
	{
		$dondominio = $this->init();
		
		try{
			$lock = $dondominio->domain_update( $domain, array( 'updateType' => 'transferBlock', 'transferBlock' => $lock ));
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Error while setting up the transfer lock status for domain' . $domain . ': ' . $e->getMessage();
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * Change the autorenew state of the given domain. When autorenew is enabled, the domain will be extended.
	 * 
	 * @param 	string	$domain			The domainname to change the autorenew setting for,
	 * @param 	bool	$autorenew		The new autorenew setting (True = On|False = Off)
	 * @return	bool					True when the setting is succesfully changed; False otherwise
	 */
	function setDomainAutoRenew( $domain, $autorenew = true )
	{
		$dondominio = $this->init();
		
		$this->Warning[] = "DonDominio does not support enabling or disabling AutoRenew through the API.";
		
		return false;
	}
	
	/**
	 * Get EPP code/token
	 * 
	 * @param mixed $domain
	 * @return 
	 */
	public function getToken( $domain )
	{
		$dondominio = $this->init();
		
		try{
			$authcode = $dondominio->domain_getInfo( $domain, array( 'infoType' => 'authcode' ));
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Error while requesting domain authcode details for ' . $domain . ': ' . $e->getMessage();
			
			return false;
		}
		
		return $authcode->get( "authcode" );
	}
	
	/**
	 * Update the domain Whois data, but only if no handles are used by the registrar.
	 * 
	 * @param mixed $domain
	 * @param mixed $whois
	 * @return boolean True if succesfull, false otherwise
	 */
	function updateDomainWhois( $domain, $whois )
	{
		$dondominio = $this->init();
		
		try{
			$update = $dondominio->domain_updateContacts( $domain, $this->buildContactData( $whois ));
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Error updating domain contacts: ' . $e->getMessage();
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * get domain whois handles
	 * 
	 * @param mixed $domain
	 * @return array with handles
	 */
	function getDomainWhois( $domain )
	{
		$dondominio = $this->init();
		
		try{
			$domain_info = $dondominio->domain_getInfo( $domain, array( 'infoType' => 'contacts' ));
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Error retrieving domain whois information for ' . $domain . ': ' . $e->getMessage();
			
			return false;
		}
		
		$ownerContact = $domain_info->get( 'contactOwner' );
		$adminContact = $domain_info->get( 'contactAdmin' );
		$techContact = $domain_info->get( 'techContact' );
		$billingContact = $domain_info->get( 'billingContact' );
		
		$contacts = array(
			'ownerHandle' => $ownerContact['contactID'],
			'adminHandle' => $ownerContact['contactID'],
			'techHandle' => $ownerContact['contactID']
		);
		
		return $contacts;
	}
	
	/**
	 * Create a new whois contact
	 * 
	 * @param 	array		 $whois		The whois information for the new contact.
	 * @param 	mixed 	 	 $type		The contact type. This is only used to access the right data in the $whois object.
	 * @return	bool					Handle when the new contact was created succesfully; False otherwise.		
	 */
	function createContact( $whois, $type = HANDLE_OWNER )
	{
		$dondominio = $this->init();
		
		$this->Warning[] = 'DonDominio: The API does not support creating contacts.';
		
		return false;
	}
	
	/**
	 * Update the whois information for the given contact person.
	 * 
	 * @param string $handle	The handle of the contact to be changed.
	 * @param array $whois The new whois information for the given contact.
	 * @param mixed $type The of contact. This is used to access the right fields in the whois array
	 * @return
	 */
	function updateContact( $handle, $whois, $type = HANDLE_OWNER )
	{
		$dondominio = $this->init();
		
		$this->Warning[] = 'DonDominio: The API does not support updating contact information.';
		
		return false;
	}
	
	/**
     * Get information availabe of the requested contact.
     * 
     * @param string $handle The handle of the contact to request.
     * @return array Information available about the requested contact.
     */
    function getContact( $handle )
    {
    	$dondominio = $this->init();
    	
    	try{
    		$contact_info = $dondominio->contact_getInfo( $handle );
    	}catch( DonDominioAPI_Error $e ){
    		$this->Error[] = 'DonDominio: Error found while retrieving contact information: ' . $e->getMessage();
    		
    		return false;
    	}
    	
    	$whois = new whois;
    	
    	$whois->ownerCompanyName 	= $contact_info->get( 'orgName' );
    	$whois->ownerInitials 		= $contact_info->get( 'firstName' );
    	$whois->ownerSurName		= $contact_info->get( 'lastName' );
    	$whois->ownerAddress		= $contact_info->get( 'address' );
    	$whois->ownerZipCode		= $contact_info->get( 'postalCode' );
    	$whois->ownerCity			= $contact_info->get( 'city' );
    	$whois->ownerState			= $contact_info->get( 'state' );
    	$whois->ownerCountry		= $contact_info->get( 'country' );
    	$whois->ownerPhoneNumber	= $contact_info->get( 'phone' );
    	$whois->ownerFaxNumber		= $contact_info->get( 'fax' );
    	$whois->ownerEmailAddress	= $contact_info->get( 'email' );
    	
    	return $whois;
	}
		
	
	/**
     * Get the handle of a contact.
     * 
     * @param array $whois The whois information of contact
     * @param string $type The type of person. This is used to access the right fields in the whois object.
     * @return string handle of the requested contact; False if the contact could not be found.
     */
    function getContactHandle( $whois = array(), $type = HANDLE_OWNER )
    {
    	$dondominio = $this->init();
    	
    	switch($type) {
			case HANDLE_OWNER:  $prefix = "owner";  break;	
			case HANDLE_ADMIN:  $prefix = "admin";  break;	
			case HANDLE_TECH:   $prefix = "tech";   break;	
			default:            $prefix = "";       break;	
		}
		
		$variable = $prefix . 'EmailAddress';
		
		try{
			$contact_found = $dondominio->contact_getList( array(
				'pageLength' => 1,
				'page' => 1,
				'email' => $whois->$variable
			));
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Error while retrieving contact information: ' . $e->getMessage();
			
			return false;
		}
		
		if( $contact_found->get( 'results' ) == 0 ){
			return false;
		}
		
		$contacts = $contact_found->get( 'contacts' );
		
		if( count( $contacts ) == 0 ){
			return false;
		}
		
		$contact = $contacts[0];
		
		return $contact['contactID'];
   	}
	
	/**
     * Get a list of contact handles available
     * 
     * @param string $surname Surname to limit the number of records in the list.
     * @return array List of all contact matching the $surname search criteria.
     */
    function getContactList( $surname = "" )
    {
    	$dondominio = $this->init();
    	
    	//Retrieving all contacts
    	try{
			do{
				
				$contacts = $dondominio->contact_getList( array(
					'pageLength' => 1000,
					'page' => $page,
				));
				
				$queryInfo = $contacts->get( 'queryInfo ');
				
				$contacts_array = array_merge(
					$contacts_array,
					$contacts->get( 'contacts' )
				);
				
				$page++;
			}while( $queryInfo['total'] > count( $contacts_array ));
		}catch( DonDominioAPI_Error $e ){
			$this->Error[] = 'DonDominio: Error while retrieving contact list: ' . $e->getMessage();
			
			return false;
		}
		
		//Array to hold contact list
		$contact_list = array();
		
		//Retrieving more information for contacts
		foreach( $contacts_array as $contact ){
			try{
				$contact_info = $dondominio->contact_getInfo( $contact['ContactID'] );
			}catch( DonDominioAPI_Error $e ){
				$this->Error[] = 'DonDominio: Error while retrieving contact: ' . $e->getMessage();
				
				return false;
			}
			
			$contact_list[] = array(
				'Handle' => $contact_info->get( 'contactID' ),
				'CompanyName' => $contact_info->get( 'orgName' ),
				'SurName' => $contact_info->get( 'lastName' ),
				'Initials' => $contact_info->get( 'firstName' )
			);
		}
		
		return $contact_list;
   	}

	/**
   	 * Update the nameservers for the given domain.
   	 * 
   	 * @param string $domain The domain to be changed.
   	 * @param array $nameservers The new set of nameservers.
   	 * @return bool True if the update was succesfull; False otherwise;
   	 */
   	function updateNameServers( $domain, $nameservers = array() )
   	{
   		$dondominio = $this->init();
   		
   		$nameservers_array = array();
		
		foreach( $nameservers as $key=>$nameserver ){
			if( !strpos( $key, 'ip' )){
				$nameservers_array[] = $nameserver;
			}
		}
		
   		try{
   			$update = $dondominio->domain_updateNameServers( $domain, $nameservers_array );
   		}catch( DonDominioAPI_Error $e ){
   			$this->Error[] = 'DonDominio: Error while updating Nameservers: ' . $e->getMessage();
   			
   			return false;
   		}
   		
   		return true;
	}
	
	/**
	 * Get class version information.
	 * 
	 * @return array()
	 */
	static function getVersionInformation()
	{
		$dondominio = $this->init();
		
		$version['name']            = "DonDominio Registrar Plugin";
		$version['api_version']     = "0.9.8";
		$version['date']            = "2015-09-15"; // Last modification date
		$version['wefact_version']  = "1.0"; // Version released for WeFact
		$version['autorenew']       = true; // AutoRenew is default?  true | false
		$version['handle_support']  = false; // Handles are supported? true | false
		$version['cancel_direct']   = false; // Possible to terminate domains immediately?  true | false
		$version['cancel_expire']   = false; // Possible to stop auto-renew for domains? true | false
		
		// Information for customer (will be showed at registrar-show-page)
		$version['dev_logo']		= 'http://www.dondominio.com/images/head-logo-dondominio.png'; // URL to your logo
		$version['dev_author']		= 'Soluciones Corporativas IP SLU'; // Your companyname
		$version['dev_website']		= 'https://www.dondominio.com'; // URL website
		$version['dev_email']		= 'info@dondominio.com'; // Your e-mailaddress for support questions
		
		return $version;	
	}	
	
}
?>