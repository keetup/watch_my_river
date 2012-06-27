<?php

/**
 * Watch my River Items start plugin
 *
 * @package WMR
 */
elgg_register_event_handler('init', 'system', 'wmr_init');

function wmr_init() {
    /**
     * Register the event when plugin is deactivated
     * @FIXME: This will not work as desired.
     * The wanted result, should be, after flush cache when the module is disabled
     * 
     * ElggCore package do not support an event or a plugin hook when cache is clear
     */
//    elgg_register_event_handler('deactivate', 'plugin', 'wmr_on_deactivate_plugin');
    //Register a cron to fix the river items
    elgg_register_plugin_hook_handler('cron', 'daily', 'wmr_fix_river_items');

    //Add a button on dashboard widget to fix plugins river items
    elgg_register_menu_item('admin_control_panel', array(
        'name' => 'fix_plugins',
        'text' => elgg_echo('wmr:fix_items'),
        'href' => 'watch_my_river/fixme',
        'link_class' => 'elgg-button elgg-button-action',
    ));

    elgg_register_page_handler('watch_my_river', 'wmr_page_handler');

    run_function_once('wmr_create_database');
}

function wmr_page_handler($page) {
    admin_gatekeeper();

    /**
     * We have only one page, "fixme" one that will fix items 
     */
    wmr_fix_river_items();
    system_message(elgg_echo('wmr:fixme:success'));
    forward(REFERER);
}

/**
 * This will generate a disabled table to move river items on them
 * 
 * @return boolean 
 */
function wmr_create_database() {
    $schema_file = elgg_get_plugins_path() . 'watch_my_river/schema/mysql.sql';
    run_sql_script($schema_file);
    return TRUE;
}

/**
 * Event when plugin is deactivate
 * 
 * @param string $event
 * @param string $otype
 * @param mix $object 
 */
function wmr_on_deactivate_plugin($event, $otype, $object) {
    $check_event = ($event == 'deactivate');
    $check_otype = ($otype == 'plugin');

    if ($check_event && $check_otype) {
        //We fix every river items so no problems will go
        wmr_fix_river_items();
    }
}

function wmr_river_callback($row) {

    $view = $row->view;

    $wmr_ob = new stdClass();

    $wmr_ob->type = $row->type;
    $wmr_ob->subtype = $row->subtype;
    $wmr_ob->view = $view;

    //Check if a view exist, without check extensions
    if (elgg_view_exists($view, '', FALSE)) {
        $wmr_ob->disable_items = 'no';
    } else {
        $wmr_ob->disable_items = 'yes';
    }

    return $wmr_ob;
}

/**
 * Get all views and disable the river items that have not an existing view 
 */
function wmr_fix_river_items() {
    $dbprefix = elgg_get_config('dbprefix');
    
    //Get enabled river items
    $query_get_rivers = "SELECT * FROM {$dbprefix}river GROUP BY view";
    $river_views = get_data($query_get_rivers, 'wmr_river_callback');

    //Get disabled river items
    $query_get_disabled_rivers = "SELECT * FROM {$dbprefix}river_disabled GROUP BY view";
    $disabled_river_views = get_data($query_get_disabled_rivers, 'wmr_river_callback');

    //If there are some empty river item, then move to disabled table
    if (is_array($river_views)) {
        foreach ($river_views as $wmr_ob) {
            if ($wmr_ob->disable_items == 'yes') {
                //Move them to river_disabled
                wmr_move_to_disabled($wmr_ob->view);
            }
        }
    }

    //If the view on disabled river items exists, then enable
    if (is_array($disabled_river_views)) {
        foreach ($disabled_river_views as $disabled_view) {
            if ($wmr_ob->disable_items == 'no') {
                //Move them to river_disabled
                wmr_move_to_enabled($disabled_view->view);
            }
        }
    }

    return $river_views;
}

/**
 * Move a view into river_disabled table
 * 
 * @param string $view
 * @return bool 
 */
function wmr_move_to_disabled($view) {
    $db_prefix = elgg_get_config('dbprefix');

    $to_table = $db_prefix . 'river_disabled';
    $from_table = $db_prefix . 'river';

    return wmr_generic_move($to_table, $from_table, $view);
}

/**
 * Move a view into river table
 * 
 * @param string $view
 * @return bool 
 */
function wmr_move_to_enabled($view) {
    $db_prefix = elgg_get_config('dbprefix');

    $from_table = $db_prefix . 'river_disabled';
    $to_table = $db_prefix . 'river';

    wmr_generic_move($to_table, $from_table, $view);
}


/**
 * Generic function to move items into tables
 * 
 * @param string $to_table
 * @param string $from_table
 * @param string $view
 * @return boolean 
 */
function wmr_generic_move($to_table, $from_table, $view) {
    if (empty($view) || empty($from_table) || empty($to_table)) {
        return FALSE;
    }

    $query = "INSERT INTO `{$to_table}` SELECT * FROM `{$from_table}` WHERE view = '{$view}'";

    $success = FALSE;

    try {
        $success = insert_data($query);
    } catch (Exception $e) {
        return FALSE;
    }

    if ($success) {
        $delete_query = "DELETE FROM `{$from_table}` WHERE `{$from_table}`.`view` = '{$view}'";
        return delete_data($delete_query);
    }

    return $success;
}