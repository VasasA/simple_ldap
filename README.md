Simple LDAP
===========

The Simple LDAP project is a set of modules to provide Backdrop integration with
an LDAPv3 server. It is an alternative to the Lightweight Directory Access
Protocol (LDAP) module, with a much narrower set of features. The goal of the
project is to provide very basic LDAP functionality which should cover most
common use cases. Any edge case functionality or site-specific requirements
should be implemented using a helper module.

The current implementation was developed against OpenLDAP, with some testing
against Active Directory. Most functionality should work with any LDAPv3
compliant server, but this is largely untested.


Installation
------------

- Install this module using the official Backdrop CMS instructions at
  https://backdropcms.org/guide/modules
- You should enable the sub-modules, to get the complete feature set. See below.
- Configuration page: Administration > Configuration > User accounts > Simple LDAP Configuration

**The project consists of one main module, and five submodules:**


Simple LDAP
-----------

This is the main module, on which all of the other modules are based. It
provides an interface to the configured LDAP directory with basic low-level
LDAP functions and no bells or whistles. It does not provide anything to
Backdrop on its own.


Simple LDAP User
----------------

This module allows authentication to the LDAP directory configured in the
Simple LDAP module. It also provides synchronization services both to and from
LDAP and Backdrop. It supports mapping LDAP attributes to Backdrop user object
fields (both native, and using Field API).

**NOTE**: You can see the users at admin/people/list page, where the status is
provided by Backdrop's database. However the status is provided by LDAP
database at the edit page of a user. These can be different.

**Configuration**
In addition to the configuration available in the administration UI, an
attribute map can be specified in `BACKDROP_ROOT/settings.php`, using the variable
`$conf['simple_ldap_user_attribute_map']`.

This variable is an array of arrays, where each of the arrays have the
following items:

* backdrop - The field name on the Backdrop user. This must be the machine name of
	   the field. To specify Field module fields, prefix the field name with a
	   hash, e.g. '#field_foo'. If no hash prefix is found, it is assumed that the
	   field is a property of the user itself, such as name, pass, mail, etc.

	   This can also be an array of Backdrop properties or fields. If the array
	   contains more than one entry, synchronization for that map only works in
	   the backdrop->ldap direction, and the fields are concatenated with a space
	   separator.

	   Note: If you are mapping a Field module field that does not store its data
	   in a 'value' column, you need to specify the name of the column in the
	   mapping itself using square brackets. See the Country example below.

* ldap - The LDAP attribute on the LDAP user.

Example:
```php
$conf['simple_ldap_user_attribute_map'] = array(

  // Generic example.
  array(
    'backdrop' => '#backdrop-user-field-machine-name',
    'ldap' => 'ldap-attribute',
  ),

  // First name example.
  array(
    'backdrop' => '#field_first_name',
    'ldap' => 'givenName',
  ),

  // Last name example.
  array(
    'backdrop' => '#field_last_name',
    'ldap' => 'sn',
  ),

  // Country example.
  array(
    'backdrop' => '#field_country[iso2]',
    'ldap' => 'localityName',
  ),

  // Timezone example (saved directly to users table, note there is no '#').
  array(
    'backdrop' => 'timezone',
    'ldap' => 'l',
  ),

  // Combined fields example.
  array(
    'backdrop' => array(
      '#field_first_name',
      '#field_last_name',
    ),
    'ldap' => 'displayName',
  ),

);
```

Active Directory Example:
```php
$conf['simple_ldap_user_attribute_map'] = array(
  array(
    'backdrop' => '#field_first_name',
    'ldap' => 'givenName',
  ),
  array(
    'backdrop' => '#field_last_name',
    'ldap' => 'sn',
  ),
  array(
    'backdrop' => array(
      '#field_first_name',
      '#field_last_name',
    ),
    'ldap' => 'CN',
  ),
  array(
    'backdrop' => array(
      '#field_first_name',
      '#field_last_name',
    ),
    'ldap' => 'displayName',
  ),
);
```


Simple LDAP Role
----------------

This module allows Backdrop roles to be derived from LDAP groups, and
vice-versa. It is dependent on the Simple LDAP User module.


Simple LDAP SSO
---------------
Simple LDAP SSO is a Single-Sign-On implementation that uses your LDAP server
to authenticate each session.

**How does it work?**
When a user logs in to any site using this module, two things occur. First,
the unique session ID that Backdrop assigns to the user is [hashed](https://en.wikipedia.org/wiki/Hash_function) and
stored in an attribute you deem on LDAP. Then, the session information
— including the user's name and session id — is encrypted and stored in a cookie.

When a user then navigates to another website configured with this SSO module,
and before the session handling occurs that determines whether a user is logged
in or not, the SSO cookie is decrypted, and the session information is saved to
the database. Then, the normal session handling occurs, and the Backdrop
session cookie is recognized and used. Finally, at the end of the bootstrap,
the session ID is validated against the hashed value stored in LDAP.
If the values do not match, the user is immediately logged out and errors are
logged.

**Requirements**
- A common base domain to use.
- The [PHP mcrypt extension](https://www.php.net/mcrypt) installed on the server.
- Read/write credentials to LDAP.

**Installation**
1. Install the module at admin/modules, or using drush.
2. You must set the session_inc variable to Simple LDAP SSO’s session include
file. Insert the following line into the `BACKDROP_ROOT/settings.php` file!
```php
$settings["session_inc"] = "modules/simple_ldap/simple_ldap_sso/simple_ldap_sso.session.inc";
```
3. Configure the module at admin/config/people/simple_ldap/sso.
4. Go to admin/reports/status to see if Simple LDAP SSO is marked as 'Configured'.

**NOTE**: All sites must use the same encryption key, cookie domain,
LDAP attribute, and session ID hashing algorithm.


Simple LDAP Active Group
------------------------
A small helper module. Adds a user to the defined LDAP group when set to
"Active" and removes the user from the group when set to "Blocked".
This module is best used when a search filter is set in Simple Ldap User
to enforce group membership.
For example: "memberOf=cn=active,ou=groups,o=example"
Administration > Configuration > User accounts > Simple LDAP Configuration > Roles tab > Default LDAP group DN

Another function:
With the "Delete LDAP entries, even if they do not match the filter"
option a user will be deleted from LDAP when deleted from Backdrop,
even if the user's DN does not match the specified search filter.
Administration > Configuration > User accounts > Simple LDAP Configuration > Users tab > Advanced > Delete LDAP entries, even ...


Simple LDAP Delete Blocked User
-------------------------------
A small helper module. Deletes a user from LDAP when set to Blocked in
Backdrop. This keeps the directory clean, and when restoring the account
to Active, the user will be resynced to LDAP by the Simple LDAP User module.


For developers
--------------

Enable debugging using devel module by adding the following setting to
`BACKDROP_ROOT/settings.php`

```php
$conf['simple_ldap_devel'] = TRUE;
```


Testing
-------

The simpletests provided with this module automatically configure themeselves
to use the active configuration in order to perform a real-world test against
your real LDAP server.

THIS MEANS THAT DATA WILL BE ADDED/DELETED ON YOUR REAL LDAP SERVER!

The simpletests only operate against entries it creates, but in the event of a
failure, the test cannot clean up after itself. If you are testing a specific
configuration, it is recommended to run the test against a development or
staging directory first.


Building a test environment
---------------------------

You can build a test environment with this description. You can download and
install a prepared configuration. There is a `Vagrantfile` included that will
build a virtual machine with a working LDAP directory.

1. Install VirtualBox. https://www.virtualbox.org/
2. Install Vagrant. https://www.vagrantup.com/
3. Download this project: https://github.com/VasasA/simple_ldapVM/archive/7.x-1.x.zip
(It is a fork of https://github.com/ulsdevteam/simple_ldap)
4. Unzip it into a directory.
5. Open Terminal, and `cd` to this directory (containing the `Vagrantfile`).
6. Run this command: `vagrant up`
It will download and build a virtual machine with a working LDAP directory.
(It may take a long time.)
7. When complete, there is the IP address in the last line. If OS X is the
Vagrant host, then the vagrant box is available at `simpleldap.local`
For other operating systems, the IP address will need to be obtainted manually,
and added to the local hosts file for best results.
8. You have to configure Simple LDAP module according to the LDAP server:
Unzip the `test_configs_simple_ldap.zip`, and move the json files into the 
corrensponding module's config directory. (Make a copy of the original files.)
9. The `sn` attribute is required in this LDAP directory. So you have to insert
a new line `$this->attributes['sn'] = 'UserSurname';` 
into the `SimpleLdapUser.class.php` line 243.
The result:
```php
$this->attributes['sn'] = 'UserSurname';
$this->server->add($this->dn, $this->attributes);
```
10. Create a new Backdrop role: `default_group`
Administration > Configuration > User accounts > Add role button
11. You can run the self test:
Administration > Configuration > Development > Testing > Simple LDAP
12. Install the modules of Simple LDAP, if you want to take a manual test.
13. You can shut down the virtual machine with this command: `vagrant halt`

**LDAP**
The LDAP is pre-populated with some dummy data.
ldap://simpleldap.local
DN: cn=admin,dc=local
password: admin

DN: cn=ldapuser,ou=people,dc=local
password: ldapuser

**phpLDAPadmin**
phpLDAPadmin is available at http://simpleldap.local/pma
Login DN: cn=admin,dc=local
password: admin

**Virtual machine's console or ssh**
Console or ssh credentials:
username: vagrant
password: vagrant

**Drupal 7**
The virtual machine also contains a Drupal 7 installation with Simple LDAP
module. The Drupal installation is set up with the following credentials:
http://simpleldap.local/
username: admin
password: admin


Issues
------

Bugs and Feature requests should be reported in the Issue Queue:
https://github.com/backdrop-contrib/simple_ldap/issues


Current Maintainer
------------------

- Attila Vasas (https://github.com/vasasa).
- Seeking additional maintainers.


Credits
-------

- Ported to Backdrop CMS by Attila Vasas (https://github.com/vasasa).
- Originally written for Drupal: https://www.drupal.org/node/1845170/committers


License
-------

This project is GPL v2 software. See the LICENSE.txt file in this directory for
complete text.


Screenshot
----------

![Simple LDAP screenshot](https://github.com/backdrop-contrib/simple_ldap/blob/1.x-1.x/images/screenshot.png)
