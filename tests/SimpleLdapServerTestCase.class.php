<?php
/**
 * @file
 * SimpleLdapServerTestCase class.
 */

class SimpleLdapServerTestCase extends DrupalWebTestCase {

  /**
   * Inherited from DrupalWebTestCase::setUp().
   */
  public function setUp() {
    // Get the live simple_ldap config.
    $config = config('simple_ldap.settings');
    $host = $config->get('simple_ldap_host');
    $port = $config->get('simple_ldap_port');
    $starttls = $config->get('simple_ldap_starttls');
    $binddn = $config->get('simple_ldap_binddn');
    $bindpw = $config->get('simple_ldap_bindpw');
    $readonly = $config->get('simple_ldap_readonly');
    $pagesize = $config->get('simple_ldap_pagesize');
    $debug = $config->get('simple_ldap_debug');

    // Create the sandbox environment.
    $modules = func_get_args();
    if (isset($modules[0]) && is_array($modules[0])) {
      $modules = $modules[0];
    }
    parent::setUp($modules);

    // Enable the simple_ldap module.
    $modules = array('simple_ldap');
    $success = module_enable($modules);
    $this->assertTrue($success, t('Enabled modules: %modules', array('%modules' => implode(', ', $modules))));

    // Configure the sandbox environment.
    $config->set('simple_ldap_host', $host);
    $config->set('simple_ldap_port', $port);
    $config->set('simple_ldap_starttls', $starttls);
    $config->set('simple_ldap_binddn', $binddn);
    $config->set('simple_ldap_bindpw', $bindpw);
    $config->set('simple_ldap_readonly', $readonly);
    $config->set('simple_ldap_pagesize', $pagesize);
    $config->set('simple_ldap_debug', $debug);
    $config->save();
  }

}