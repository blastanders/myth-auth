<?php

use Myth\Auth\Entities\User;

if (! function_exists('logged_in')) {
    /**
     * Checks to see if the user is logged in.
     *
     * @return bool
     */
    function logged_in()
    {
        return service('authentication')->check();
    }
}

if (! function_exists('user')) {
    /**
     * Returns the User instance for the current logged in user.
     *
     * @return User|null
     */
    function user()
    {
        $authenticate = service('authentication');
        $authenticate->check();

        return $authenticate->user();
    }
}

if (! function_exists('user_id')) {
    /**
     * Returns the User ID for the current logged in user.
     *
     * @return int|null
     */
    function user_id()
    {
        $authenticate = service('authentication');
        $authenticate->check();

        return $authenticate->id();
    }
}

if (! function_exists('in_groups')) {
    /**
     * Ensures that the current user is in at least one of the passed in
     * groups. The groups can be passed in as either ID's or group names.
     * You can pass either a single item or an array of items.
     *
     * Example:
     *  in_groups([1, 2, 3]);
     *  in_groups(14);
     *  in_groups('admins');
     *  in_groups( ['admins', 'moderators'] );
     *
     * @param mixed $groups
     */
    function in_groups($groups): bool
    {
        $authenticate = service('authentication');
        $authorize    = service('authorization');

        if ($authenticate->check()) {
            return $authorize->inGroup($groups, $authenticate->id());
        }

        return false;
    }
}

if (! function_exists('has_permission')) {
    /**
     * Ensures that the current user has the passed in permission.
     * The permission can be passed in either as an ID or name.
     *
     * @param int|string $permission
     */
    function has_permission($permission): bool
    {
        $authenticate = service('authentication');
        $authorize    = service('authorization');

        if ($authenticate->check()) {
            return $authorize->hasPermission($permission, $authenticate->id()) ?? false;
        }

        return false;
    }
}

// Utility functions. Not related to the Auth library specifically, just handy to include them here

if(!function_exists('pre_var_dump')) {
    function pre_var_dump() {
        $dark_mode = false;
        $back_trace = debug_backtrace();
        $last = end($back_trace);

        $back_trace_array = array();

        foreach($back_trace as $each_trace) {
            $back_trace_array[] = @$each_trace['file'] . ' ' . @$each_trace['line'];
        }

        //Apparently hsl is the coolest color system
        $h = rand(0, 359);
        $s = rand(0, 99);
        $l = '';

        if ($dark_mode) {
            $l = '15';
            $color = 'white';
        } else {
            $l = '70';
            $color = 'black';
        }

        $container_id = "pre_var_dump-container-{$h}{$s}";

        $is_cli = php_sapi_name() == 'cli';

        if ($is_cli) {
            echo PHP_EOL . ">------------------ START: ".date("Y-m-d H:i:s")."----------------------<";
        } else {
            echo "<pre style='background-color:hsl({$h}deg, {$s}%, {$l}%); color: {$color}; padding: 10px; border-radius: 5px; font-size: 14px; line-height: 20px; z-index: 99999; position: relative;'><details>";
        }

        $step = 0;

        foreach($back_trace_array as $i => $each_trace) {

            if ($is_cli) {
                echo PHP_EOL . "[{$step}][{$each_trace}]";
            } else {
                if ($i == 0) {
                    echo "<summary>[{$step}][{$each_trace}]</summary>";
                } elseif ($i === 1) {
                    echo "[{$step}][{$each_trace}]";
                } else {
                    echo PHP_EOL . "[{$step}][{$each_trace}]";
                }
            }

            $step++;
        }
        if (!$is_cli) {
            echo "</details>".PHP_EOL;
        } else {
            echo PHP_EOL;
        }

        if (!$is_cli) {
            echo "<button type='button' onclick='document.querySelector(`#{$container_id}`).style.display == `none` ? document.querySelector(`#{$container_id}`).style.display = `block` : document.querySelector(`#{$container_id}`).style.display = `none`;'>";
            echo "Collapse/Expand - Collapse/Expand - Collapse/Expand - Collapse/Expand";
            echo "</button>";
        }

        if (!$is_cli) {
            echo "<div id='{$container_id}'>";
            foreach (func_get_args() as $param) {
                var_dump($param);
            }
            echo "</div>";
            echo "</pre>";
        } else {
            foreach (func_get_args() as $param) {
                var_dump($param);
                echo PHP_EOL;
            }
            echo PHP_EOL . ">------------------ END: ".date("Y-m-d H:i:s")."----------------------<" . PHP_EOL;
        }
    }
}

if (!function_exists("_get_database_change_name")) {
    function _get_database_change_name($conn = null, $year = '', $schema = '')
    {
        if (empty($year)) {
            $year = date("Y");
        }
        $database_change_name = "base_database_change_{$year}";

        if (!empty($schema)) {
            $schema = mysqli_real_escape_string($conn, str_replace("`", "", $schema));
            $check_for_exists = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '{$database_change_name}' AND TABLE_SCHEMA LIKE '{$schema}';";
        } else {
            $check_for_exists = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '{$database_change_name}';";
        }

        $check_for_exists = mysqli_query($conn, $check_for_exists);
        $check_for_exists = mysqli_num_rows($check_for_exists);

        if (empty($schema)) {
            $tbl_name = $database_change_name;
            $tbl_name_quoted = "`{$database_change_name}`";
            $file_name_quoted = "`base_database_change_file_name`";
        } else {
            $tbl_name = $schema . "." . $database_change_name;
            $tbl_name_quoted = "`{$schema}`.`{$database_change_name}`";
            $file_name_quoted = "`{$schema}`.`base_database_change_file_name`";
        }

        if ($check_for_exists < 1) {
            //create the db change table
            $sql = <<<SQL
                    CREATE TABLE {$tbl_name_quoted} (
                        `id` INT(15) NOT NULL AUTO_INCREMENT,
                        `query_type` SET('INSERT','UPDATE','DELETE') NOT NULL,
                        `database_table` VARCHAR(128) NOT NULL,
                        `data_id` VARCHAR(100) DEFAULT NULL,
                        `field_name` VARCHAR(100) DEFAULT NULL,
                        `field_data_old` TEXT DEFAULT NULL,
                        `field_data_new` TEXT DEFAULT NULL,
                        `ip` VARCHAR(15) NOT NULL,
                        `file_name` varchar(255) NOT NULL,
                        `user` INT(10) NOT NULL,
                        `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                        `delflag` INT(1) DEFAULT NULL,
                        `memid` INT(15) DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `cmbidx` (`query_type`,`data_id`,`database_table`) USING BTREE,
                        KEY `cmb2idx` (`query_type`,`data_id`,`database_table`,`field_name`) USING BTREE,
                        KEY `cmb3idx` (`query_type`,`field_data_old`(100),`database_table`,`field_name`) USING BTREE,
                        KEY `tbl` (`database_table`) USING BTREE,
                        KEY `dataidx` (`data_id`) USING BTREE
                    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
SQL;
            $query = mysqli_query($conn, $sql);

            $sql = <<<SQL
                CREATE TABLE IF NOT EXISTS `{$file_name_quoted}` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `hash` varchar(32) DEFAULT NULL,
                    `path` text DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `hashidx` (`hash`) USING BTREE
                  );
SQL;
            $query = mysqli_query($conn, $sql);

        }
        return $tbl_name;
    }
}

if (!function_exists("_insert_single_row_no_db_change")) {
    function _insert_single_row_no_db_change($conn = null, $table_name = null, $data_array = null, $return_sql = false) {
        // error_reporting(-1);
        if(empty($table_name)) {
            echo 'Error @ ' . __FILE__.__LINE__;
            return false;
        } else {
            $table_name = mysqli_real_escape_string($conn, $table_name);
        }

        if(empty($data_array)) {
            pre_var_dump('Error @ ' . __FILE__.__LINE__);
            return false;
        }

        if(!$conn) {
            echo 'Error @ ' . __FILE__.__LINE__;
            return false;
        }

        foreach($data_array as $index => $each_data_row) {
            if (is_array($each_data_row)) {
                if (empty($each_data_row)) {
                    $each_data_row = null;
                } else {
                    $each_data_row = json_encode($each_data_row);
                }
            }
            if($each_data_row === null || strtolower($each_data_row) == 'null') {
                $data_array[$index] = 'null';
            } else if(strtolower($each_data_row) == 'current_timestamp') {
                $data_array[$index] = 'CURRENT_TIMESTAMP';
            } else if($each_data_row === array()) {
                $data_array[$index] = 'null';
            } else {
                $data_array[$index] = "'" . mysqli_real_escape_string($conn, $each_data_row) . "'";
            }
        }

        $insert_cols = array_keys($data_array);
        $insert_cols = implode("`,`", $insert_cols);
        $insert_data = implode(",", $data_array);
        // $on_duplicate_key_update_string = implode(", ", $on_duplicate_key_update_array);
        $table_name = str_replace(".","`.`",$table_name);

        $insert_sql = "INSERT INTO `{$table_name}` (`$insert_cols`) VALUES ($insert_data);";

        if ($return_sql) {
            return $insert_sql;
        }

        try
        {
            $insert_query = mysqli_query($conn, $insert_sql);
        }
        catch (\Exception $e)
        {
            return $e->getMessage();
        }

        if ($insert_query) {
            $inserted_id = mysqli_insert_id($conn);
            return $inserted_id;
        } else {
            $err = mysqli_error($conn);

            // $error_data = array();
            // $error_data['query'] = mysqli_real_escape_string($conn, $insert_sql);
            // $error_data['error'] = mysqli_real_escape_string($conn, $err);

            // $error_log_insert_sql = "INSERT INTO `error_log` (query, error) VALUES ('{$error_data['query']}', '{$error_data['error']}');";
            // $error_log_insert_query = mysqli_query($conn, $error_log_insert_sql);

            return $err;
        }
    }
}

if (!function_exists("_db_change")) {
    function _db_change ($conn = null, $query_type = '', $table_name = '', $pk_col = null, $pk_val = null, $data_array = array(), $field_data_old = array(), $param = array()) {

        $sch = explode(".",$table_name);
        if(count($sch)==1) { $sch[1] = $sch[0]; $sch[0] = ""; }

        $database_change_name = _get_database_change_name($conn, null, $sch[0]);

        if (isset($_SESSION)) {
            if (!empty($_SESSION['remote_addr'])) {
                $ip = $_SESSION['remote_addr'];
            }
        }
        if (empty($ip)) {
            if( ! empty($_SERVER['HTTP_CLIENT_IP'])) {
                // ip from share internet
                $ip = @$_SERVER['HTTP_CLIENT_IP'];
            } elseif ( ! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                // ip pass from proxy
                $ip = @$_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
                $ip = @$_SERVER['REMOTE_ADDR'];
            } else {
                $ip = '';
            }
        }

        if (isset($_SESSION)) {
            // pre_var_dump($_SESSION);
            // die(PHP_EOL.__FILE__.__LINE__.PHP_EOL);
            if (empty($user)) {
                if (!empty($_SESSION['user'])) {
                    $user = $_SESSION['user'];
                }
            }
        }

        if (!empty($param['user'])) {
            $user = $param['user'];
        }

        if (empty($user)) {
            $user = 0;
        }

        $bt = debug_backtrace();
        $file = array();
        foreach ($bt as $caller) {
            $file[] = 'File:' . $caller['file'] . '  Line:' . $caller['line'];
        }
        $file = array_slice($file, 0, 4);
        $file = implode(PHP_EOL, $file);

        $file_path = mysqli_real_escape_string($conn, $file);
        $file = md5($file_path);
        $sch[0] = mysqli_real_escape_string($conn, str_replace("`", "", $sch[0]));

        $base_database_change_file_name_insert_sql = "INSERT IGNORE INTO `{$sch[0]}`.`base_database_change_file_name` (`hash`, `path`) VALUES ('{$file}', '{$file_path}');";
        $query = mysqli_query($conn, $base_database_change_file_name_insert_sql);

        if ($query_type == 'DELETE') {
            if (!empty($field_data_old)) {
                foreach ($field_data_old as $col_name => $each_col) {
                    $database_change_arr = array();
                    $database_change_arr['query_type'] = $query_type;
                    $database_change_arr['database_table'] = $table_name;
                    $database_change_arr['data_id'] = $pk_val;
                    $database_change_arr['field_name'] = $col_name;
                    $database_change_arr['field_data_old'] = @$field_data_old[$database_change_arr['field_name']];
                    $database_change_arr['field_data_new'] = null;
                    $database_change_arr['ip'] = $ip;
                    $database_change_arr['file_name'] = $file;
                    $database_change_arr['user'] = $user;
                    $database_change_arr['date'] = date("Y-m-d\TH:i:s");
                    $database_change_arr['delflag'] = 1;

                    _insert_single_row_no_db_change($conn, $database_change_name, $database_change_arr);
                }
            }
        } else {
            foreach ($data_array as $col_name => $each_col) {

                $database_change_arr = array();
                $database_change_arr['field_name'] = $col_name;
                $database_change_arr['field_data_old'] = @$field_data_old[$database_change_arr['field_name']];
                $database_change_arr['field_data_new'] = @$each_col;

                if ((string)$database_change_arr['field_data_new'] != (string)$database_change_arr['field_data_old']) {

                    $database_change_arr['query_type'] = $query_type;
                    $database_change_arr['database_table'] = $table_name;
                    $database_change_arr['data_id'] = $pk_val;
                    $database_change_arr['ip'] = $ip;
                    $database_change_arr['file_name'] = $file;
                    $database_change_arr['user'] = $user;
                    $database_change_arr['date'] = date("Y-m-d\TH:i:s");

                    _insert_single_row_no_db_change($conn, $database_change_name, $database_change_arr);
                }
            }
        }
    }
}



if (!function_exists("insert_single_row")) {
    function insert_single_row($conn = null, $table_name = null, $data_array = array(), $return_sql = false, $param = array()) {
        $table_name_esc = mysqli_real_escape_string($conn, $table_name);

        $sch = explode(".",$table_name);
        if(count($sch)==1) { $sch[1] = $sch[0]; $sch[0] = ""; } 

        $sch[0] = mysqli_real_escape_string($conn, $sch[0]);
        $sch[1] = mysqli_real_escape_string($conn, $sch[1]);

        if (empty($sch[0])) {
            $structure_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME LIKE '{$sch[1]}';";
        } else {
            $structure_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME LIKE '{$sch[1]}' AND TABLE_SCHEMA LIKE {'$sch[0]}';";
        }


        $structure_query = mysqli_query($conn, $structure_sql);
        $structure = array();

        $keys = array_keys($data_array);
        $keys = array_flip($keys);
        $keys = json_encode($keys);
        $keys = strtolower($keys);
        $keys = json_decode($keys, true);

        $to_insert = array();

        foreach($data_array as $key => $value) {
            $key = strtolower($key);
            $data_array[$key] = $value;
        }

        while ($row = mysqli_fetch_assoc($structure_query)) {
            $col_name_lower = strtolower($row['COLUMN_NAME']);
            if (!isset($keys[$col_name_lower])) {

            } else {
                $to_insert[$row['COLUMN_NAME']] = $data_array[$col_name_lower];
            }
        }


        $inserted_res = _insert_single_row_no_db_change($conn, $table_name, $to_insert, $return_sql);
        if (is_numeric($inserted_res)) {
            $to_insert['id'] = $inserted_res;
            // pre_var_dump($inserted_res);
            // die(PHP_EOL.__FILE__.__LINE__.PHP_EOL);
            _db_change($conn, 'INSERT', $table_name, 'id', $inserted_res, $to_insert, array(), $param);
        } else {

        }
        return $inserted_res;
    }
}


if (!function_exists("update_single_row")) {
    function update_single_row($conn = null, $table_name = null, $data_array = array(), $pk_col = '', $pk_val = '', $return_sql = false, $param = array()) {
        // error_reporting(-1);
        if(empty($table_name)) {
            echo('Error @ ' . __FILE__.__LINE__);
            return false;
        }
        
        if(empty($data_array)) {
            echo('Error @ ' . __FILE__.__LINE__);
            return false;
        }
        
        if(!$conn) {
            echo('Error @ ' . __FILE__.__LINE__);
            return false;
        }

        $data_array_raw = $data_array;

        $t_name_exp = explode(".", $table_name);

        if (sizeof($t_name_exp) == 2) {
            $t_name_exp[0] = mysqli_real_escape_string($conn, $t_name_exp[0]);
            $t_name_exp[1] = mysqli_real_escape_string($conn, $t_name_exp[1]);
            $table_name = "`{$t_name_exp[0]}`.`{$t_name_exp[1]}`";
        } else {
            $table_name = mysqli_real_escape_string($conn, $table_name);
            $table_name = "`{$table_name}`";
        }

        //$table_name = mysqli_real_escape_string($conn, $table_name);
        $pk_col = mysqli_real_escape_string($conn, $pk_col);
        $pk_val = mysqli_real_escape_string($conn, $pk_val);
            
        $field_data_old_sql = "SELECT * FROM {$table_name} WHERE `{$pk_col}` = '{$pk_val}';";
        $field_data_old_query = mysqli_query($conn, $field_data_old_sql);
        $field_data_old = mysqli_fetch_assoc($field_data_old_query);

        $update_arr = array();

        if ($field_data_old === null || $field_data_old_query === false) {
            echo "update_single_row() FAILED. SQL:[{$field_data_old_sql}] @ " . __FILE__.__LINE__.PHP_EOL;
            return false;
        }
        
        foreach($data_array as $index => $each_data_row) {
            if (is_array($each_data_row)) {
                if (empty($each_data_row)) {
                    $each_data_row = null;
                } else {
                    $each_data_row = json_encode($each_data_row);
                }
            }

            if (!array_key_exists($index, $field_data_old)) {
                // pre_var_dump($index);
                continue;
            }

            if($each_data_row === null || strtolower($each_data_row) == 'null') {
                $data_array[$index] = 'null';
            } else if(strtolower($each_data_row) == 'current_timestamp') {
                $data_array[$index] = 'CURRENT_TIMESTAMP';
            } else if($each_data_row === array()) {
                $data_array[$index] = 'null';
            } else {
                $data_array[$index] = "'" . mysqli_real_escape_string($conn, $each_data_row) . "'";
            }

            if ($data_array[$index] == "''") {
                $data_array[$index] = 'null';
            }
            
            $update_arr[] = " `{$index}` = $data_array[$index] ";
        }

        // pre_var_dump($field_data_old);
        // pre_var_dump($data_array);

        $update_str = implode(",", $update_arr);
        $update_sql = "UPDATE {$table_name} SET {$update_str} WHERE `{$pk_col}` = '{$pk_val}';";

        // pre_var_dump($update_sql);
     
        if ($return_sql) {
            return $update_sql;
        }

        try
        {
            $update_query = mysqli_query($conn, $update_sql);
        } 
        catch (\Exception $e)
        {
            return $e->getMessage();
        }
        
        if ($update_query) {
            _db_change($conn, 'UPDATE', $table_name, $pk_col, $pk_val, $data_array_raw, $field_data_old, $param);
            return $pk_val;
        } else {
            $err = mysqli_error($conn);

            // $error_data = array();
            // $error_data['query'] = mysqli_real_escape_string($conn, $update_sql);
            // $error_data['error'] = mysqli_real_escape_string($conn, $err);

            // $error_log_insert_sql = "INSERT INTO `error_log` (query, error) VALUES ('{$error_data['query']}', '{$error_data['error']}');";
            // $error_log_insert_query = mysqli_query($conn, $error_log_insert_sql);

            return $err;
        }
    }
}

if (!function_exists("delete_single_row")) {
    function delete_single_row ($conn = null, $table_name = null, $data_array = array(), $pk_col = '', $pk_val = '', $return_sql = false, $param = array()) {
        // error_reporting(-1);

        if (!empty($pk_col) && !empty($pk_val)) {
            if (!isset($data_array[$pk_col])) {
                $data_array[$pk_col] = $pk_val;
            }
        }

        if(empty($table_name)) {
            echo('Error @ ' . __FILE__.__LINE__);
            return false;
        } else {
            $table_name = str_replace("`", "", $table_name);
            if (stripos($table_name, ".") !== false) {
                $table_name = explode(".", $table_name);
                foreach ($table_name as &$each_part) {
                    $each_part = mysqli_real_escape_string($conn, $each_part);
                }

                $table_name = implode("`.`", $table_name);

            } else {
                $table_name = mysqli_real_escape_string($conn, $table_name);
            }
        }

        if(empty($data_array)) {
            echo('Error @ ' . __FILE__.__LINE__);
            return false;
        }

        if(!$conn) {
            echo('Error @ ' . __FILE__.__LINE__);
            return false;
        }

        $delete_where = '1=1';

        // $data_array_raw = $data_array;

        if (isset($data_array['id'])) {
            $pk_col = 'id';
            $pk_val = $data_array['id'];
        } else {
            $table_name_esc = mysqli_real_escape_string($conn, $table_name);
            $pk_sql = "SHOW KEYS FROM `{$table_name_esc}` WHERE Key_name = 'PRIMARY';";
            $pk_query = mysqli_query($conn, $pk_sql);
            $pk = mysqli_fetch_assoc($pk_query);
            $pk_col = $pk['Column_name'];
            // $pk_val = $data_array[$pk_col];
        }

        foreach($data_array as $index => $each_data_row) {
            $index = mysqli_real_escape_string($conn, $index);

            if (is_array($each_data_row)) {
                if (empty($each_data_row)) {
                    $each_data_row = null;
                } else {
                    $each_data_row = json_encode($each_data_row);
                }
            }
            if($each_data_row === null || strtolower($each_data_row) == 'null') {
                $data_array[$index] = 'null';
            } else if(strtolower($each_data_row) == 'current_timestamp') {
                $data_array[$index] = 'CURRENT_TIMESTAMP';
            } else if($each_data_row === array()) {
                $data_array[$index] = 'null';
            } else {
                $data_array[$index] = "'" . mysqli_real_escape_string($conn, $each_data_row) . "'";
            }

            if (strpos($data_array[$index], "'") !== false) {
                $delete_where .= " AND `$index` LIKE $data_array[$index]";
            } else {
                $delete_where .= " AND `$index` = $data_array[$index]";
            }

        }


        $field_data_old_sql = "SELECT * FROM `{$table_name}` WHERE {$delete_where};";
        $field_data_old_query = mysqli_query($conn, $field_data_old_sql);
        $field_data_old = mysqli_fetch_assoc($field_data_old_query);
        
        
        $delete_sql = "DELETE FROM `{$table_name}` WHERE {$delete_where};";

        if ($return_sql) {
            return $delete_sql;
        }

        $delete_query = mysqli_query($conn, $delete_sql);

        if ($delete_query) {
            $table_name = str_replace("`.`", ".", $table_name);
            _db_change($conn, 'DELETE', $table_name, $pk_col, $field_data_old[$pk_col], null, $field_data_old, $param);
            return true;
        } else {
            $err = mysqli_error($conn);

            // $error_data = array();
            // $error_data['query'] = mysqli_real_escape_string($conn, $update_sql);
            // $error_data['error'] = mysqli_real_escape_string($conn, $err);

            // $error_log_insert_sql = "INSERT INTO `error_log` (query, error) VALUES ('{$error_data['query']}', '{$error_data['error']}');";
            // $error_log_insert_query = mysqli_query($conn, $error_log_insert_sql);

            return $err;
        }
    }
}

if (!function_exists("bulk_insert")) {
    function bulk_insert ($conn = null, $table_name = null, $data_array = array(), $return_sql = false, $segment_size = 1000, $param = array()) {
        $table_name = str_replace("`", "", $table_name);
        $table_name_esc = mysqli_real_escape_string($conn, $table_name);

        $sch = explode(".",$table_name);
        if(count($sch)==1) { $sch[1] = $sch[0]; $sch[0] = ""; }

        $sch[0] = mysqli_real_escape_string($conn, $sch[0]);
        $sch[1] = mysqli_real_escape_string($conn, $sch[1]);

        $structure_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME LIKE '{$sch[1]}' AND TABLE_SCHEMA LIKE '{$sch[0]}';";
        //$structure_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME LIKE '{$table_name_esc}' AND TABLE_SCHEMA LIKE 'adminportal';";

        $structure_query = mysqli_query($conn, $structure_sql);
        $structure = array();


        $keys = array_keys(reset($data_array));
        $keys = array_flip($keys);
        $keys = json_encode($keys);
        $keys = strtolower($keys);
        $keys = json_decode($keys, true);

        $to_insert = array();

        foreach($data_array as $key => $value) {
            $key = strtolower($key);
            $data_array[$key] = $value;
        }

        $col_names = array();

        $tbl_cols = array();

        while ($row = mysqli_fetch_assoc($structure_query)) {
            $tbl_cols[strtolower($row['COLUMN_NAME'])] = $row['COLUMN_NAME'];
        }
        foreach ($keys as $key => $na) {

            if (isset($tbl_cols[$key])) {
                $col_names[$tbl_cols[$key]] = $tbl_cols[$key];
            }
        }

        foreach ($data_array as $i => $row) {
            if (sizeof($row) != sizeof($col_names)) {
                pre_var_dump("Mismatch columns on bulk insert on array index {$i}", $col_names, $row);
                die(PHP_EOL.__FILE__.__LINE__.PHP_EOL);
            }
        }

        $cols = implode("`, `", $col_names);
        $insert_prefix = "INSERT INTO `{$sch[0]}`.`{$sch[1]}` (`{$cols}`) VALUES ";
        $rows = array();

        $affected_rows = 0;

        $data_array = array_values($data_array);
        foreach ($data_array as $key => $row) {
            foreach ($row as &$d) {
                $d = mysqli_real_escape_string($conn, $d);
            }
            $rows[] = "('" . implode("','", $row) . "')";

            if ($key % $segment_size === 0) {
                $sql = implode(",", $rows);
                $sql = $insert_prefix . $sql . ";";

                // pre_var_dump($sql);

                $res = mysqli_query($conn, $sql);
                if (!$res) {
                    pre_var_dump(mysqli_error($conn));
                    pre_var_dump($sql);
                    die(PHP_EOL.__FILE__.__LINE__.PHP_EOL);
                }
                // pre_var_dump($res);
                $affected_rows += (int)mysqli_affected_rows($conn);
                // die(PHP_EOL.__FILE__.__LINE__.PHP_EOL);
                $rows = array();
            }
        }

        $sql = implode(",".PHP_EOL, $rows);
        $sql = $insert_prefix . $sql . ";";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            pre_var_dump(mysqli_error($conn));
            pre_var_dump($sql);
            die(PHP_EOL.__FILE__.__LINE__.PHP_EOL);
        }
        $affected_rows += (int)mysqli_affected_rows($conn);

        return $affected_rows;

    }
}

if (!function_exists('array_to_csv')) {
    function array_to_csv($path, $data_array) {
        $headers = implode('","', array_keys(reset($data_array)));
        $headers = '"' . $headers . '"';

        file_put_contents($path, $headers);

        foreach ($data_array as $each_row) {
            $each_row_text = implode('","', $each_row);
            $each_row_text = PHP_EOL . '"' . $each_row_text . '"';
            file_put_contents($path, $each_row_text, FILE_APPEND);
        }
        return true;
    }
}
if (!function_exists('csv_to_array')) {
    function csv_to_array ($path = '') {
        $rows   = array_map('str_getcsv', file($path));
        $header = array_shift($rows);
        $csv    = array();
        foreach($rows as $row) {
            $csv[] = array_combine($header, $row);
        }

        return $csv;
    }
}