<?php
//declare(strict_types=1);

namespace SimpleSAML\Module\zimbraauth\Auth\Source;

use Exception;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Error;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Utils;

/*

LDAP authentication source - username & password for Zimbra.

Set-up in config/authsources.php as follows:

'zimbra' => array (
    'zimbraauth:ZimbraAuth',
    'zimbraServer' => 'zimbraserver.example.com',
    'zimbraPort' => 389
)

Enable the module in config/config.php, add zimbraauth to module.enable as following example:
     'module.enable' => [
           'admin' => true,
           'zimbraauth' => true,
     ],

This file should be in : modules/zimbraauth/src/Auth/Source/ZimbraAuth.php don't forget:
 
chown www-data:www-data modules/ -R

Assuming you installed SimpleSAMLPhp in simplesaml Apache location, you can test the authentication source via:
https://your-saml-server.example.com/simplesaml/module.php/admin/test/zimbra
First login using your SimpleSAMLPhp admin credentials and then use a Zimbra account for testing.

1. The Zimbra authentication module requires users to use their full email address to log-in.
2. Login by the use of an alias email address is not and will not be supported.
3. This module requires TLS for the connection between this module and the Zimbra LDAP.
4. This module was designed for SimpleSAMLPhp version 2.0.4.

Upon successful auth the following attributes will be available: "ou", "sn", "givenname", "mail", "uid".

If this works you can set-up SimpleSAMLPhp as an IDP and use Zimbra as the authentication source see:
https://simplesamlphp.org/docs/stable/simplesamlphp-idp.html

Bare minimum example of metadata/saml20-idp-hosted.php, notice 'auth'=>'zimbra' is what tells SimpleSAMLPhp
to use the Zimbra authentication module:

```
<?php
$metadata['https://your-saml-server.example.com/simplesaml/saml2/idp/metadata.php'] = [
   'host' => '__DEFAULT__',
   'privatekey' => 'server.pem',
   'certificate' => 'server.crt',
   'auth' => 'zimbra',
];
```
*/


class ZimbraAuth extends UserPassBase
{
    /**
     * Constructor for this authentication source.
     *
     * @param array $info  Information about this authentication source.
     * @param array $config  Configuration.
     */
    public function __construct(array $info, array $config)
    {
        // Call the parent constructor first, as required by the interface
        parent::__construct($info, $config);
        $this->config = $config;
    }

   protected function login(string $username, string $password): array
   {
      $username = strtolower($username);
      $authresult= $this->verifyLdapUser($username, $password);

      if($authresult)
      {
         return $authresult;
      }
      else
      {
         throw new \SimpleSAML\Error\Error('WRONGUSERPASS');
      }
   }

   public function logout(array &$state): void
   {
   }

   public function verifyLdapUser($username,$password)
   {
      $ds=@ldap_connect($this->config['zimbraServer'], $this->config['zimbraPort']);
      ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
      ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
      ldap_set_option($ds, LDAP_OPT_NETWORK_TIMEOUT, 10);
      $uid = ldap_escape(explode("@",$username)[0], "", LDAP_ESCAPE_FILTER);
      $domain = ldap_escape(explode("@",$username)[1], "", LDAP_ESCAPE_FILTER);

      $ou = "ou=people,dc=".str_replace(".",",dc=",$domain);
      $dn = "uid=".$uid.",".$ou;

      if(ldap_start_tls($ds))
      {
         $bind=@ldap_bind($ds, $dn, $password);
         if($bind)
         {
            $attributes = array("ou", "sn", "givenname", "mail", "uid");
            $sr=@ldap_search($ds, $ou, "uid=".$uid, $attributes); //this search should always give one single result.
            $info = ldap_get_entries($ds, $sr);

            //Technically the mail attribute is multi-valued, meaning if a user has alias email addresses these also are in the mail attribute.
            //since the first mail attribute == the zimbra account name, this is what we want to use for authentication if we want to use if for authentication.
            //alias email addresses should never be a part of any login flow, as this is a security risk.

            //When doing integrations, it is almost always better and more secure to work with single-value attributes, which is why we [0] everything below. 

            if($info["count"] == 1)
            {
               return [
                  'ou' => [$ou],
                  'dn' => [$dn],
                  'sn' => [$info[0]['sn'][0]],
                  'givenname' => [$info[0]['givenname'][0]],
                  'mail' => [$info[0]['mail'][0]],
                  'uid' => [$info[0]['uid'][0]]
               ];
            }
            else
            {
               echo "Fatal: LDAP search returned multiple entries, something is not right here...";
               //An LDAP search with something like dn: uid=admin,ou=people,dc=barrydegraaff,dc=nl in an OU like ou=people,dc=barrydegraaff,dc=nl should give exactly one entry... if not I dunno what happened but it can't be good.
               return false;
            }
         }
         else
         {
            return false;
         }
      }
      else
      {
         echo "Fatal: LDAP TLS failed";
         return false;
      }
   }
}
