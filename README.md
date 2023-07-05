# Zimbra authentication module for SimpleSAMLphp

While a generic LDAP authentication module for SimpleSAMLphp exists, it is hard to set-up, it requires the use and storing of an admin bind credential and is in continous development making it impossible to install a working version of this module on a released version of SimpleSAMLphp.

The Zimbra authentication module for SimpleSAMLphp makes it easy to use the Zimbra LDAP as the authentication source for your SimpleSAMLphp based SAML IDP.

1. The Zimbra authentication module requires users to use their full email address to log-in.
2. Login by the use of an alias email address is not and will not be supported.
3. This module requires TLS for the connection between this module and the Zimbra LDAP.
4. This module was designed for SimpleSAMLPhp version 2.0.4.

## 2FA
If you want to use 2FA this should be achieved with an additional SimpleSAMLPhp module. This module is not aware of Zimbra 2FA and it will ignore any Zimbra 2FA settings.

## Installing and configuration

Set-up in config/authsources.php as follows, replace `zimbraserver.example.com` with your Zimbra server hostname:

```
'zimbra' => array (
    'zimbraauth:ZimbraAuth',
    'zimbraServer' => 'zimbraserver.example.com',
    'zimbraPort' => 389
)
```

Enable the module in config/config.php, add zimbraauth to module.enable as following example:
```
     'module.enable' => [
           'admin' => true,
           'zimbraauth' => true,
     ],
```
First use the `cd` command to go into the installation folder of SimpleSAMLPhp, then install this module using:

```
mkdir -p modules/zimbraauth/src/Auth/Source/
wget https://raw.githubusercontent.com/Zimbra/zimbra-auth-module-simplesamlphp/main/ZimbraAuth.php -O modules/zimbraauth/src/Auth/Source/ZimbraAuth.php
chown www-data:www-data modules/ -R  #Ubuntu
chown apache:apache modules/ -R  #RedHat
```

Assuming you installed SimpleSAMLPhp in simplesaml Apache location, you can test the authentication source via: https://your-saml-server.example.com/simplesaml/module.php/admin/test/zimbra

First login using your SimpleSAMLPhp admin credentials and then use a Zimbra account for testing. Use the full Zimbra email address to log-in. (admin@example.com or testuser@example.com and NOT admin or testuser)

Upon successful auth the following attributes will be available: "ou", "sn", "givenname", "mail", "uid".

If this works you can set-up SimpleSAMLPhp as an IDP and use Zimbra as the authentication source see: https://simplesamlphp.org/docs/stable/simplesamlphp-idp.html

Bare minimum example of metadata/saml20-idp-hosted.php, notice 'auth'=>'zimbra' is what tells SimpleSAMLPhp to use the Zimbra authentication module:

```
<?php
$metadata['https://your-saml-server.example.com/simplesaml/saml2/idp/metadata.php'] = [
   'host' => '__DEFAULT__',
   'privatekey' => 'server.pem',
   'certificate' => 'server.crt',
   'auth' => 'zimbra',
];
```
