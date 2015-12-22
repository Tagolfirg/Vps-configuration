#!/usr/bin/php
<?php
if (!isset($argc) || !isset($argv)) {
    exit_with_error('run it from cli');
}

if (!isset($argv[1])) {
    exit_with_error('no sitename');
}

if (exec('whoami') != 'root') {
    exit_with_error('not root? are you crazy? run me as root!');
}

define('NOP', NULL);
define('SITENAME', $argv[1]);
define('SITEPATH', implode('_', array_reverse(explode('.', SITENAME))));
define('USERNAME', SITEPATH);
define('USERGROUP', USERNAME);

define('NGINX_DIR', '/etc/nginx/');
define('PHPFPM_DIR', '/etc/php/fpm-php5.6/');
define('PHPFPM_SESSION_PATH', '/var/lib/php/session/%USERNAME/'); // Change it also in php-fpm template

$array_find    = array('%SITENAME%','%SITEPATH%','%USERNAME%','%USERGROUP%');
$array_replace = array(SITENAME,SITEPATH,USERNAME,USERGROUP);

echo "\n\n";
echo 'SITENAME: '.SITENAME."\n"; 
echo 'SITEPATH: '.SITEPATH."\n"; 
echo 'USERNAME: '.USERNAME."\n"; 
echo 'USERGROUP: '.USERGROUP."\n"; 
echo 'DATABASENAME: '.USERNAME."\n"; 
echo 'DATABASEUSER: '.USERNAME."\n"; 
confirmation() ? NOP : exit();
echo "\n";

// for processes' pipes
$descriptorspec = array(
   0 => array('pipe', 'r'), 
   1 => array('pipe', 'w'),
   2 => array('file', '/dev/null', 'w')
);

// Adding new user
echo "\n\n\t=== Adding user ===";
if (confirmation('Adding user '.USERNAME.'?') === true) {
    $user_exists = exec('id '.USERNAME);
    if (stristr($user_exists, 'No such user') !== false) {
        confirmation('User "' . USERNAME . '" is exists!');
        exit();
    } else {
        $password = generate_password();

        // Adding user in ubuntu
        /*
        $process = proc_open('adduser --quiet '.USERNAME, $descriptorspec, $pipes);

        // For additional userinfo in ubuntu
        fwrite($pipes[0], "{$password}\n");
        fwrite($pipes[0], "{$password}\n");
        for ($i=0; $i<5; $i++) {
            fwrite($pipes[0], "\n");
        }
        fwrite($pipes[0], "y\n");
        fclose($pipes[0]);
        fclose($pipes[1]);
        */

        // Adding user in gentoo
        /*
        $process = proc_open("useradd -mU -p \$(openssl passwd \"{$password}\") ".USERNAME, $descriptorspec, $pipes);
        */

        proc_close($process);

        exec('usermod -aG ' . USERGROUP . ' nginx');

        save_data(USERNAME, 'user login', USERNAME);
        save_data(USERNAME, 'user password', $password);
        save_data(USERNAME);

        echo 'User added. Nginx added to user\'s group' . "\n";

        // Set correct rights to new home
        chmod('/home/'.USERNAME, 0750);

        // Create public html dir and default placeholder
        mkdir('/home/'.USERNAME.'/public_html', 0770);
        chmod('/home/'.USERNAME.'/public_html', 0770);

        // Create a defult index.html
        file_put_contents('/home/'.USERNAME.'/public_html/index.html', SITENAME);
        chmod('/home/'.USERNAME.'/public_html/index.html', 0755);

        // Change owner of home & public_html
        exec(sprintf('chown %s:%s -R %s', USERNAME, USERNAME, '/home/'.USERNAME.'/'));
        echo 'Default index.html was created' . "\n";
    }
}



// Creating rule for php-fpm
echo "\n\n\t=== Creating rule for php-fpm ===";
if (confirmation('Create rule for php-fpm for site '.SITENAME.'?') === true) {
    $default_php_fpm_rule = PHPFPM_DIR . '_include/_template';
    if (!file_exists($default_php_fpm_rule)) {
        confirmation('Can\'t find default rule for php-fpm.');
        exit();
    } else {
        $php_fpm_rule_content      = file_get_contents($default_php_fpm_rule);
        $php_fpm_rule_content      = str_replace($array_find, $array_replace, $php_fpm_rule_content);
        $php_fpm_rule_path         = PHPFPM_DIR . 'sites-available/' . SITEPATH;
        $php_fpm_rule_enabled_path = PHPFPM_DIR . 'sites-enabled/'   . SITEPATH;
        file_put_contents($php_fpm_rule_path, $php_fpm_rule_content);

        exec("mkdir -p "  . str_replace($array_find, $array_replace, PHPFPM_SESSION_PATH));
        exec("chmod 770 " . str_replace($array_find, $array_replace, PHPFPM_SESSION_PATH));

        if (confirmation('Enable rule for php-fpm for site ' . SITENAME .'?') === true) {
            exec("ln -s {$php_fpm_rule_path} {$php_fpm_rule_enabled_path}");
            echo 'Rule for php-fpm for site '. SITENAME . ' was enabled' . "\n";
        }

        if (confirmation('Reload configuration for php-fpm?') === true) {
            exec('/etc/init.d/php-fpm reload');
            echo 'Configuration for php-fpm was reloaded' . "\n";
        }
    }
}



// Creating rule for nginx
echo "\n\n\t=== Creating rule for nginx ===";
if (confirmation('Create rule for nginx for site '.SITENAME.'?') === true) {
    $default_nginx_rule = NGINX_DIR . '_include/_template';
    if (!file_exists($default_nginx_rule)) {
        confirmation('Can\'t find default rule for nginx.');
        exit();
    } else {
        $nginx_rule_content      = file_get_contents($default_nginx_rule);
        $nginx_rule_content      = str_replace($array_find, $array_replace, $nginx_rule_content);
        $nginx_rule_path         = NGINX_DIR . 'sites-available/' . SITEPATH;
        $nginx_rule_enabled_path = NGINX_DIR . 'sites-enabled/'   . SITEPATH;
        file_put_contents($nginx_rule_path, $nginx_rule_content);

        if (confirmation('Enable rule for nginx  for site ' . SITENAME .'?') === true) {
            exec("ln -s {$nginx_rule_path} {$nginx_rule_enabled_path}");
            echo 'Rule for nginx for site '. SITENAME . ' was enabled' . "\n";
        }

        if (confirmation('Reload configuration for nginx?') === true) {
            exec('/etc/init.d/nginx reload');
            echo 'Configuration for nginx was reloaded' . "\n";
        }
    }
}



// Creating mysql database
echo "\n\n\t=== Create mysql database ===";
if (confirmation('Create mysql database for user '.USERNAME.'?') === true) {
    $password = generate_password();
    die("\r\nCHANGE MYSQL PASSWORD\r\n");
    $mysql_link = mysql_connect("localhost", "root", "%PASSWORD%");
    if (!$mysql_link) {
        confirmation('Can\'t connect to mysql.');
        exit();
    } else {
        $temp_databaseuser = substr(USERNAME, 0, 16);
        $mysql_user_was_created = false;

        do {
            // Check mysql user name for availible
            $result = mysql_query("SELECT `User` FROM mysql.user WHERE `User` = '{$temp_databaseuser}';");
            $row = mysql_fetch_row($result);

            if ($row && isset($row[0]) && $row[0] == $temp_databaseuser) {
                // Default mysql length limit for username (or/and db name)
                $temp_databaseuser = substr($temp_databaseuser, 0, 16);
                $result = mysql_query("SELECT `User` FROM mysql.user WHERE `User` LIKE '{$temp_databaseuser}%' ORDER BY `User`;");

                echo "Sorry, but username '{$temp_databaseuser}' in mysql not availible, plz write another one:\n";
                while ($row = mysql_fetch_row($result)) {
                    echo "{$row[0]}\n";
                }

                echo "\n";
                $temp_databaseuser = substr(enter_a_word('Please type another username for database.'), 0, 16);
                continue;
            }

            $mysql_user_was_created = true;
        } while ($mysql_user_was_created === false);

        define('DATABASEUSER', $temp_databaseuser);
        define('DATABASENAME', $temp_databaseuser);

        mysql_query("CREATE USER '".DATABASEUSER."'@'localhost' IDENTIFIED BY '{$password}';");
        mysql_query("CREATE DATABASE `".DATABASENAME."`;");
        mysql_query("GRANT ALL PRIVILEGES ON `".DATABASENAME."`.* TO `". DATABASEUSER."`@'localhost' WITH GRANT OPTION;");

        mysql_close($mysql_link);
        echo 'Mysql database was created' . "\n";

        save_data(USERNAME, 'mysql login', DATABASEUSER);
        save_data(USERNAME, 'mysql password', $password);
        save_data(USERNAME, 'mysql database', DATABASENAME);
        save_data(USERNAME);
        echo 'Done :)' . "\n\n\n";
    }
}







function exit_with_error($error) {
    echo $error . "\n";
    die();
}

function confirmation($message='') {
    $message = "\n" . ($message=='' ? '' : "{$message} ") . 'Are you sure to continue? [y/n]' . "\n";

    while (true) {
        print $message;
        flush();
        @ob_flush();
        $confirmation = strtolower(trim(fgets(STDIN)));
        if (in_array($confirmation, array('y','n')) == true) {
            return $confirmation == 'y' ? true : false;
        }
    }
}

function generate_password() {
    $password = md5(md5(time().rand().time()) + 'pepperony');
    // Float length password lol
    $password = substr($password, rand(1,3), rand(12,16));
    return $password;
}

function save_data($username, $data_type='', $data='') {
    if (!file_exists('plz_do_not_stole_my_passwords')) {
        mkdir('plz_do_not_stole_my_passwords', 0700);
    }

    if(!file_exists("plz_do_not_stole_my_passwords/{$username}")) {
        file_put_contents("plz_do_not_stole_my_passwords/{$username}", '');
    }

    if ($data_type && $data) {
        file_put_contents("plz_do_not_stole_my_passwords/{$username}", "{$data_type}\t:\t{$data}\n", FILE_APPEND);
    } else {
        file_put_contents("plz_do_not_stole_my_passwords/{$username}", "\n\n", FILE_APPEND);
    }
    return true;
}

function enter_a_word($message) {
    $message = "\n" . ($message=='' ? '' : $message . ' ') . 'Type data and press <Enter> after it.' . "\n";

    while (true) {
        print $message;
        flush();
        ob_flush();
        $data = strtolower(trim(fgets(STDIN)));
        $confirmation = confirmation("You entered: '{$data}'. Is it correct?");
        if ($confirmation) {
            return $data;
        }
    }
}
