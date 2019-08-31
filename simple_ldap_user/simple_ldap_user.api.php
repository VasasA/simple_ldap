<?php
/**
 * @file
 * Describe hooks provided by the Simple LDAP User module.
 */

/**
 * SimpleLdapUser fingerprint.
 *
 * Variables exposed by __get() and __set()
 * ----------------------------------------
 * $attributes
 * $dn
 * $exists
 * $server
 *
 * Magic methods
 * -------------
 * __construct($name)
 * __get($name)
 * __set($name, $value)
 *
 * Public functions
 * ----------------
 * authenticate($password)
 * save()
 * delete()
 *
 * Public static methods
 * ---------------------
 * singleton($name)
 * filter()
 * reset()
 * hash($key, $value)
 */

/**
 * simple_ldap_user helper functions.
 *
 * simple_ldap_user_load_or_create_by_name($name)
 * simple_ldap_user_login_name_validate($form, &$form_state)
 * simple_ldap_user_sync_user_to_ldap($backdrop_user)
 * simple_ldap_user_sync_user_to_backdrop($backdrop_user)
 * simple_ldap_user_variable_get($variable)
 */

/**
 * Synchronizes a Backdrop user to LDAP.
 *
 * This hook is called when simple_ldap_user needs to synchronize Backdrop user
 * data to LDAP.
 *
 * This example sets the LDAP employeeType attribute to "full-time"
 *
 * @param StdClass $user
 *   The full Backdrop user object that is being synchronized.
 */
function hook_sync_user_to_ldap($user) {
  $ldap_user = SimpleLdapUser::singleton($user->name);
  $ldap_user->employeeType = 'full-time';
  $ldap_user->save();
}

/**
 * Alter user data before saving to Backdrop.
 *
 * This hook is called when simple_ldap_user is doing an account synchronization
 * from LDAP to Backdrop, immediately before user_save() is called.
 *
 * @param array $edit
 *   Array of changes to apply to the Backdrop user by user_save().
 * @param StdClass $backdrop_user
 *   The Backdrop user object to be saved.
 * @param SimpleLdapUser $ldap_user
 *   The SimpleLdapUser object that matches the Backdrop user object.
 */
function hook_simple_ldap_user_to_backdrop_alter($edit, $backdrop_user, $ldap_user) {
}

/**
 * Alter user data before saving to LDAP.
 *
 * This hook is called when simple_ldap_user is doing an account synchronization
 * from Backdrop to LDAP, immediately before SimpleLdapUser::save() is called.
 *
 * @param SimpleLdapUser $ldap_user
 *   The SimpleLdapUser object to be saved.
 * @param StdClass $backdrop_user
 *   The Backdrop user object that matches the SimpleLdapUser object.
 */
function hook_simple_ldap_user_to_ldap_alter($ldap_user, $backdrop_user) {
}
