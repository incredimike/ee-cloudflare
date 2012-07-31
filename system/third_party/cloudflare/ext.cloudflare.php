<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once("ip_in_range.php");

class Cloudflare_ext {

	public	$name				= 'CloudFlare';
	public	$version			= '1.0';
	public	$description		= 'Incorporates CloudFlare functionality into ExpressionEngine';
	public	$settings_exist		= 'y';
	public	$docs_url			= ''; // 'http://expressionengine.com/user_guide/';

	public	$origin_ip 			= '';
	public	$ip_country 		= '';
	public	$cloudflare_ip		= '';
	public 	$is_cf 				= false;
	
	private $settings			= array();
	
	const	RANGE_DELIM			= "\n";
	const	UNKNOWN_COUNTRY		= 'XX';
	

	public function __construct( $settings='' )
	{
		$this->EE =& get_instance();
		$this->settings = $settings;
		$this->is_cf = ( ! empty( $_SERVER["HTTP_CF_CONNECTING_IP"] )) ? TRUE : FALSE;
	}

	/**
	 * Activate Extension
	 */
	function activate_extension()
	{
		// -------------------------------------------
		//  Add the extension hooks
		// -------------------------------------------

		$this->settings = array( 
			'cf_api_host'		=> 'ssl://www.cloudflare.com',
			'cf_api_port'		=> '443',
			'cf_ipv4_ranges'	=> "204.93.240.0/24\n204.93.177.0/24\n199.27.128.0/21\n173.245.48.0/20\n103.22.200.0/22\n141.101.64.0/18\n108.162.192.0/18\n190.93.240.1/20",
			'cf_ipv6_ranges'	=> "2400:cb00::/32\n2606:4700::/32\n2803:f800::/32",
			'overwrite_addr'	=> 'y',
		);

		$this->EE->db->insert('extensions', array(
			'class'    => get_class($this),
			'method'   => 'pre_system',
			'hook'     => 'pre_system',
			'settings' => serialize($this->settings),
			'priority' => 1,
			'version'  => $this->version,
			'enabled'  => 'y'
		));
	}


	/**
	 * Disable Extension
	 */
	function disable_extension()
	{
		// -------------------------------------------
		//  Delete the extension hooks
		// -------------------------------------------

		$this->EE->db->where('class', get_class($this))
		             ->delete('exp_extensions');
	}


	/**
	 * Update Extension
	 */
	function update_extension($current = '')
	{
		// Nothing to change...
		return FALSE;
	}
 
	/**
	 * Hook pre_system for running the CloudFlare overwites asap.
	 */
 
	function pre_system ( $data ) 
	{

		// Is the HTTP_CF_CONNECTING_IP header set? If not, then this isn't CloudFlare
		$this->is_cf = ( ! empty( $_SERVER["HTTP_CF_CONNECTING_IP"] )) ? TRUE : FALSE;

		// Set Origin IP
		$this->origin_ip = ( ! empty( $_SERVER["HTTP_CF_CONNECTING_IP"] ))
				? $_SERVER["HTTP_CF_CONNECTING_IP"]
				: UNKNOWN_COUNTRY;

		// Set country code
		$this->ip_country = ( ! empty( $_SERVER["HTTP_CF_IPCOUNTRY"] ))
				? $_SERVER["HTTP_CF_IPCOUNTRY"]
				: UNKNOWN_COUNTRY;
		
		// Set the CloudFlare service IP
		$this->cloudflare_ip = $_SERVER['REMOTE_ADDR'];
		
		// Overwrite the REMOTE_ADDR data if enabled
		if ( $this->settings['overwrite_addr'] == 'y' )
		{
			$this->_overwrite_remoteaddr();
		}
		
	}
	
	// Template Tags
	
	public function origin_ip()
	{
		return $this->origin_ip;
	}

	
	public function ip_country()
	{
		return $this->ip_country;
	}

	public function cloudflare_ip()
	{
		return $this->cloudflare_ip;
	}
	
	
	
	// Private Methods


	private function _overwrite_remoteaddr ()
	{

	    if (strpos($_SERVER["REMOTE_ADDR"], ":") === FALSE) {
			// IPV4: Update the REMOTE_ADDR value if the current REMOTE_ADDR value is in the specified range.
			$ranges = $this->settings('cf_ipv4_ranges');
			$cf_ipv4_ranges = array_map('trim', explode(RANGE_DELIM, $ranges));
			foreach ($cf_ipv4_ranges as $range) {
				if (ipv4_in_range($_SERVER["REMOTE_ADDR"], trim($range))) {
					$_SERVER["REMOTE_ADDR"] = $_SERVER["HTTP_CF_CONNECTING_IP"];
					break;
				}
			}
		}
		else {
			$ipv6 = get_ipv6_full($_SERVER["REMOTE_ADDR"]);
			$ranges = $this->settings('cf_ipv6_ranges');
			$cf_ipv6_ranges = array_map('trim', explode(RANGE_DELIM, $ranges));
			foreach ($cf_ipv6_ranges as $range) {
				if (ipv6_in_range($ipv6, $range)) {
					$_SERVER["REMOTE_ADDR"] = $_SERVER["HTTP_CF_CONNECTING_IP"];
					break;
				}
			}
		}
		
	}

	function settings()
	{
		$settings = array();

		$settings['overwrite_addr']		= array('r', array('y' => "Yes", 'n' => "No"), 'y');
		$settings['dev_mode']			= array('r', array('y' => "Yes", 'n' => "No"), 'y');

		$settings['cf_api_key']			= array('i', '', $this->settings['cf_api_key']);		
		$settings['cf_api_email']		= array('i', '', $this->settings['cf_api_email']);		
		$settings['cf_api_host']	  	= array('i', '', $this->settings['cf_api_host']);
		$settings['cf_api_port']	   	= array('i', '', $this->settings['cf_api_port']);
		$settings['cf_ipv4_ranges']   	= array('t', '', $this->settings['cf_ipv4_ranges']);
		$settings['cf_ipv6_ranges']   	= array('t', '', $this->settings['cf_ipv6_ranges']);


		return $settings;
	}

	
	
}

/** 

List of possible country returns:

A1,"Anonymous Proxy"
A2,"Satellite Provider"
O1,"Other Country"
AD,"Andorra"
AE,"United Arab Emirates"
AF,"Afghanistan"
AG,"Antigua and Barbuda"
AI,"Anguilla"
AL,"Albania"
AM,"Armenia"
AO,"Angola"
AP,"Asia/Pacific Region"
AQ,"Antarctica"
AR,"Argentina"
AS,"American Samoa"
AT,"Austria"
AU,"Australia"
AW,"Aruba"
AX,"Aland Islands"
AZ,"Azerbaijan"
BA,"Bosnia and Herzegovina"
BB,"Barbados"
BD,"Bangladesh"
BE,"Belgium"
BF,"Burkina Faso"
BG,"Bulgaria"
BH,"Bahrain"
BI,"Burundi"
BJ,"Benin"
BL,"Saint Bartelemey"
BM,"Bermuda"
BN,"Brunei Darussalam"
BO,"Bolivia"
BQ,"Bonaire, Saint Eustatius and Saba"
BR,"Brazil"
BS,"Bahamas"
BT,"Bhutan"
BV,"Bouvet Island"
BW,"Botswana"
BY,"Belarus"
BZ,"Belize"
CA,"Canada"
CC,"Cocos (Keeling) Islands"
CD,"Congo, The Democratic Republic of the"
CF,"Central African Republic"
CG,"Congo"
CH,"Switzerland"
CI,"Cote d'Ivoire"
CK,"Cook Islands"
CL,"Chile"
CM,"Cameroon"
CN,"China"
CO,"Colombia"
CR,"Costa Rica"
CU,"Cuba"
CV,"Cape Verde"
CW,"Curacao"
CX,"Christmas Island"
CY,"Cyprus"
CZ,"Czech Republic"
DE,"Germany"
DJ,"Djibouti"
DK,"Denmark"
DM,"Dominica"
DO,"Dominican Republic"
DZ,"Algeria"
EC,"Ecuador"
EE,"Estonia"
EG,"Egypt"
EH,"Western Sahara"
ER,"Eritrea"
ES,"Spain"
ET,"Ethiopia"
EU,"Europe"
FI,"Finland"
FJ,"Fiji"
FK,"Falkland Islands (Malvinas)"
FM,"Micronesia, Federated States of"
FO,"Faroe Islands"
FR,"France"
GA,"Gabon"
GB,"United Kingdom"
GD,"Grenada"
GE,"Georgia"
GF,"French Guiana"
GG,"Guernsey"
GH,"Ghana"
GI,"Gibraltar"
GL,"Greenland"
GM,"Gambia"
GN,"Guinea"
GP,"Guadeloupe"
GQ,"Equatorial Guinea"
GR,"Greece"
GS,"South Georgia and the South Sandwich Islands"
GT,"Guatemala"
GU,"Guam"
GW,"Guinea-Bissau"
GY,"Guyana"
HK,"Hong Kong"
HM,"Heard Island and McDonald Islands"
HN,"Honduras"
HR,"Croatia"
HT,"Haiti"
HU,"Hungary"
ID,"Indonesia"
IE,"Ireland"
IL,"Israel"
IM,"Isle of Man"
IN,"India"
IO,"British Indian Ocean Territory"
IQ,"Iraq"
IR,"Iran, Islamic Republic of"
IS,"Iceland"
IT,"Italy"
JE,"Jersey"
JM,"Jamaica"
JO,"Jordan"
JP,"Japan"
KE,"Kenya"
KG,"Kyrgyzstan"
KH,"Cambodia"
KI,"Kiribati"
KM,"Comoros"
KN,"Saint Kitts and Nevis"
KP,"Korea, Democratic People's Republic of"
KR,"Korea, Republic of"
KW,"Kuwait"
KY,"Cayman Islands"
KZ,"Kazakhstan"
LA,"Lao People's Democratic Republic"
LB,"Lebanon"
LC,"Saint Lucia"
LI,"Liechtenstein"
LK,"Sri Lanka"
LR,"Liberia"
LS,"Lesotho"
LT,"Lithuania"
LU,"Luxembourg"
LV,"Latvia"
LY,"Libyan Arab Jamahiriya"
MA,"Morocco"
MC,"Monaco"
MD,"Moldova, Republic of"
ME,"Montenegro"
MF,"Saint Martin"
MG,"Madagascar"
MH,"Marshall Islands"
MK,"Macedonia"
ML,"Mali"
MM,"Myanmar"
MN,"Mongolia"
MO,"Macao"
MP,"Northern Mariana Islands"
MQ,"Martinique"
MR,"Mauritania"
MS,"Montserrat"
MT,"Malta"
MU,"Mauritius"
MV,"Maldives"
MW,"Malawi"
MX,"Mexico"
MY,"Malaysia"
MZ,"Mozambique"
NA,"Namibia"
NC,"New Caledonia"
NE,"Niger"
NF,"Norfolk Island"
NG,"Nigeria"
NI,"Nicaragua"
NL,"Netherlands"
NO,"Norway"
NP,"Nepal"
NR,"Nauru"
NU,"Niue"
NZ,"New Zealand"
OM,"Oman"
PA,"Panama"
PE,"Peru"
PF,"French Polynesia"
PG,"Papua New Guinea"
PH,"Philippines"
PK,"Pakistan"
PL,"Poland"
PM,"Saint Pierre and Miquelon"
PN,"Pitcairn"
PR,"Puerto Rico"
PS,"Palestinian Territory"
PT,"Portugal"
PW,"Palau"
PY,"Paraguay"
QA,"Qatar"
RE,"Reunion"
RO,"Romania"
RS,"Serbia"
RU,"Russian Federation"
RW,"Rwanda"
SA,"Saudi Arabia"
SB,"Solomon Islands"
SC,"Seychelles"
SD,"Sudan"
SE,"Sweden"
SG,"Singapore"
SH,"Saint Helena"
SI,"Slovenia"
SJ,"Svalbard and Jan Mayen"
SK,"Slovakia"
SL,"Sierra Leone"
SM,"San Marino"
SN,"Senegal"
SO,"Somalia"
SR,"Suriname"
ST,"Sao Tome and Principe"
SV,"El Salvador"
SX,"Sint Maarten"
SY,"Syrian Arab Republic"
SZ,"Swaziland"
TC,"Turks and Caicos Islands"
TD,"Chad"
TF,"French Southern Territories"
TG,"Togo"
TH,"Thailand"
TJ,"Tajikistan"
TK,"Tokelau"
TL,"Timor-Leste"
TM,"Turkmenistan"
TN,"Tunisia"
TO,"Tonga"
TR,"Turkey"
TT,"Trinidad and Tobago"
TV,"Tuvalu"
TW,"Taiwan"
TZ,"Tanzania, United Republic of"
UA,"Ukraine"
UG,"Uganda"
UM,"United States Minor Outlying Islands"
US,"United States"
UY,"Uruguay"
UZ,"Uzbekistan"
VA,"Holy See (Vatican City State)"
VC,"Saint Vincent and the Grenadines"
VE,"Venezuela"
VG,"Virgin Islands, British"
VI,"Virgin Islands, U.S."
VN,"Vietnam"
VU,"Vanuatu"
WF,"Wallis and Futuna"
WS,"Samoa"
YE,"Yemen"
YT,"Mayotte"
ZA,"South Africa"
ZM,"Zambia"
ZW,"Zimbabwe"

*/





/* End of file pi.cloudflare.php */
/* Location: ./system/expressionengine/third_party/plugin_name/pi.cloudflare.php */