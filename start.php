<?php

/**
 * Watch my River Items start plugin
 *
 * @package WMR
 */
elgg_register_event_handler('init', 'system', 'wmr_init');

function wmr_init() {
    //Register the event when plugin is deactivated
    elgg_register_event_handler('deactivate', 'plugin', 'wmr_on_deactivate_plugin');

    //Register a cron to fix the river items
    elgg_register_plugin_hook_handler('cron', 'daily', 'wmr_fix_river_items');

    //Add a button on dashboard to fix plugins river items
    elgg_register_menu_item('admin_control_panel', array(
        'name' => 'fix_plugins',
        'text' => elgg_echo('wmr:fix_items'),
        'href' => 'watch_my_river/fixme',
        'link_class' => 'elgg-button elgg-button-action',
    ));
    
    elgg_register_page_handler('watch_my_river', 'wmr_page_handler');
}

function wmr_page_handler($page) {
    admin_gatekeeper();
    
    /**
     *We have only one page, "fixme" one that will fix items 
     */
    
    $river_views = wmr_fix_river_items();
    system_message(elgg_echo('wmr:fixme:success'));
    forward(REFERER);
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

    if (elgg_view_exists($view)) {
        $wmr_ob->disable_items = 'no';
    } else {
        $wmr_ob->disable_items = 'yes';
        /**
         * @TODO: Make some process to hide or to remove the items, maybe move them
         * to a tmp table
         *  But instead make a tmp table, should be great 
         * that river items has disable/enable support, like every entities
         */
    }

    return $wmr_ob;
}

/**
 * Get all views and disable the river items that have not an existing view 
 */
function wmr_fix_river_items() {
    $dbprefix = elgg_get_config('dbprefix');
    $query_get_rivers = "SELECT * FROM {$dbprefix}river GROUP BY view";

    $river_views = get_data($query_get_rivers, 'wmr_river_callback');

    return $river_views;
}