<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This class is responsible for managing login session, mainly concentrating on
 * DB I/O.
 *
 * Normally, you should not have to work with this class unless you are either
 * writing an authentication front or back end, or a component which includes online
 * status notifications and the like.
 *
 * The single instance of this class can be accessed as
 * midcom::get('auth')->sessionmgr.
 *
 * <b>Checking whether a user is online</b>
 *
 * The is-user-online check requires the user to have the privilege <i>midcom:isonline</i>
 * set on the user which he is trying to check.
 *
 * @package midcom.services
 */
class midcom_services_auth_sessionmgr
{
    /**
     * A list of loaded login sessions, indexed by their session identifier.
     * This is used for authentication purposes.
     *
     * @var Array
     */
    private $_loaded_sessions = Array();

    /**
     * Once a session has been authenticated, this variable holds the ID of the current
     * login session.
     *
     * Care should be taken when using this variable, as quite sensitive information can
     * be obtained with this session id.
     *
     * @var string
     */
    var $current_session_id = null;

    var $auth = null;

    /**
     * Currently authenticated midgard_user object
     *
     * @var midgard_user
     */
    var $user = null;

    /**
     * Currently authenticated midgard_person object
     *
     * @var midgard_person
     */
    var $person = null;

    /**
     * Simple, currently empty default constructor.
     */
    public function __construct($auth)
    {
        $this->auth = $auth;
    }

    /**
     * Creates a login session using the given arguments. Returns a session identifier.
     * The call will validate the passed credentials and thus authenticate for the given
     * user at the same time, so there is no need to call authenticate_session() after
     * creating it. A failed password check will of course not create a login session.
     *
     * @param string $username The name of the user to store with the session.
     * @param string $password The clear text password to store with the session.
     * @param string $clientip The client IP to which this session is assigned to. This
     *     defaults to the client IP reported by Apache.
     * @return Array An array holding the session identifier in the 'session_id' key and
     *     the associated user in the 'user' key (take this by reference!). Failure returns false.
     */
    public function create_login_session($username, $password, $clientip = null)
    {
        if ($clientip === null)
        {
            $clientip = $_SERVER['REMOTE_ADDR'];
        }

        if (!$this->_do_midgard_auth($username, $password))
        {
            debug_add('Failed to create a new login session: Authentication Failure', MIDCOM_LOG_ERROR);
            return false;
        }

        if (!$user = $this->auth->get_user($this->person))
        {
            debug_add("Failed to create a new login session: User ID " . midcom_connection::get_user() . " is invalid.", MIDCOM_LOG_ERROR);
            return false;
        }

        $session = new midcom_core_login_session_db();
        $session->userid = $user->id;
        $session->username = $username;
        $session->password = $this->_obfuscate_password($password);
        $session->clientip = $clientip;
        $session->timestamp = time();

        if (!$session->create())
        {
            debug_add('Failed to create a new login session: ' . midcom_connection::get_error_string(), MIDCOM_LOG_ERROR);
            return false;
        }

        $result = array
        (
            'session_id' => $session->guid,
            'user' => $user
        );

        $this->current_session_id = $session->guid;
        $this->_loaded_sessions[$session->guid] = $session;
        return $result;
    }

    /**
     * Creates a trusted login session using the given arguments. Returns a session identifier.
     * The call will validate the passed credentials and thus authenticate for the given
     * user at the same time, so there is no need to call authenticate_session() after
     * creating it.
     *
     * @param string $username The name of the user to store with the session.
     * @param string $clientip The client IP to which this session is assigned to. This
     *     defaults to the client IP reported by Apache.
     * @return Array An array holding the session identifier in the 'session_id' key and
     *     the associated user in the 'user' key (take this by reference!). Failure returns false.
     */
    public function create_trusted_login_session($username, $clientip = null)
    {
        if ($clientip === null)
        {
            $clientip = $_SERVER['REMOTE_ADDR'];
        }

        if (!$this->_do_trusted_midgard_auth($username))
        {
            debug_add('Failed to create a new login session: Authentication Failure', MIDCOM_LOG_ERROR);
            return false;
        }

        if (!$user = $this->auth->get_user($this->person))
        {
            debug_add("Failed to create a new login session: User ID {$username} is invalid.", MIDCOM_LOG_ERROR);
            return false;
        }

        $session = new midcom_core_login_session_db();
        $session->userid = $user->id;
        $session->username = $username;
        $session->trusted = true;
        $session->clientip = $clientip;
        $session->timestamp = time();

        if (!$session->create())
        {
            debug_add('Failed to create a new login session: ' . midcom_connection::get_error_string(), MIDCOM_LOG_ERROR);
            return false;
        }

        $result = array
        (
            'session_id' => $session->guid,
            'user' => $user
        );

        $this->current_session_id = $session->guid;

        return $result;
    }

    /**
     * Checks the system for a valid login session matching the passed arguments.
     * It validates the clients' IP, the user ID and the sesion timeout. If a valid
     * session is found, its ID is returned again, you can from now on use this as
     * a token for authentication.
     *
     * This code will implicitly clean up stale sessions for the current
     * user.
     *
     * @param string $sessionid The Session ID to check for.
     * @param midcom_core_user The user for which we should look up the login session.
     * @param string $clientip The client IP to check against, this defaults to the
     *     client IP reported by Apache.
     * @return string The token you can use for authentication or false, in case there
     *     is no valid session.
     */
    function load_login_session($sessionid, $user, $clientip = null)
    {
        try
        {
            $session = new midcom_core_login_session_db($sessionid);
        }
        catch (Exception $e)
        {
            debug_add('Login session ' . $sessionid . ' failed to load: ' . $e->getMessage(), MIDCOM_LOG_INFO);
            return false;
        }

        if ($clientip === null)
        {
            $clientip = $_SERVER['REMOTE_ADDR'];
        }

        $timed_out = time() - midcom::get('config')->get('auth_login_session_timeout');

        if ($session->timestamp < $timed_out)
        {
            $session->delete();
            debug_add("The session {$session->guid} (#{$session->id}) has timed out.", MIDCOM_LOG_INFO);
            return false;
        }

        if (   midcom::get('config')->get('auth_check_client_ip')
            && $session->clientip != $clientip)
        {
            debug_add("The session {$session->guid} (#{$session->id}) had mismatching client IP.", MIDCOM_LOG_INFO);
            debug_add("Expected {$session->clientip}, got {$clientip}.");
            return false;
        }

        if ($session->timestamp < time() - midcom::get('config')->get('auth_login_session_update_interval'))
        {
            // Update the timestamp if previous timestamp is older than specified interval
            $session->timestamp = time();
            try
            {
                if (!$session->update())
                {
                    debug_add("Failed to update the session {$session->guid} (#{$session->id}) to the current timestamp: " . midcom_connection::get_error_string(), MIDCOM_LOG_INFO);
                }
            }
            catch (Exception $e)
            {
                debug_add("Failed to update the session {$session->guid} (#{$session->id}) to the current timestamp: " . $e->getMessage(), MIDCOM_LOG_INFO);
            }
        }

        $this->_loaded_sessions[$sessionid] = $session;
        return $sessionid;
    }

    /**
     * Internal helper, which does the actual Midgard authentication.
     *
     * @param string $username The name of the user to authenticate.
     * @param string $password The password of the user to authenticate.
     * @return boolean Indicating success.
     */
    private function _do_midgard_auth($username, $password)
    {
        if ($username == '' || $password == '')
        {
            debug_add("Failed to authenticate: Username or password is empty.", MIDCOM_LOG_ERROR);
            return false;
        }
        $this->user = midcom_connection::login($username, $password);

        return $this->_load_person($username);
    }

    /**
     * Internal helper, which does the actual trusted Midgard authentication.
     *
     * @param string $username The name of the user to authenticate.
     * @return boolean Indicating success.
     */
    private function _do_trusted_midgard_auth($username)
    {
        if ($username == '')
        {
            debug_add("Failed to authenticate: Username is empty.", MIDCOM_LOG_ERROR);
            return false;
        }

        $this->user = midcom_connection::login($username, '', true);

        return $this->_load_person($username);
    }

    private function _load_person($username)
    {
        if (!$this->user)
        {
            debug_add("Failed to authenticate the given user: ". midcom_connection::get_error_string(),
            MIDCOM_LOG_INFO);
            return false;
        }

        $this->person = $this->user->get_person();
        $person_class = midcom::get('config')->get('person_class');
        if (get_class($this->person) != $person_class)
        {
            // Cast the person object to correct person class
            $this->person = new $person_class($this->person->guid);
        }
        return true;
    }

    /**
     * This function authenticates a given session, which must have been loaded
     * previously with load_login_session (this is mandatory).
     *
     * On success, the Auth service main object will automatically be resynced to
     * the authenticated user.
     *
     * If authentication fails, an invalid session is assumed, which will be
     * invalidated and deleted immediately.
     *
     * @param string $sessionid The session identifier to authenticate against.
     * @return boolean Indicating success.
     */
    function authenticate_session($sessionid)
    {
        if (! array_key_exists($sessionid, $this->_loaded_sessions))
        {
            debug_add("The session {$sessionid} has not been loaded yet, cannot authenticate to it.", MIDCOM_LOG_ERROR);
            return false;
        }

        $session = $this->_loaded_sessions[$sessionid];
        $username = $session->username;

        if ($session->trusted)
        {
            $auth_result = $this->_do_trusted_midgard_auth($username);
        }
        else
        {
            $password = $this->_unobfuscate_password($session->password);
            $auth_result = $this->_do_midgard_auth($username, $password);
        }

        if (!$auth_result)
        {
            unset ($this->_loaded_sessions[$sessionid]);
            if (! $session->delete())
            {
                debug_add("Failed to delete the invalid session {$session->guid} (#{$session->id}): " . midcom_connection::get_error_string(), MIDCOM_LOG_INFO);
                return false;
            }
            $session->purge();
            return false;
        }

        $this->current_session_id = $sessionid;

        return true;
    }

    /**
     * Call this function to drop a session which has been previously loaded successfully.
     * Usually, you will use this during logouts.
     *
     * @param string $sessionid The id of the session to invalidate.
     * @return boolean Indicating success.
     */
    function delete_session($sessionid)
    {
        if (! array_key_exists($sessionid, $this->_loaded_sessions))
        {
            debug_add('Only sessions which have been previously loaded can be deleted.', MIDCOM_LOG_ERROR);
            return false;
        }

        $session = $this->_loaded_sessions[$sessionid];

        if (! $session->delete())
        {
            debug_add("Failed to delete the delete session {$session->guid} (#{$session->id}): " . midcom_connection::get_error_string(), MIDCOM_LOG_INFO);
            return false;
        }
        $session->purge();

        unset ($this->_loaded_sessions[$sessionid]);
        return true;
    }

    /**
     * This function obfuscates a password in some way so that accidential
     * "views" of a password in the database or a log are not immediately
     * a problem. This is not targeted to prevent intrusion, just to prevent
     * somebody viewing the logs or debugging the system is able to just
     * read somebody elses passwords (especially given that many users
     * share their passwords over multiple systems).
     *
     * _unobfuscate_password() is used to restore the password into its original
     * form.
     *
     * @param string $password The password to obfuscate.
     * @return string The obfuscated password.
     * @see _unobfuscate_password()
     */
    private function _obfuscate_password($password)
    {
        return base64_encode($password);
    }

    /**
     * Reverses password obfuscation.
     *
     * @param string $password The password to obfuscate.
     * @return string The obfuscated password.
     * @see _unobfuscate_password()
     */
    private function _unobfuscate_password($password)
    {
        return base64_decode($password);
    }

    /**
     * This function is called by the framework whenever a user's password is updated. It will
     * synchronize all active login sessions of that user to the new password.
     *
     * Access to this function is restricted to midcom_core_account.
     *
     * @param midcom_core_user $user A reference to the user object which has been updated.
     * @param string $new The new password (plain text).
     */
    function _update_user_password(&$user, $new)
    {
        if (empty($new))
        {
            return;
        }
        $qb = new midgard_query_builder('midcom_core_login_session_db');
        $qb->add_constraint('userid', '=', $user->id);
        $result = @$qb->execute();

        if (empty($result))
        {
            // No login sessions found
            return;
        }

        foreach ($result as $session)
        {
            $session->password = $this->_obfuscate_password($new);
            $session->update();
        }
    }

    /**
     * This function is called by the framework whenever a user's username is updated. It will
     * synchronize all active login sessions of that user to the new username.
     *
     * Access to this function is restricted to midcom_core_account.
     *
     * @param midcom_core_user $user A reference to the user object which has been updated.
     * @param string $new The new username.
     */
    function _update_user_username(&$user, $new)
    {
        if (empty($new))
        {
            return;
        }
        $qb = new midgard_query_builder('midcom_core_login_session_db');
        $qb->add_constraint('userid', '=', $user->id);
        $result = @$qb->execute();

        if (! $result)
        {
            // No login sessions found
            return true;
        }

        foreach ($result as $session)
        {
            $session->username = $new;
            $session->update();
        }
    }

    public function _delete_user_sessions(midcom_core_user $user)
    {
        // Delete login sessions
        $qb = new midgard_query_builder('midcom_core_login_session_db');
        $qb->add_constraint('userid', '=', $user->id);
        $result = @$qb->execute();
        if ($result)
        {
            foreach ($result as $entry)
            {
                debug_add("Deleting login session ID {$entry->id} for user {$entry->username} with timestamp {$entry->timestamp}");
                $entry->delete();
            }
        }
    }

    /**
     * Checks the online state of a given user. You require the privilege midcom:isonline
     * for the user you are going to check. The privilege is not granted by default,
     * to allow users full control over their privacy.
     *
     * 'unknown' is returned in cases where you have insufficient permissions.
     *
     * @param midcom_core_user $user The user object which has been updated.
     * @return string One of 'online', 'offline' or 'unknown', indicating the current online
     *     state.
     */
    function is_user_online($user)
    {
        if (!$user->get_storage()->can_do('midcom:isonline'))
        {
            return 'unknown';
        }

        $timed_out = time() - midcom::get('config')->get('auth_login_session_timeout');
        $qb = new midgard_query_builder('midcom_core_login_session_db');
        $qb->add_constraint('userid', '=', $user->id);
        $qb->add_constraint('timestamp', '>=', $timed_out);
        $result = @$qb->execute();

        if (! $result)
        {
            return 'offline';
        }
        return 'online';
    }

    /**
     * Returns the total number of users online. This does not adhere the isonline check,
     * as there is no information about which users are online.
     *
     * The test is, as usual, heuristic, as it will count users which forgot to log off
     * as long as their session did not expire.
     *
     * @return int The count of users online
     * @todo Move this to a SELECT DISTINCT for performance reasons ASAP.
     */
    function get_online_users_count()
    {
        $timed_out = time() - midcom::get('config')->get('auth_login_session_timeout');
        $qb = new midgard_query_builder('midcom_core_login_session_db');
        $qb->add_constraint('timestamp', '>=', $timed_out);
        $result = @$qb->execute();

        // Filter out duplicate sessions.
        $userids = Array();
        foreach ($result as $session)
        {
            $userids[] = $session->userid;
        }
        return count(array_unique($userids));
    }

    /**
     * Extended check for online users. Returns an array of guid=>midcom_core_user pairs of the users
     * which are currently online. This takes privileges into account and will thus only list
     * users which the current user has the privililege to observe.
     *
     * So the difference between get_online_users_count and the size of this result set is the number
     * of invisible users.
     *
     * @return Array List of visible users that are online.
     * @todo Move this to a SELECT DISTINCT for performance reasons ASAP.
     */
    function get_online_users()
    {
        $timed_out = time() - midcom::get('config')->get('auth_login_session_timeout');
        $qb = new midgard_query_builder('midcom_core_login_session_db');
        $qb->add_constraint('timestamp', '>=', $timed_out);
        $query_result = @$qb->execute();
        $result = Array();
        if ($query_result)
        {
            foreach ($query_result as $session)
            {
                $user = $this->auth->get_user($session->userid);
                if (array_key_exists($user->guid, $result))
                {
                    // Skip duplicate login sessions
                    continue;
                }
                if (   $user
                    && $user->is_online())
                {
                    $result[$user->guid] =& $user;
                }
            }
        }
        return $result;
    }
}
?>
