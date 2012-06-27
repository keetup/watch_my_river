<?php

/**
 * This file is executed when the module is deactivated
 * 
 * Move all deactivated river items into the default river database
 */
$db_prefix = elgg_get_config('dbprefix');

$from_table = $db_prefix . 'river_disabled';
$to_table = $db_prefix . 'river';

$query = "INSERT INTO `{$to_table}` SELECT * FROM `{$from_table}`";
$success = insert_data($query);


if ($success) {
    $delete_query = "TRUNCATE TABLE `{$from_table}`";
    return delete_data($delete_query);
}